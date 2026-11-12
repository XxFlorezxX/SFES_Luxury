<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Resena
 * Calificaciones y comentarios de los clientes sobre cada pieza.
 * Una reseña se marca como "verificada" cuando el cliente realmente
 * compró el producto (se cruza contra detalle_pedidos), que es lo que
 * le da credibilidad frente a un comentario cualquiera.
 */
class Resena
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    /** Reseñas aprobadas de un producto, con el nombre del autor. */
    public function deProducto(int $productoId, int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.nombre AS autor
             FROM resenas r
             JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.producto_id = :pid AND r.aprobada = 1
             ORDER BY r.verificada DESC, r.creado_en DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':pid', $productoId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Promedio, total y distribución de estrellas (para la barra de 5 a 1). */
    public function resumen(int $productoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total, COALESCE(AVG(calificacion),0) AS promedio
             FROM resenas WHERE producto_id = :pid AND aprobada = 1'
        );
        $stmt->execute([':pid' => $productoId]);
        $base = $stmt->fetch();

        $stmtDist = $this->db->prepare(
            'SELECT calificacion, COUNT(*) AS total
             FROM resenas WHERE producto_id = :pid AND aprobada = 1
             GROUP BY calificacion'
        );
        $stmtDist->execute([':pid' => $productoId]);

        $distribucion = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($stmtDist->fetchAll() as $fila) {
            $distribucion[(int) $fila['calificacion']] = (int) $fila['total'];
        }

        return [
            'total'        => (int) $base['total'],
            'promedio'     => round((float) $base['promedio'], 1),
            'distribucion' => $distribucion,
        ];
    }

    public function deUsuarioYProducto(int $usuarioId, int $productoId): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM resenas WHERE usuario_id = :uid AND producto_id = :pid LIMIT 1');
        $stmt->execute([':uid' => $usuarioId, ':pid' => $productoId]);
        return $stmt->fetch();
    }

    /** ¿Este cliente compró esta pieza alguna vez? */
    public function compro(int $usuarioId, int $productoId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM detalle_pedidos dp
             JOIN pedidos p ON p.id = dp.pedido_id
             WHERE p.usuario_id = :uid AND dp.producto_id = :pid AND p.estado <> :cancelado'
        );
        $stmt->execute([':uid' => $usuarioId, ':pid' => $productoId, ':cancelado' => 'cancelado']);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Crea o actualiza la reseña del cliente (una por producto). */
    public function guardar(int $usuarioId, int $productoId, int $calificacion, string $titulo, string $comentario): bool
    {
        $calificacion = max(1, min(5, $calificacion));
        $verificada   = $this->compro($usuarioId, $productoId) ? 1 : 0;
        $existente    = $this->deUsuarioYProducto($usuarioId, $productoId);

        if ($existente) {
            $stmt = $this->db->prepare(
                'UPDATE resenas
                 SET calificacion = :cal, titulo = :titulo, comentario = :comentario,
                     verificada = :verificada, creado_en = NOW()
                 WHERE id = :id'
            );
            return $stmt->execute([
                ':cal'        => $calificacion,
                ':titulo'     => $titulo ?: null,
                ':comentario' => $comentario,
                ':verificada' => $verificada,
                ':id'         => $existente['id'],
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO resenas (producto_id, usuario_id, calificacion, titulo, comentario, verificada, aprobada, creado_en)
             VALUES (:pid, :uid, :cal, :titulo, :comentario, :verificada, 1, NOW())'
        );
        return $stmt->execute([
            ':pid'        => $productoId,
            ':uid'        => $usuarioId,
            ':cal'        => $calificacion,
            ':titulo'     => $titulo ?: null,
            ':comentario' => $comentario,
            ':verificada' => $verificada,
        ]);
    }

    /** Últimas reseñas del sitio (para el panel de administración). */
    public function ultimas(int $limite = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.nombre AS autor, p.nombre AS producto
             FROM resenas r
             JOIN usuarios u  ON u.id = r.usuario_id
             JOIN productos p ON p.id = r.producto_id
             ORDER BY r.creado_en DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function calificacionPromedioTienda(): float
    {
        return round((float) $this->db
            ->query('SELECT COALESCE(AVG(calificacion),0) FROM resenas WHERE aprobada = 1')
            ->fetchColumn(), 1);
    }
}
