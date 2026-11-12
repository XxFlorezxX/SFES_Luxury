<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Favorito
 * Lista de deseos del cliente. Persiste en base de datos (no en el
 * navegador), así el cliente ve sus piezas guardadas desde cualquier
 * dispositivo donde inicie sesión.
 */
class Favorito
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    /** Agrega o quita según el estado actual. Devuelve true si quedó guardado. */
    public function alternar(int $usuarioId, int $productoId): bool
    {
        if ($this->existe($usuarioId, $productoId)) {
            $stmt = $this->db->prepare('DELETE FROM favoritos WHERE usuario_id = :uid AND producto_id = :pid');
            $stmt->execute([':uid' => $usuarioId, ':pid' => $productoId]);
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO favoritos (usuario_id, producto_id, creado_en) VALUES (:uid, :pid, NOW())'
        );
        $stmt->execute([':uid' => $usuarioId, ':pid' => $productoId]);
        return true;
    }

    public function existe(int $usuarioId, int $productoId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM favoritos WHERE usuario_id = :uid AND producto_id = :pid LIMIT 1');
        $stmt->execute([':uid' => $usuarioId, ':pid' => $productoId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Piezas guardadas por el cliente, con sus datos completos. */
    public function deUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, f.creado_en AS guardado_en
             FROM favoritos f
             JOIN productos p ON p.id = f.producto_id
             WHERE f.usuario_id = :uid AND p.activo = 1
             ORDER BY f.creado_en DESC'
        );
        $stmt->execute([':uid' => $usuarioId]);
        return $stmt->fetchAll();
    }

    /** IDs guardados — para pintar el corazón lleno en las tarjetas del catálogo. */
    public function idsDeUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT producto_id FROM favoritos WHERE usuario_id = :uid');
        $stmt->execute([':uid' => $usuarioId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function contar(int $usuarioId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM favoritos WHERE usuario_id = :uid');
        $stmt->execute([':uid' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /** Las piezas más deseadas de la tienda (dato útil para el panel). */
    public function masDeseados(int $limite = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.nombre, p.imagen, COUNT(f.id) AS guardados
             FROM favoritos f
             JOIN productos p ON p.id = f.producto_id
             GROUP BY p.id
             ORDER BY guardados DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
