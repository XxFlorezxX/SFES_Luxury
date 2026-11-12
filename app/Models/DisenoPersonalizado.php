<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo DisenoPersonalizado
 * Guarda cada concepto de joya generado con IA (si el cliente tiene
 * sesión activa), para que pueda consultarlo más adelante.
 */
class DisenoPersonalizado
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    public function crear(?int $usuarioId, array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO disenos_personalizados
                (usuario_id, tipo, nombre_ia, metal, piedra, estilo, descripcion, prompt_imagen, imagen_url, creado_en)
             VALUES (:uid, :tipo, :nombre_ia, :metal, :piedra, :estilo, :descripcion, :prompt_imagen, :imagen_url, NOW())'
        );
        $stmt->execute([
            ':uid'           => $usuarioId,
            ':tipo'          => $datos['tipo'],
            ':nombre_ia'     => $datos['nombre_ia']     ?? null,
            ':metal'         => $datos['metal']         ?? null,
            ':piedra'        => $datos['piedra']        ?? null,
            ':estilo'        => $datos['estilo']        ?? null,
            ':descripcion'   => $datos['descripcion']   ?? null,
            ':prompt_imagen' => $datos['prompt_imagen'] ?? null,
            ':imagen_url'    => $datos['imagen_url']    ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM disenos_personalizados WHERE usuario_id = :uid ORDER BY creado_en DESC');
        $stmt->execute([':uid' => $usuarioId]);
        return $stmt->fetchAll();
    }
}
