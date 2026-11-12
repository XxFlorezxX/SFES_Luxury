<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Configuracion
 * Ajustes editables de la tienda (nombre, contacto, umbral de envío
 * gratis…) guardados como pares clave/valor. Evita tener que tocar
 * código para cambiar un dato del negocio.
 */
class Configuracion
{
    private PDO $db;
    private static array $cache = [];

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    public function obtener(string $clave, string $porDefecto = ''): string
    {
        if (array_key_exists($clave, self::$cache)) {
            return self::$cache[$clave];
        }

        try {
            $stmt = $this->db->prepare('SELECT valor FROM configuracion WHERE clave = :clave LIMIT 1');
            $stmt->execute([':clave' => $clave]);
            $valor = $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[Configuracion] ' . $e->getMessage());
            $valor = false;
        }

        return self::$cache[$clave] = ($valor === false || $valor === null ? $porDefecto : (string) $valor);
    }

    public function todas(): array
    {
        $salida = [];
        foreach ($this->db->query('SELECT clave, valor FROM configuracion')->fetchAll() as $fila) {
            $salida[$fila['clave']] = $fila['valor'];
        }
        return $salida;
    }

    public function guardar(string $clave, string $valor): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM configuracion WHERE clave = :clave LIMIT 1');
        $stmt->execute([':clave' => $clave]);

        if ($stmt->fetchColumn()) {
            $upd = $this->db->prepare('UPDATE configuracion SET valor = :valor WHERE clave = :clave');
            $ok  = $upd->execute([':clave' => $clave, ':valor' => $valor]);
        } else {
            $ins = $this->db->prepare('INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor)');
            $ok  = $ins->execute([':clave' => $clave, ':valor' => $valor]);
        }

        self::$cache[$clave] = $valor;
        return $ok;
    }
}
