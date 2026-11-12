<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Cupon
 * Códigos de descuento. La validación vive aquí, del lado del servidor:
 * el navegador solo envía el código escrito, nunca el monto del
 * descuento. Así nadie puede manipular cuánto se le descuenta.
 */
class Cupon
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    public function buscarPorCodigo(string $codigo): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM cupones WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => strtoupper(trim($codigo))]);
        return $stmt->fetch();
    }

    /**
     * Valida un cupón contra el subtotal del carrito.
     *
     * @return array{ok:bool, mensaje:string, cupon?:array, descuento?:float}
     */
    public function validar(string $codigo, float $subtotal): array
    {
        $cupon = $this->buscarPorCodigo($codigo);

        if (!$cupon) {
            return ['ok' => false, 'mensaje' => 'Ese código no existe.'];
        }

        if ((int) $cupon['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'Este cupón ya no está disponible.'];
        }

        $hoy = date('Y-m-d');

        if (!empty($cupon['inicia_en']) && $hoy < $cupon['inicia_en']) {
            return ['ok' => false, 'mensaje' => 'Este cupón aún no está vigente.'];
        }

        if (!empty($cupon['vence_en']) && $hoy > $cupon['vence_en']) {
            return ['ok' => false, 'mensaje' => 'Este cupón ya venció.'];
        }

        if ($cupon['usos_maximos'] !== null && (int) $cupon['usos_actuales'] >= (int) $cupon['usos_maximos']) {
            return ['ok' => false, 'mensaje' => 'Este cupón alcanzó su límite de usos.'];
        }

        if ($subtotal < (float) $cupon['minimo_compra']) {
            return [
                'ok'      => false,
                'mensaje' => 'Este cupón aplica desde ' . moneda($cupon['minimo_compra']) . ' de compra.',
            ];
        }

        return [
            'ok'        => true,
            'mensaje'   => 'Cupón aplicado: ' . $cupon['codigo'],
            'cupon'     => $cupon,
            'descuento' => $this->calcularDescuento($cupon, $subtotal),
        ];
    }

    /** Calcula el descuento en pesos; nunca puede superar el subtotal. */
    public function calcularDescuento(array $cupon, float $subtotal): float
    {
        $descuento = $cupon['tipo'] === 'porcentaje'
            ? $subtotal * ((float) $cupon['valor'] / 100)
            : (float) $cupon['valor'];

        return round(min($descuento, $subtotal), 2);
    }

    public function registrarUso(string $codigo): void
    {
        $stmt = $this->db->prepare('UPDATE cupones SET usos_actuales = usos_actuales + 1 WHERE codigo = :codigo');
        $stmt->execute([':codigo' => strtoupper(trim($codigo))]);
    }

    /* ═══════════════════════ ADMINISTRACIÓN ══════════════════════ */

    public function todos(): array
    {
        return $this->db->query('SELECT * FROM cupones ORDER BY activo DESC, creado_en DESC')->fetchAll();
    }

    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM cupones WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function guardar(int $id, array $d): bool
    {
        if ($id > 0) {
            $stmt = $this->db->prepare(
                'UPDATE cupones SET codigo=:codigo, descripcion=:descripcion, tipo=:tipo, valor=:valor,
                        minimo_compra=:minimo, usos_maximos=:usos_max, inicia_en=:inicia, vence_en=:vence, activo=:activo
                 WHERE id=:id'
            );
            return $stmt->execute([
                ':id'          => $id,
                ':codigo'      => $d['codigo'],
                ':descripcion' => $d['descripcion'] ?: null,
                ':tipo'        => $d['tipo'],
                ':valor'       => $d['valor'],
                ':minimo'      => $d['minimo_compra'],
                ':usos_max'    => $d['usos_maximos'] ?: null,
                ':inicia'      => $d['inicia_en'] ?: null,
                ':vence'       => $d['vence_en'] ?: null,
                ':activo'      => $d['activo'],
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO cupones (codigo, descripcion, tipo, valor, minimo_compra, usos_maximos, inicia_en, vence_en, activo, creado_en)
             VALUES (:codigo, :descripcion, :tipo, :valor, :minimo, :usos_max, :inicia, :vence, :activo, NOW())'
        );
        return $stmt->execute([
            ':codigo'      => $d['codigo'],
            ':descripcion' => $d['descripcion'] ?: null,
            ':tipo'        => $d['tipo'],
            ':valor'       => $d['valor'],
            ':minimo'      => $d['minimo_compra'],
            ':usos_max'    => $d['usos_maximos'] ?: null,
            ':inicia'      => $d['inicia_en'] ?: null,
            ':vence'       => $d['vence_en'] ?: null,
            ':activo'      => $d['activo'],
        ]);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM cupones WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /** Descuento total otorgado — entra al estado de resultados. */
    public function descuentoOtorgado(?string $desde = null, ?string $hasta = null): float
    {
        $sql    = 'SELECT COALESCE(SUM(descuento),0) FROM pedidos WHERE estado <> :cancelado';
        $params = [':cancelado' => 'cancelado'];

        if ($desde) {
            $sql .= ' AND creado_en >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta) {
            $sql .= ' AND creado_en <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}
