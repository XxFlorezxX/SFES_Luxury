<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Seguridad.php';
require_once __DIR__ . '/../Models/Usuario.php';

/**
 * AuthController
 * Maneja dos identidades de sesión independientes:
 *   $_SESSION['admin_id']   → acceso al panel /admin/*
 *   $_SESSION['cliente_id'] → cuenta, pedidos y lista de deseos
 *
 * Medidas aplicadas en todos los formularios de acceso:
 *  · contraseñas verificadas contra bcrypt (nunca texto plano),
 *  · token CSRF obligatorio,
 *  · identificador de sesión renovado al iniciar y cerrar sesión,
 *  · bloqueo temporal tras varios intentos fallidos (fuerza bruta),
 *  · mensajes de error genéricos: no se revela si un correo existe.
 */
class AuthController extends Controller
{
    /* ══════════════════════ ADMINISTRADOR ══════════════════════ */

    public function login(): void
    {
        if ($this->adminId()) {
            $this->redireccionar('admin/panel');
            return;
        }

        $this->vista('auth/login', [
            'titulo' => 'Acceso administrador | ' . TIENDA_NOMBRE,
            'error'  => null,
        ], false);
    }

    public function procesarLogin(): void
    {
        if (!$this->validarCsrf($_POST['csrf_token'] ?? null)) {
            $this->mostrarLogin('La sesión del formulario expiró. Intenta de nuevo.');
            return;
        }

        $email    = $this->texto($_POST, 'usuario', 150);
        $password = (string) ($_POST['clave'] ?? '');

        $bloqueo = Seguridad::minutosBloqueo($email);
        if ($bloqueo > 0) {
            $this->mostrarLogin("Demasiados intentos fallidos. Espera {$bloqueo} minuto(s) antes de reintentar.");
            return;
        }

        $usuarioModelo = new Usuario();
        $usuario = $usuarioModelo->verificarCredenciales($email, $password);

        if (!$usuario || $usuario['rol'] !== 'admin') {
            Seguridad::registrarIntento($email, false);
            $this->mostrarLogin('Usuario o contraseña incorrectos.');
            return;
        }

        Seguridad::registrarIntento($email, true);
        Seguridad::renovarSesion();

        $_SESSION['admin_id']     = (int) $usuario['id'];
        $_SESSION['admin_nombre'] = $usuario['nombre'];
        $usuarioModelo->registrarAcceso((int) $usuario['id']);

        $this->redireccionar('admin/panel');
    }

    public function logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_nombre']);
        Seguridad::renovarSesion();
        $this->flash('Sesión de administrador cerrada.', 'exito');
        $this->redireccionar('login');
    }

    private function mostrarLogin(string $error): void
    {
        $this->vista('auth/login', [
            'titulo' => 'Acceso administrador | ' . TIENDA_NOMBRE,
            'error'  => $error,
        ], false);
    }

    /* ═══════════════════════════ CLIENTE ═══════════════════════ */

    public function ingresar(): void
    {
        if ($this->clienteId()) {
            $this->redireccionar('mi-cuenta');
            return;
        }

        $this->vista('auth/ingresar', [
            'titulo' => 'Iniciar sesión | ' . TIENDA_NOMBRE,
            'error'  => null,
            'email'  => '',
        ], false);
    }

    public function procesarIngreso(): void
    {
        if (!$this->validarCsrf($_POST['csrf_token'] ?? null)) {
            $this->mostrarIngreso('La sesión del formulario expiró. Intenta de nuevo.');
            return;
        }

        $email    = $this->texto($_POST, 'email', 150);
        $password = (string) ($_POST['password'] ?? '');

        $bloqueo = Seguridad::minutosBloqueo($email);
        if ($bloqueo > 0) {
            $this->mostrarIngreso("Demasiados intentos fallidos. Espera {$bloqueo} minuto(s).", $email);
            return;
        }

        $usuarioModelo = new Usuario();
        $usuario = $usuarioModelo->verificarCredenciales($email, $password);

        if (!$usuario) {
            Seguridad::registrarIntento($email, false);
            $this->mostrarIngreso('Correo o contraseña incorrectos.', $email);
            return;
        }

        Seguridad::registrarIntento($email, true);
        $this->iniciarSesionCliente($usuario);

        // Si el admin entra por el formulario de cliente, se le abren ambas sesiones.
        if ($usuario['rol'] === 'admin') {
            $_SESSION['admin_id']     = (int) $usuario['id'];
            $_SESSION['admin_nombre'] = $usuario['nombre'];
        }

        $this->redireccionar($this->destinoTrasLogin());
    }

    public function registro(): void
    {
        if ($this->clienteId()) {
            $this->redireccionar('mi-cuenta');
            return;
        }

        $this->vista('auth/registro', [
            'titulo'  => 'Crear cuenta | ' . TIENDA_NOMBRE,
            'errores' => [],
            'valores' => ['nombre' => '', 'email' => '', 'telefono' => ''],
        ], false);
    }

    public function procesarRegistro(): void
    {
        $errores = [];

        if (!$this->validarCsrf($_POST['csrf_token'] ?? null)) {
            $errores[] = 'La sesión del formulario expiró. Intenta de nuevo.';
        }

        $nombre    = $this->texto($_POST, 'nombre', 120);
        $email     = $this->texto($_POST, 'email', 150);
        $telefono  = $this->texto($_POST, 'telefono', 30);
        $password  = (string) ($_POST['password'] ?? '');
        $confirmar = (string) ($_POST['password_confirmar'] ?? '');

        if (mb_strlen($nombre) < 3) {
            $errores[] = 'Escribe tu nombre completo.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresa un correo electrónico válido.';
        }
        if (strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errores[] = 'La contraseña debe combinar letras y números.';
        }
        if ($password !== $confirmar) {
            $errores[] = 'Las contraseñas no coinciden.';
        }

        $usuarioModelo = new Usuario();

        if (!$errores && $usuarioModelo->correoExiste($email)) {
            $errores[] = 'Ya existe una cuenta con ese correo. ¿Quieres iniciar sesión?';
        }

        if ($errores) {
            $this->vista('auth/registro', [
                'titulo'  => 'Crear cuenta | ' . TIENDA_NOMBRE,
                'errores' => $errores,
                'valores' => compact('nombre', 'email', 'telefono'),
            ], false);
            return;
        }

        try {
            $nuevoId = $usuarioModelo->crear($nombre, $email, $password, 'cliente', $telefono);
        } catch (Throwable $e) {
            error_log('[AuthController::procesarRegistro] ' . $e->getMessage());
            $this->vista('auth/registro', [
                'titulo'  => 'Crear cuenta | ' . TIENDA_NOMBRE,
                'errores' => ['No pudimos crear tu cuenta. Intenta de nuevo.'],
                'valores' => compact('nombre', 'email', 'telefono'),
            ], false);
            return;
        }

        $this->iniciarSesionCliente($usuarioModelo->buscarPorId($nuevoId));
        $this->flash('¡Bienvenida a ' . TIENDA_NOMBRE . '! Tu cuenta quedó creada.', 'exito');
        $this->redireccionar($this->destinoTrasLogin());
    }

    public function salir(): void
    {
        unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_email']);
        Seguridad::renovarSesion();
        $this->flash('Cerraste sesión. ¡Vuelve pronto!', 'exito');
        $this->redireccionar('');
    }

    /* ══════════════════════════ INTERNOS ═══════════════════════ */

    private function mostrarIngreso(string $error, string $email = ''): void
    {
        $this->vista('auth/ingresar', [
            'titulo' => 'Iniciar sesión | ' . TIENDA_NOMBRE,
            'error'  => $error,
            'email'  => $email,
        ], false);
    }

    private function iniciarSesionCliente(array $usuario): void
    {
        Seguridad::renovarSesion();

        $_SESSION['cliente_id']     = (int) $usuario['id'];
        $_SESSION['cliente_nombre'] = $usuario['nombre'];
        $_SESSION['cliente_email']  = $usuario['email'];

        (new Usuario())->registrarAcceso((int) $usuario['id']);
    }

    /**
     * A dónde llevar al cliente después de entrar.
     * Solo se aceptan rutas internas de este sitio: un destino con
     * dominio externo se descarta (protección contra open redirect).
     */
    private function destinoTrasLogin(): string
    {
        $destino = $_SESSION['destino_post_login'] ?? '';
        unset($_SESSION['destino_post_login']);

        if ($destino === '') {
            return 'mi-cuenta';
        }

        $ruta = (string) parse_url($destino, PHP_URL_PATH);
        $base = rtrim(BASE_URL, '/');

        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = substr($ruta, strlen($base));
        }

        $ruta = ltrim($ruta, '/');

        if ($ruta === '' || str_contains($ruta, '://') || str_starts_with($ruta, '/')) {
            return 'mi-cuenta';
        }

        return $ruta;
    }
}
