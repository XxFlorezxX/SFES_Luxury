<?php
/**
 * Controlador base
 * Todos los controladores lo extienden. Concentra lo que de otro modo
 * se repetiría en cada uno: render de vistas, respuestas JSON, control
 * de acceso, protección CSRF, mensajes flash y saneo de entrada.
 */
abstract class Controller
{
    /* ══════════════════════════ VISTAS ══════════════════════════ */

    /**
     * Renderiza una vista pública dentro del layout de la tienda.
     *
     * @param string $vista     Ruta relativa a app/Views sin .php (ej: "catalogo/index")
     * @param array  $datos     Variables disponibles dentro de la vista
     * @param bool   $conLayout false para vistas que traen su propio HTML (login, registro)
     */
    protected function vista(string $vista, array $datos = [], bool $conLayout = true): void
    {
        $rutaVista = __DIR__ . '/../Views/' . $vista . '.php';

        if (!file_exists($rutaVista)) {
            $this->errorInterno("Vista no encontrada: {$vista}");
            return;
        }

        $datos['csrfToken'] = $datos['csrfToken'] ?? $this->csrfToken();
        $datos['flash']     = $this->tomarFlash();
        extract($datos, EXTR_SKIP);

        if ($conLayout) {
            require __DIR__ . '/../Views/layouts/header.php';
            require $rutaVista;
            require __DIR__ . '/../Views/layouts/footer.php';
        } else {
            require $rutaVista;
        }
    }

    /** Renderiza una vista del panel de administración (layout con sidebar). */
    protected function vistaAdmin(string $vista, array $datos = []): void
    {
        $rutaVista = __DIR__ . '/../Views/' . $vista . '.php';

        if (!file_exists($rutaVista)) {
            $this->errorInterno("Vista no encontrada: {$vista}");
            return;
        }

        $datos['csrfToken'] = $datos['csrfToken'] ?? $this->csrfToken();
        $datos['flash']     = $this->tomarFlash();
        extract($datos, EXTR_SKIP);

        require __DIR__ . '/../Views/layouts/admin_header.php';
        require $rutaVista;
        require __DIR__ . '/../Views/layouts/admin_footer.php';
    }

    protected function errorInterno(string $detalle): void
    {
        error_log('[SFES] ' . $detalle);
        http_response_code(500);
        echo MODO_DEBUG
            ? '<pre style="font:14px monospace;padding:24px">' . e($detalle) . '</pre>'
            : 'Ocurrió un error inesperado.';
    }

    /* ═════════════════════ NAVEGACIÓN Y JSON ═════════════════════ */

    /**
     * Redirige a una ruta interna. Solo acepta rutas propias del sitio:
     * cualquier intento de mandar al usuario a un dominio externo
     * (open redirect) se descarta y cae al inicio.
     */
    protected function redireccionar(string $ruta = ''): void
    {
        $ruta = ltrim(trim($ruta), '/');

        if ($ruta !== '' && (str_contains($ruta, '://') || str_starts_with($ruta, '//') || str_contains($ruta, "\n"))) {
            $ruta = '';
        }

        header('Location: ' . BASE_URL . $ruta);
        exit;
    }

    /** Respuesta JSON para peticiones fetch/AJAX. */
    protected function json(mixed $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Lee el cuerpo JSON de la petición; cae a $_POST si no viene JSON. */
    protected function entradaJson(): array
    {
        $crudo = file_get_contents('php://input');
        $datos = json_decode((string) $crudo, true);
        return is_array($datos) ? $datos : $_POST;
    }

    protected function esAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    /* ═══════════════════════ ENTRADA SEGURA ══════════════════════ */

    /** Texto limpio desde $_POST/$_GET: recorta y elimina caracteres de control. */
    protected function texto(array $origen, string $clave, int $maximo = 500): string
    {
        $valor = (string) ($origen[$clave] ?? '');
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor) ?? '';
        return mb_substr(trim($valor), 0, $maximo);
    }

    protected function entero(array $origen, string $clave, int $porDefecto = 0): int
    {
        return isset($origen[$clave]) && is_numeric($origen[$clave]) ? (int) $origen[$clave] : $porDefecto;
    }

    protected function decimal(array $origen, string $clave, float $porDefecto = 0.0): float
    {
        $valor = str_replace([',', ' ', '$', '.'], ['.', '', '', ''], (string) ($origen[$clave] ?? ''));
        return is_numeric($valor) ? (float) $valor : $porDefecto;
    }

    /* ══════════════════════ CONTROL DE ACCESO ════════════════════ */

    protected function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    protected function clienteId(): ?int
    {
        return isset($_SESSION['cliente_id']) ? (int) $_SESSION['cliente_id'] : null;
    }

    protected function requerirAdmin(): void
    {
        if (!$this->adminId()) {
            $this->flash('Inicia sesión como administrador para continuar.', 'aviso');
            $this->redireccionar('login');
        }
    }

    /** Igual que requerirAdmin() pero para endpoints JSON: responde 401 en vez de redirigir. */
    protected function requerirAdminJson(): void
    {
        if (!$this->adminId()) {
            $this->json(['ok' => false, 'mensaje' => 'Sesión de administrador expirada.'], 401);
        }
    }

    /** Exige sesión de cliente; conserva el destino para volver tras iniciar sesión. */
    protected function requerirCliente(): void
    {
        if (!$this->clienteId()) {
            $_SESSION['destino_post_login'] = $_SERVER['REQUEST_URI'] ?? '';
            $this->flash('Inicia sesión para continuar.', 'aviso');
            $this->redireccionar('ingresar');
        }
    }

    protected function requerirClienteJson(): void
    {
        if (!$this->clienteId()) {
            $this->json(['ok' => false, 'requiere_login' => true, 'mensaje' => 'Inicia sesión para usar esta función.'], 401);
        }
    }

    /* ════════════════════════ MENSAJES FLASH ═════════════════════ */

    /** Guarda un mensaje que se mostrará una sola vez en la siguiente página. */
    protected function flash(string $mensaje, string $tipo = 'exito'): void
    {
        $_SESSION['flash'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
    }

    protected function tomarFlash(): ?array
    {
        if (empty($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    /* ═══════════════════════ PROTECCIÓN CSRF ═════════════════════ */

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validarCsrf(?string $token): bool
    {
        return !empty($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Exige un token CSRF válido en formularios normales.
     * Si falta, corta la ejecución con un mensaje y vuelve a $rutaVuelta.
     */
    protected function exigirCsrf(string $rutaVuelta): void
    {
        if (!$this->validarCsrf($_POST['csrf_token'] ?? null)) {
            $this->flash('La sesión del formulario expiró. Intenta de nuevo.', 'error');
            $this->redireccionar($rutaVuelta);
        }
    }

    /** Igual, pero para endpoints JSON. */
    protected function exigirCsrfJson(array $datos): void
    {
        if (!$this->validarCsrf($datos['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'mensaje' => 'La sesión expiró. Recarga la página.'], 419);
        }
    }

    /* ══════════════════════════ MODELOS ══════════════════════════ */

    protected function modelo(string $nombre): object
    {
        $archivo = __DIR__ . '/../Models/' . $nombre . '.php';
        if (file_exists($archivo)) {
            require_once $archivo;
        }
        return new $nombre();
    }
}
