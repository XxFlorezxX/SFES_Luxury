<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Inventario (kardex)
 * Registra toda variación de stock: venta, cancelación, reposición y
 * ajuste manual. Antes el stock se editaba sin dejar rastro; ahora cada
 * cambio queda auditado con motivo, fecha y responsable.
 *
 * Importante: `descontar()` y `devolver()` NO abren transacción propia
 * — se llaman desde dentro de la transacción del pedido, para que stock
 * y pedido se confirmen o se deshagan juntos.
 */
class Inventario
{
    public const TIPOS = ['entrada', 'salida', 'ajuste'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    /**
     * Descuenta unidades por una venta. Bloquea la fila y verifica que
     * haya existencias suficientes: si dos clientes compran la última
     * pieza a la vez, solo una prospera.
     *
     * @throws RuntimeException si no hay stock suficiente
     */
    public function descontar(int $productoId, int $cantidad, ?int $pedidoId = null, string $motivo = 'Venta'): void
    {
        $bloqueo = Database::motor() === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare('SELECT nombre, stock FROM productos WHERE id = :id' . $bloqueo);
        $stmt->execute([':id' => $productoId]);
        $producto = $stmt->fetch();

        if (!$producto) {
            throw new RuntimeException('El producto ya no está disponible.');
        }

        $stockActual = (int) $producto['stock'];

        if ($stockActual < $cantidad) {
            throw new RuntimeException(
                sprintf('Solo quedan %d unidad(es) de "%s".', $stockActual, $producto['nombre'])
            );
        }

        $nuevo = $stockActual - $cantidad;
        $this->db->prepare('UPDATE productos SET stock = :stock WHERE id = :id')
                 ->execute([':stock' => $nuevo, ':id' => $productoId]);

        $this->registrar($productoId, 'salida', $cantidad, $nuevo, $motivo, $pedidoId);
    }

    /** Devuelve unidades al inventario (cancelación de pedido). */
    public function devolver(int $productoId, int $cantidad, ?int $pedidoId = null, string $motivo = 'Pedido cancelado'): void
    {
        $stmt = $this->db->prepare('SELECT stock FROM productos WHERE id = :id');
        $stmt->execute([':id' => $productoId]);
        $stockActual = $stmt->fetchColumn();

        if ($stockActual === false) {
            return; // el producto fue eliminado: no hay a dónde devolver
        }

        $nuevo = (int) $stockActual + $cantidad;
        $this->db->prepare('UPDATE productos SET stock = :stock WHERE id = :id')
                 ->execute([':stock' => $nuevo, ':id' => $productoId]);

        $this->registrar($productoId, 'entrada', $cantidad, $nuevo, $motivo, $pedidoId);
    }

    /** Ajuste manual desde el panel (reposición, merma, corrección de conteo). */
    public function ajustar(int $productoId, int $nuevoStock, string $motivo, ?int $usuarioId = null): array
    {
        $stmt = $this->db->prepare('SELECT nombre, stock FROM productos WHERE id = :id');
        $stmt->execute([':id' => $productoId]);
        $producto = $stmt->fetch();

        if (!$producto) {
            return ['ok' => false, 'mensaje' => 'El producto no existe.'];
        }

        $nuevoStock = max(0, $nuevoStock);
        $anterior   = (int) $producto['stock'];
        $diferencia = $nuevoStock - $anterior;

        if ($diferencia === 0) {
            return ['ok' => false, 'mensaje' => 'El stock ya tenía ese valor.'];
        }

        $this->db->prepare('UPDATE productos SET stock = :stock WHERE id = :id')
                 ->execute([':stock' => $nuevoStock, ':id' => $productoId]);

        $this->registrar(
            $productoId,
            $diferencia > 0 ? 'entrada' : 'salida',
            abs($diferencia),
            $nuevoStock,
            $motivo !== '' ? $motivo : 'Ajuste manual de inventario',
            null,
            $usuarioId
        );

        return [
            'ok'      => true,
            'mensaje' => sprintf('Stock de "%s" ajustado de %d a %d.', $producto['nombre'], $anterior, $nuevoStock),
            'stock'   => $nuevoStock,
        ];
    }

    /** Deja la entrada en el kardex. */
    public function registrar(
        int $productoId,
        string $tipo,
        int $cantidad,
        int $stockResultante,
        string $motivo,
        ?int $pedidoId = null,
        ?int $usuarioId = null
    ): void {
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'ajuste';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO movimientos_inventario
                (producto_id, tipo, cantidad, stock_resultante, motivo, pedido_id, usuario_id, creado_en)
             VALUES (:pid, :tipo, :cantidad, :stock, :motivo, :pedido, :usuario, NOW())'
        );
        $stmt->execute([
            ':pid'      => $productoId,
            ':tipo'     => $tipo,
            ':cantidad' => $cantidad,
            ':stock'    => $stockResultante,
            ':motivo'   => mb_substr($motivo, 0, 120),
            ':pedido'   => $pedidoId,
            ':usuario'  => $usuarioId,
        ]);
    }

    /** Historial de movimientos de una pieza. */
    public function kardex(int $productoId, int $limite = 40): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM movimientos_inventario
             WHERE producto_id = :pid
             ORDER BY creado_en DESC, id DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':pid', $productoId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Últimos movimientos de toda la tienda. */
    public function ultimos(int $limite = 15): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, p.nombre AS producto, p.imagen
             FROM movimientos_inventario m
             JOIN productos p ON p.id = m.producto_id
             ORDER BY m.creado_en DESC, m.id DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Unidades que entraron y salieron en un rango (para la gráfica). */
    public function flujoPorDia(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(creado_en) AS dia,
                    SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END) AS entradas,
                    SUM(CASE WHEN tipo = 'salida'  THEN cantidad ELSE 0 END) AS salidas
             FROM movimientos_inventario
             WHERE creado_en BETWEEN :desde AND :hasta
             GROUP BY DATE(creado_en)
             ORDER BY dia ASC"
        );
        $stmt->execute([':desde' => $desde . ' 00:00:00', ':hasta' => $hasta . ' 23:59:59']);
        return $stmt->fetchAll();
    }
}
