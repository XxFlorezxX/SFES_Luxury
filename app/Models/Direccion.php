<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Direccion
 * Cada cliente guarda una dirección de envío "principal" que se
 * reutiliza para futuros pedidos (evita volver a escribirla siempre).
 */
class Direccion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    public function principalDe(int $usuarioId): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM direcciones WHERE usuario_id = :uid AND principal = 1 LIMIT 1');
        $stmt->execute([':uid' => $usuarioId]);
        return $stmt->fetch();
    }

    /** Crea o actualiza (upsert) la dirección principal del usuario. */
    public function guardar(int $usuarioId, array $datos): bool
    {
        $existente = $this->principalDe($usuarioId);

        if ($existente) {
            $stmt = $this->db->prepare(
                'UPDATE direcciones SET nombres=:nombres, telefono=:telefono, direccion=:direccion,
                     ciudad=:ciudad, especificaciones=:especificaciones
                 WHERE id = :id'
            );
            return $stmt->execute([
                ':nombres'          => $datos['nombres'],
                ':telefono'         => $datos['telefono'],
                ':direccion'        => $datos['direccion'],
                ':ciudad'           => $datos['ciudad'] ?? '',
                ':especificaciones' => $datos['especificaciones'] ?? null,
                ':id'               => $existente['id'],
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO direcciones (usuario_id, nombres, telefono, direccion, ciudad, especificaciones, principal)
             VALUES (:uid, :nombres, :telefono, :direccion, :ciudad, :especificaciones, 1)'
        );
        return $stmt->execute([
            ':uid'              => $usuarioId,
            ':nombres'          => $datos['nombres'],
            ':telefono'         => $datos['telefono'],
            ':direccion'        => $datos['direccion'],
            ':ciudad'           => $datos['ciudad'] ?? '',
            ':especificaciones' => $datos['especificaciones'] ?? null,
        ]);
    }
}
