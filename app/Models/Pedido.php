<?php
require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/Inventario.php';

/**
 * Modelo Pedido
 *
 * Todo el checkout ocurre dentro de UNA transacción: se crea el pedido,
 * se guardan sus líneas y se descuenta el inventario. Si algo falla —por
 * ejemplo, que otro cliente se llevó la última unidad un segundo antes—
 * se deshace completo y no queda ni un pedido a medias ni stock mal
 * descontado.
 *
 * Los precios y costos JAMÁS vienen del navegador: el controlador los
 * resuelve desde la base de datos antes de llamar a crear().
 */
class Pedido
{
    public const ESTADOS = ['pendiente', 'confirmado', 'procesando', 'enviado', 'entregado', 'cancelado'];

    /** Transiciones que devuelven mercancía al inventario. */
    private const ESTADO_CANCELADO = 'cancelado';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    /* ══════════════════════════ CHECKOUT ═════════════════════════ */

    /**
     * Crea el pedido con su detalle y descuenta el inventario.
     *
     * @param array $cliente  nombres, email, telefono, direccion, ciudad, especificaciones, metodo_pago
     * @param array $items    [['producto_id','nombre','precio','costo','cantidad','imagen'], ...]
     * @param array $importes ['subtotal','descuento','cupon_codigo','envio','total']
     *
     * @throws RuntimeException si algún producto se quedó sin stock
     */
    public function crear(array $cliente, array $items, ?int $usuarioId, array $importes): int
    {
        $inventario = new Inventario();
        $costoTotal = 0.0;
        foreach ($items as $item) {
            $costoTotal += (float) $item['costo'] * (int) $item['cantidad'];
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO pedidos
                    (usuario_id, nombres, email, telefono, direccion, ciudad, especificaciones,
                     subtotal, descuento, cupon_codigo, envio, total, costo_total, metodo_pago, estado, creado_en)
                 VALUES
                    (:usuario_id, :nombres, :email, :telefono, :direccion, :ciudad, :especificaciones,
                     :subtotal, :descuento, :cupon, :envio, :total, :costo_total, :metodo_pago, :estado, NOW())'
            );
            $stmt->execute([
                ':usuario_id'       => $usuarioId,
                ':nombres'          => $cliente['nombres'],
                ':email'            => $cliente['email'] ?? null,
                ':telefono'         => $cliente['telefono'],
                ':direccion'        => $cliente['direccion'],
                ':ciudad'           => $cliente['ciudad'] ?? '',
                ':especificaciones' => $cliente['especificaciones'] ?? null,
                ':subtotal'         => $importes['subtotal'],
                ':descuento'        => $importes['descuento'],
                ':cupon'            => $importes['cupon_codigo'] ?: null,
                ':envio'            => $importes['envio'],
                ':total'            => $importes['total'],
                ':costo_total'      => $costoTotal,
                ':metodo_pago'      => $cliente['metodo_pago'] ?? 'contraentrega',
                ':estado'           => 'pendiente',
            ]);

            $pedidoId = (int) $this->db->lastInsertId();

            $this->db->prepare('UPDATE pedidos SET codigo = :codigo WHERE id = :id')
                     ->execute([':codigo' => 'SFS-' . str_pad((string) $pedidoId, 5, '0', STR_PAD_LEFT), ':id' => $pedidoId]);

            $stmtItem = $this->db->prepare(
                'INSERT INTO detalle_pedidos (pedido_id, producto_id, nombre, precio, costo, cantidad, imagen)
                 VALUES (:pedido_id, :producto_id, :nombre, :precio, :costo, :cantidad, :imagen)'
            );

            foreach ($items as $item) {
                $stmtItem->execute([
                    ':pedido_id'   => $pedidoId,
                    ':producto_id' => $item['producto_id'],
                    ':nombre'      => $item['nombre'],
                    ':precio'      => $item['precio'],
                    ':costo'       => $item['costo'],
                    ':cantidad'    => $item['cantidad'],
                    ':imagen'      => $item['imagen'] ?? null,
                ]);

                // Descuenta stock dentro de la misma transacción.
                $inventario->descontar(
                    (int) $item['producto_id'],
                    (int) $item['cantidad'],
                    $pedidoId,
                    'Venta pedido #' . $pedidoId
                );
            }

            $this->db->commit();
            return $pedidoId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /* ══════════════════════════ CONSULTAS ════════════════════════ */

    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /** Pedido de un cliente concreto (evita que alguien vea pedidos ajenos). */
    public function buscarDeUsuario(int $id, int $usuarioId): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM pedidos WHERE id = :id AND usuario_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $usuarioId]);
        return $stmt->fetch();
    }

    public function items(int $pedidoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM detalle_pedidos WHERE pedido_id = :id ORDER BY id ASC');
        $stmt->execute([':id' => $pedidoId]);
        return $stmt->fetchAll();
    }

    /** Resumen de líneas en texto ("Anillo x2 | Aretes x1"), armado en PHP. */
    public function resumenItems(int $pedidoId, int $maximo = 3): string
    {
        $items  = $this->items($pedidoId);
        $partes = [];
        foreach (array_slice($items, 0, $maximo) as $item) {
            $partes[] = $item['nombre'] . ' x' . $item['cantidad'];
        }
        if (count($items) > $maximo) {
            $partes[] = '+' . (count($items) - $maximo) . ' más';
        }
        return implode(' · ', $partes);
    }

    public function deUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pedidos WHERE usuario_id = :uid ORDER BY creado_en DESC');
        $stmt->execute([':uid' => $usuarioId]);
        return $stmt->fetchAll();
    }

    /** Listado paginado para el panel, con filtro por estado y búsqueda. */
    public function listar(int $pagina = 1, int $porPagina = 15, string $estado = '', string $busqueda = ''): array
    {
        $pagina    = max(1, $pagina);
        $porPagina = min(100, max(5, $porPagina));
        $offset    = ($pagina - 1) * $porPagina;

        $where  = ['1=1'];
        $params = [];

        if ($estado !== '' && in_array($estado, self::ESTADOS, true)) {
            $where[] = 'p.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($busqueda !== '') {
            $where[] = '(p.nombres LIKE :q1 OR p.email LIKE :q2 OR p.codigo LIKE :q3 OR p.telefono LIKE :q4)';
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = $like;
        }

        $sqlWhere = implode(' AND ', $where);

        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM pedidos p WHERE {$sqlWhere}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM detalle_pedidos dp WHERE dp.pedido_id = p.id) AS lineas,
                    (SELECT COALESCE(SUM(dp.cantidad),0) FROM detalle_pedidos dp WHERE dp.pedido_id = p.id) AS unidades
             FROM pedidos p
             WHERE {$sqlWhere}
             ORDER BY p.creado_en DESC, p.id DESC
             LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'pedidos'   => $stmt->fetchAll(),
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => max(1, (int) ceil($total / $porPagina)),
            'porPagina' => $porPagina,
        ];
    }

    /* ═══════════════════════ CAMBIO DE ESTADO ════════════════════ */

    /**
     * Cambia el estado del pedido. Si pasa a "cancelado", devuelve la
     * mercancía al inventario; si se reactiva un pedido cancelado, la
     * vuelve a descontar. Todo dentro de una transacción.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public function actualizarEstado(int $id, string $estado, string $notas): array
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return ['ok' => false, 'mensaje' => 'Estado no válido.'];
        }

        $pedido = $this->buscarPorId($id);
        if (!$pedido) {
            return ['ok' => false, 'mensaje' => 'El pedido no existe.'];
        }

        $anterior = $pedido['estado'];
        if ($anterior === $estado) {
            $this->db->prepare('UPDATE pedidos SET notas_admin = :notas WHERE id = :id')
                     ->execute([':notas' => $notas, ':id' => $id]);
            return ['ok' => true, 'mensaje' => 'Notas actualizadas.'];
        }

        $inventario = new Inventario();
        $this->db->beginTransaction();

        try {
            if ($estado === self::ESTADO_CANCELADO && $anterior !== self::ESTADO_CANCELADO) {
                foreach ($this->items($id) as $item) {
                    $inventario->devolver((int) $item['producto_id'], (int) $item['cantidad'], $id, 'Cancelación pedido #' . $id);
                }
            } elseif ($anterior === self::ESTADO_CANCELADO && $estado !== self::ESTADO_CANCELADO) {
                foreach ($this->items($id) as $item) {
                    $inventario->descontar((int) $item['producto_id'], (int) $item['cantidad'], $id, 'Reactivación pedido #' . $id);
                }
            }

            $this->db->prepare('UPDATE pedidos SET estado = :estado, notas_admin = :notas WHERE id = :id')
                     ->execute([':estado' => $estado, ':notas' => $notas, ':id' => $id]);

            $this->db->commit();

            return ['ok' => true, 'mensaje' => 'Pedido actualizado a "' . estado_pedido_texto($estado) . '".'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[Pedido::actualizarEstado] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /* ══════════════════════ MÉTRICAS RÁPIDAS ═════════════════════ */

    public function contarTotal(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
    }

    public function ingresosTotales(): float
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado <> :cancelado');
        $stmt->execute([':cancelado' => 'cancelado']);
        return (float) $stmt->fetchColumn();
    }

    public function ingresosDelMes(): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(total),0) FROM pedidos
             WHERE estado <> :cancelado AND creado_en >= :desde'
        );
        $stmt->execute([':cancelado' => 'cancelado', ':desde' => date('Y-m-01') . ' 00:00:00']);
        return (float) $stmt->fetchColumn();
    }

    public function contarPorEstado(): array
    {
        $salida = array_fill_keys(self::ESTADOS, 0);
        foreach ($this->db->query('SELECT estado, COUNT(*) AS c FROM pedidos GROUP BY estado')->fetchAll() as $fila) {
            $salida[$fila['estado']] = (int) $fila['c'];
        }
        return $salida;
    }

    public function ultimos(int $n = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, codigo, nombres, total, estado, creado_en
             FROM pedidos ORDER BY creado_en DESC, id DESC LIMIT :n'
        );
        $stmt->bindValue(':n', $n, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
