<?php
require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/Gasto.php';

/**
 * Modelo Reporte
 * Motor de analítica del panel: series de ventas, ranking de productos,
 * comportamiento de clientes y estado de resultados.
 *
 * Regla contable que se aplica en todas las consultas: los pedidos
 * cancelados NO cuentan como ingreso. Y el costo de la mercancía se
 * toma de `costo_total` del pedido —el costo congelado al momento de la
 * venta—, no del costo actual del producto, para que un cambio de precio
 * de proveedor no altere la utilidad de meses pasados.
 */
class Reporte
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    /* ═══════════════════════ RESUMEN GENERAL ═════════════════════ */

    /**
     * Indicadores del periodo + variación frente al periodo anterior
     * de la misma duración (eso es lo que convierte un número suelto
     * en información accionable).
     */
    public function resumen(string $desde, string $hasta): array
    {
        $actual = $this->totalesEntre($desde, $hasta);

        $dias      = max(1, (int) ((strtotime($hasta) - strtotime($desde)) / 86400) + 1);
        $desdePrev = date('Y-m-d', strtotime($desde . ' -' . $dias . ' days'));
        $hastaPrev = date('Y-m-d', strtotime($desde . ' -1 day'));
        $previo    = $this->totalesEntre($desdePrev, $hastaPrev);

        $variacion = static function (float $hoy, float $antes): ?float {
            if ($antes <= 0.0) {
                return $hoy > 0 ? 100.0 : null;
            }
            return round((($hoy - $antes) / $antes) * 100, 1);
        };

        return [
            'ingresos'          => $actual['ingresos'],
            'pedidos'           => $actual['pedidos'],
            'unidades'          => $actual['unidades'],
            'ticket_promedio'   => $actual['pedidos'] > 0 ? $actual['ingresos'] / $actual['pedidos'] : 0.0,
            'costo'             => $actual['costo'],
            'utilidad_bruta'    => $actual['ingresos'] - $actual['costo'],
            'margen'            => $actual['ingresos'] > 0
                ? round((($actual['ingresos'] - $actual['costo']) / $actual['ingresos']) * 100, 1)
                : 0.0,
            'var_ingresos'      => $variacion($actual['ingresos'], $previo['ingresos']),
            'var_pedidos'       => $variacion((float) $actual['pedidos'], (float) $previo['pedidos']),
            'previo'            => $previo,
            'periodo'           => ['desde' => $desde, 'hasta' => $hasta, 'dias' => $dias],
        ];
    }

    private function totalesEntre(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS pedidos,
                    COALESCE(SUM(total),0)       AS ingresos,
                    COALESCE(SUM(costo_total),0) AS costo,
                    COALESCE(SUM(envio),0)       AS envios,
                    COALESCE(SUM(descuento),0)   AS descuentos
             FROM pedidos
             WHERE estado <> :cancelado AND creado_en BETWEEN :desde AND :hasta'
        );
        $stmt->execute([
            ':cancelado' => 'cancelado',
            ':desde'     => $desde . ' 00:00:00',
            ':hasta'     => $hasta . ' 23:59:59',
        ]);
        $fila = $stmt->fetch();

        $stmtU = $this->db->prepare(
            'SELECT COALESCE(SUM(dp.cantidad),0)
             FROM detalle_pedidos dp
             JOIN pedidos p ON p.id = dp.pedido_id
             WHERE p.estado <> :cancelado AND p.creado_en BETWEEN :desde AND :hasta'
        );
        $stmtU->execute([
            ':cancelado' => 'cancelado',
            ':desde'     => $desde . ' 00:00:00',
            ':hasta'     => $hasta . ' 23:59:59',
        ]);

        return [
            'pedidos'    => (int) $fila['pedidos'],
            'ingresos'   => (float) $fila['ingresos'],
            'costo'      => (float) $fila['costo'],
            'envios'     => (float) $fila['envios'],
            'descuentos' => (float) $fila['descuentos'],
            'unidades'   => (int) $stmtU->fetchColumn(),
        ];
    }

    /* ═════════════════════════ SERIES ════════════════════════════ */

    /**
     * Ventas día por día, sin huecos: los días sin pedidos se devuelven
     * en cero para que la línea de la gráfica no mienta.
     */
    public function ventasPorDia(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT DATE(creado_en) AS dia,
                    COUNT(*) AS pedidos,
                    COALESCE(SUM(total),0)       AS ingresos,
                    COALESCE(SUM(costo_total),0) AS costo
             FROM pedidos
             WHERE estado <> :cancelado AND creado_en BETWEEN :desde AND :hasta
             GROUP BY DATE(creado_en)
             ORDER BY dia ASC'
        );
        $stmt->execute([
            ':cancelado' => 'cancelado',
            ':desde'     => $desde . ' 00:00:00',
            ':hasta'     => $hasta . ' 23:59:59',
        ]);

        $porDia = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porDia[substr((string) $fila['dia'], 0, 10)] = $fila;
        }

        $serie   = [];
        $cursor  = strtotime($desde);
        $final   = strtotime($hasta);

        while ($cursor <= $final) {
            $clave = date('Y-m-d', $cursor);
            $fila  = $porDia[$clave] ?? null;

            $serie[] = [
                'fecha'    => $clave,
                'etiqueta' => date('d', $cursor) . ' ' . self::MESES_CORTOS[(int) date('n', $cursor) - 1],
                'pedidos'  => (int) ($fila['pedidos'] ?? 0),
                'ingresos' => (float) ($fila['ingresos'] ?? 0),
                'costo'    => (float) ($fila['costo'] ?? 0),
                'utilidad' => (float) ($fila['ingresos'] ?? 0) - (float) ($fila['costo'] ?? 0),
            ];
            $cursor = strtotime('+1 day', $cursor);
        }

        return $serie;
    }

    private const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** Serie mensual de ingresos, costo, utilidad bruta y gastos. */
    public function ventasPorMes(int $meses = 12): array
    {
        $desde = date('Y-m-01', strtotime('-' . ($meses - 1) . ' months'));

        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(creado_en, '%Y-%m') AS mes,
                    COUNT(*) AS pedidos,
                    COALESCE(SUM(total),0)       AS ingresos,
                    COALESCE(SUM(costo_total),0) AS costo
             FROM pedidos
             WHERE estado <> :cancelado AND creado_en >= :desde
             GROUP BY mes ORDER BY mes ASC"
        );
        $stmt->execute([':cancelado' => 'cancelado', ':desde' => $desde . ' 00:00:00']);

        $porMes = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porMes[$fila['mes']] = $fila;
        }

        $gastosPorMes = (new Gasto())->porMes($meses);

        $serie = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $ts       = strtotime("-{$i} months", strtotime(date('Y-m-01')));
            $clave    = date('Y-m', $ts);
            $fila     = $porMes[$clave] ?? null;
            $ingresos = (float) ($fila['ingresos'] ?? 0);
            $costo    = (float) ($fila['costo'] ?? 0);
            $gastos   = (float) ($gastosPorMes[$clave] ?? 0);

            $serie[] = [
                'mes'      => $clave,
                'etiqueta' => self::MESES_CORTOS[(int) date('n', $ts) - 1] . ' ' . date('y', $ts),
                'pedidos'  => (int) ($fila['pedidos'] ?? 0),
                'ingresos' => $ingresos,
                'costo'    => $costo,
                'gastos'   => $gastos,
                'utilidad' => $ingresos - $costo - $gastos,
            ];
        }

        return $serie;
    }

    /* ═══════════════════════ RANKINGS ════════════════════════════ */

    public function topProductos(string $desde, string $hasta, int $limite = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT dp.producto_id, dp.nombre, dp.imagen,
                    SUM(dp.cantidad) AS unidades,
                    SUM(dp.cantidad * dp.precio) AS ingresos,
                    SUM(dp.cantidad * dp.costo)  AS costo
             FROM detalle_pedidos dp
             JOIN pedidos p ON p.id = dp.pedido_id
             WHERE p.estado <> :cancelado AND p.creado_en BETWEEN :desde AND :hasta
             GROUP BY dp.producto_id, dp.nombre, dp.imagen
             ORDER BY unidades DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':cancelado', 'cancelado');
        $stmt->bindValue(':desde', $desde . ' 00:00:00');
        $stmt->bindValue(':hasta', $hasta . ' 23:59:59');
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $f): array {
            $f['unidades'] = (int) $f['unidades'];
            $f['ingresos'] = (float) $f['ingresos'];
            $f['utilidad'] = (float) $f['ingresos'] - (float) $f['costo'];
            return $f;
        }, $stmt->fetchAll());
    }

    public function ventasPorCategoria(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(pr.categoria, :sinCat) AS categoria,
                    SUM(dp.cantidad) AS unidades,
                    SUM(dp.cantidad * dp.precio) AS ingresos
             FROM detalle_pedidos dp
             JOIN pedidos p    ON p.id = dp.pedido_id
             LEFT JOIN productos pr ON pr.id = dp.producto_id
             WHERE p.estado <> :cancelado AND p.creado_en BETWEEN :desde AND :hasta
             GROUP BY categoria
             ORDER BY ingresos DESC'
        );
        $stmt->execute([
            ':sinCat'    => 'Sin categoría',
            ':cancelado' => 'cancelado',
            ':desde'     => $desde . ' 00:00:00',
            ':hasta'     => $hasta . ' 23:59:59',
        ]);

        return array_map(static function (array $f): array {
            $f['unidades'] = (int) $f['unidades'];
            $f['ingresos'] = (float) $f['ingresos'];
            return $f;
        }, $stmt->fetchAll());
    }

    /** Distribución de pedidos por estado (dona del dashboard). */
    public function pedidosPorEstado(): array
    {
        $stmt = $this->db->query('SELECT estado, COUNT(*) AS total FROM pedidos GROUP BY estado');

        $salida = array_fill_keys(['pendiente', 'confirmado', 'procesando', 'enviado', 'entregado', 'cancelado'], 0);
        foreach ($stmt->fetchAll() as $fila) {
            $salida[$fila['estado']] = (int) $fila['total'];
        }
        return $salida;
    }

    /* ═══════════════════════ CLIENTES ════════════════════════════ */

    public function clientesNuevosPorMes(int $meses = 6): array
    {
        $desde = date('Y-m-01', strtotime('-' . ($meses - 1) . ' months'));

        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(creado_en, '%Y-%m') AS mes, COUNT(*) AS total
             FROM usuarios WHERE rol = :rol AND creado_en >= :desde
             GROUP BY mes ORDER BY mes ASC"
        );
        $stmt->execute([':rol' => 'cliente', ':desde' => $desde . ' 00:00:00']);

        $porMes = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porMes[$fila['mes']] = (int) $fila['total'];
        }

        $serie = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $ts    = strtotime("-{$i} months", strtotime(date('Y-m-01')));
            $clave = date('Y-m', $ts);
            $serie[] = [
                'mes'      => $clave,
                'etiqueta' => self::MESES_CORTOS[(int) date('n', $ts) - 1] . ' ' . date('y', $ts),
                'total'    => $porMes[$clave] ?? 0,
            ];
        }
        return $serie;
    }

    public function mejoresClientes(int $limite = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nombre, u.email,
                    COUNT(p.id) AS pedidos,
                    COALESCE(SUM(p.total),0) AS gastado
             FROM usuarios u
             JOIN pedidos p ON p.usuario_id = u.id AND p.estado <> :cancelado
             WHERE u.rol = :rol
             GROUP BY u.id, u.nombre, u.email
             ORDER BY gastado DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':cancelado', 'cancelado');
        $stmt->bindValue(':rol', 'cliente');
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /* ═══════════════════ ESTADO DE RESULTADOS ════════════════════ */

    /**
     * Estado de resultados del periodo, con la estructura contable
     * habitual:
     *
     *   Ventas brutas
     *   − Descuentos            = Ventas netas
     *   − Costo de la mercancía = Utilidad bruta
     *   − Gastos operativos     = Utilidad neta
     */
    public function estadoResultados(string $desde, string $hasta): array
    {
        $v = $this->totalesEntre($desde, $hasta);

        $gastoModelo   = new Gasto();
        $gastos        = $gastoModelo->total($desde, $hasta);
        $porCategoria  = $gastoModelo->porCategoria($desde, $hasta);

        // `total` ya viene neto de descuentos y con el envío incluido:
        // se separan para que el estado de resultados sea legible.
        $ventasBrutas  = $v['ingresos'] + $v['descuentos'] - $v['envios'];
        $ventasNetas   = $v['ingresos'] - $v['envios'];
        $utilidadBruta = $ventasNetas - $v['costo'];
        $utilidadNeta  = $utilidadBruta + $v['envios'] - $gastos;

        return [
            'periodo'          => ['desde' => $desde, 'hasta' => $hasta],
            'ventas_brutas'    => $ventasBrutas,
            'descuentos'       => $v['descuentos'],
            'ventas_netas'     => $ventasNetas,
            'costo_mercancia'  => $v['costo'],
            'utilidad_bruta'   => $utilidadBruta,
            'margen_bruto'     => $ventasNetas > 0 ? round(($utilidadBruta / $ventasNetas) * 100, 1) : 0.0,
            'ingresos_envio'   => $v['envios'],
            'gastos_totales'   => $gastos,
            'gastos_categoria' => $porCategoria,
            'utilidad_neta'    => $utilidadNeta,
            'margen_neto'      => $ventasNetas > 0 ? round(($utilidadNeta / $ventasNetas) * 100, 1) : 0.0,
            'pedidos'          => $v['pedidos'],
            'unidades'         => $v['unidades'],
        ];
    }
}
