<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Usuario
 * Administradores y clientes viven en la misma tabla, separados por el
 * campo `rol`. Las contraseñas se guardan siempre con password_hash()
 * (bcrypt); en ninguna parte del sistema existe una contraseña en texto
 * plano ni un usuario "quemado" en el código.
 */
class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    /* ═════════════════════════ CONSULTAS ═════════════════════════ */

    public function buscarPorEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function correoExiste(string $email, int $excluirId = 0): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM usuarios WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute([':email' => $email, ':id' => $excluirId]);
        return (bool) $stmt->fetchColumn();
    }

    /* ═══════════════════════ AUTENTICACIÓN ═══════════════════════ */

    public function crear(string $nombre, string $email, string $password, string $rol = 'cliente', string $telefono = ''): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, telefono, rol, creado_en)
             VALUES (:nombre, :email, :hash, :telefono, :rol, NOW())'
        );
        $stmt->execute([
            ':nombre'   => $nombre,
            ':email'    => $email,
            ':hash'     => password_hash($password, PASSWORD_BCRYPT),
            ':telefono' => $telefono ?: null,
            ':rol'      => $rol === 'admin' ? 'admin' : 'cliente',
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Verifica credenciales. Devuelve el usuario o false.
     * Si el hash quedó desactualizado (por un cambio del algoritmo por
     * defecto de PHP) se regenera de forma transparente.
     */
    public function verificarCredenciales(string $email, string $password): array|false
    {
        $usuario = $this->buscarPorEmail($email);

        if (!$usuario || (int) $usuario['activo'] !== 1) {
            // Se ejecuta igual una verificación falsa para que el tiempo
            // de respuesta no revele si el correo existe o no.
            password_verify($password, '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidi');
            return false;
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            return false;
        }

        if (password_needs_rehash($usuario['password_hash'], PASSWORD_BCRYPT)) {
            $this->cambiarPassword((int) $usuario['id'], $password);
        }

        return $usuario;
    }

    public function cambiarPassword(int $id, string $nueva): bool
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
        return $stmt->execute([':hash' => password_hash($nueva, PASSWORD_BCRYPT), ':id' => $id]);
    }

    public function registrarAcceso(int $id): void
    {
        $this->db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id')->execute([':id' => $id]);
    }

    public function actualizarPerfil(int $id, string $nombre, string $email, string $telefono): bool
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, telefono = :telefono WHERE id = :id');
        return $stmt->execute([
            ':nombre'   => $nombre,
            ':email'    => $email,
            ':telefono' => $telefono ?: null,
            ':id'       => $id,
        ]);
    }

    /* ═══════════════════════ ADMINISTRACIÓN ══════════════════════ */

    public function contarClientes(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = :rol');
        $stmt->execute([':rol' => 'cliente']);
        return (int) $stmt->fetchColumn();
    }

    public function clientesNuevos(int $dias = 30): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = :rol AND creado_en >= :desde');
        $stmt->execute([':rol' => 'cliente', ':desde' => date('Y-m-d 00:00:00', strtotime("-{$dias} days"))]);
        return (int) $stmt->fetchColumn();
    }

    /** Listado paginado de clientes con su resumen de compras. */
    public function listarClientes(int $pagina = 1, int $porPagina = 20, string $busqueda = ''): array
    {
        $pagina    = max(1, $pagina);
        $porPagina = min(100, max(5, $porPagina));
        $offset    = ($pagina - 1) * $porPagina;

        $where  = 'u.rol = :rol';
        $params = [':rol' => 'cliente'];

        if ($busqueda !== '') {
            $where .= ' AND (u.nombre LIKE :q1 OR u.email LIKE :q2)';
            $params[':q1'] = $params[':q2'] = '%' . $busqueda . '%';
        }

        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM usuarios u WHERE {$where}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT u.id, u.nombre, u.email, u.telefono, u.activo, u.creado_en, u.ultimo_acceso,
                    COUNT(p.id) AS total_pedidos,
                    COALESCE(SUM(p.total), 0) AS total_gastado
             FROM usuarios u
             LEFT JOIN pedidos p ON p.usuario_id = u.id AND p.estado <> 'cancelado'
             WHERE {$where}
             GROUP BY u.id, u.nombre, u.email, u.telefono, u.activo, u.creado_en, u.ultimo_acceso
             ORDER BY u.creado_en DESC
             LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'usuarios'  => $stmt->fetchAll(),
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => max(1, (int) ceil($total / $porPagina)),
            'porPagina' => $porPagina,
        ];
    }

    /**
     * Ficha de cliente para el panel.
     * Se devuelven solo los datos necesarios para atenderlo: nunca el
     * hash de la contraseña ni campos internos.
     */
    public function fichaCliente(int $id): array|false
    {
        $usuario = $this->buscarPorId($id);
        if (!$usuario || $usuario['rol'] !== 'cliente') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total_pedidos, COALESCE(SUM(total),0) AS total_gastado
             FROM pedidos WHERE usuario_id = :id AND estado <> :cancelado'
        );
        $stmt->execute([':id' => $id, ':cancelado' => 'cancelado']);
        $resumen = $stmt->fetch();

        $stmtPed = $this->db->prepare(
            'SELECT id, codigo, total, estado, creado_en FROM pedidos
             WHERE usuario_id = :id ORDER BY creado_en DESC LIMIT 8'
        );
        $stmtPed->execute([':id' => $id]);

        $stmtDir = $this->db->prepare('SELECT ciudad, direccion, telefono FROM direcciones WHERE usuario_id = :id AND principal = 1 LIMIT 1');
        $stmtDir->execute([':id' => $id]);

        return [
            'usuario' => [
                'id'            => (int) $usuario['id'],
                'nombre'        => $usuario['nombre'],
                'email'         => $usuario['email'],
                'telefono'      => $usuario['telefono'],
                'creado_en'     => $usuario['creado_en'],
                'ultimo_acceso' => $usuario['ultimo_acceso'],
            ],
            'resumen' => [
                'total_pedidos' => (int) $resumen['total_pedidos'],
                'total_gastado' => (float) $resumen['total_gastado'],
                'ticket'        => (int) $resumen['total_pedidos'] > 0
                    ? (float) $resumen['total_gastado'] / (int) $resumen['total_pedidos']
                    : 0.0,
            ],
            'pedidos'   => $stmtPed->fetchAll(),
            'direccion' => $stmtDir->fetch() ?: null,
        ];
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id AND rol = :rol');
        return $stmt->execute([':activo' => $activo ? 1 : 0, ':id' => $id, ':rol' => 'cliente']);
    }
}
