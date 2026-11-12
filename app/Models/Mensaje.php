<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Mensaje
 * Mensajes recibidos desde el formulario de contacto. El administrador
 * los revisa desde el panel y puede marcarlos como leídos.
 */
class Mensaje
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    public function crear(string $nombre, string $email, string $mensaje, string $asunto = ''): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje, creado_en)
             VALUES (:nombre, :email, :asunto, :mensaje, NOW())'
        );
        $stmt->execute([
            ':nombre'  => $nombre,
            ':email'   => $email,
            ':asunto'  => $asunto ?: null,
            ':mensaje' => $mensaje,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function obtenerTodos(int $limite = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM mensajes_contacto ORDER BY leido ASC, creado_en DESC LIMIT :limite');
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function contarSinLeer(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0')->fetchColumn();
    }

    public function marcarLeido(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE mensajes_contacto SET leido = 1 WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
