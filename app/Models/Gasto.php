<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Gasto
 * Egresos operativos del negocio. Junto con el costo de la mercancía
 * vendida (que guarda cada pedido) permiten armar un estado de
 * resultados real, no solo un conteo de ventas.
 */
class Gasto
{
    public const CATEGORIAS = [
        'inventario' => 'Compra de inventario',
        'nomina'     => 'Nómina y honorarios',
        'marketing'  => 'Marketing y pauta',
        'logistica'  => 'Logística y envíos',
        'servicios'  => 'Servicios y arriendo',
        'impuestos'  => 'Impuestos',
        'otros'      => 'Otros',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    public function listar(string $desde, string $hasta, string $categoria = ''): array
    {
        $sql    = 'SELECT * FROM gastos WHERE fecha BETWEEN :desde AND :hasta';
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($categoria !== '' && isset(self::CATEGORIAS[$categoria])) {
            $sql .= ' AND categoria = :categoria';
            $params[':categoria'] = $categoria;
        }

        $sql .= ' ORDER BY fecha DESC, id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function total(string $desde, string $hasta): float
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(monto),0) FROM gastos WHERE fecha BETWEEN :desde AND :hasta');
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return (float) $stmt->fetchColumn();
    }

    /** Total por categoría — alimenta la dona del módulo contable. */
    public function porCategoria(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT categoria, COALESCE(SUM(monto),0) AS total, COUNT(*) AS registros
             FROM gastos WHERE fecha BETWEEN :desde AND :hasta
             GROUP BY categoria ORDER BY total DESC'
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        return array_map(static function (array $fila): array {
            $fila['etiqueta'] = self::CATEGORIAS[$fila['categoria']] ?? ucfirst($fila['categoria']);
            $fila['total']    = (float) $fila['total'];
            return $fila;
        }, $stmt->fetchAll());
    }

    /** Serie mensual de gastos, para comparar contra los ingresos. */
    public function porMes(int $meses = 12): array
    {
        $desde = date('Y-m-01', strtotime('-' . ($meses - 1) . ' months'));

        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, COALESCE(SUM(monto),0) AS total
             FROM gastos WHERE fecha >= :desde
             GROUP BY mes ORDER BY mes ASC"
        );
        $stmt->execute([':desde' => $desde]);

        $salida = [];
        foreach ($stmt->fetchAll() as $fila) {
            $salida[$fila['mes']] = (float) $fila['total'];
        }
        return $salida;
    }

    public function crear(array $d, ?int $usuarioId = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gastos (concepto, categoria, monto, fecha, nota, usuario_id, creado_en)
             VALUES (:concepto, :categoria, :monto, :fecha, :nota, :usuario, NOW())'
        );
        $stmt->execute([
            ':concepto'  => $d['concepto'],
            ':categoria' => isset(self::CATEGORIAS[$d['categoria']]) ? $d['categoria'] : 'otros',
            ':monto'     => $d['monto'],
            ':fecha'     => $d['fecha'],
            ':nota'      => $d['nota'] ?: null,
            ':usuario'   => $usuarioId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM gastos WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
