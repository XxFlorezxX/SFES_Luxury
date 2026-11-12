<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Seguridad
 * Utilidades transversales de protección:
 *   · Arranque endurecido de la sesión (cookies HttpOnly/SameSite, expiración).
 *   · Cabeceras de seguridad del navegador.
 *   · Freno de fuerza bruta en los formularios de inicio de sesión.
 *   · Validación real de las imágenes que sube el administrador.
 */
class Seguridad
{
    /* ═══════════════════════════ SESIÓN ══════════════════════════ */

    /**
     * Inicia la sesión con parámetros seguros.
     * - HttpOnly: JavaScript no puede leer la cookie (mitiga robo por XSS).
     * - SameSite=Lax: el navegador no la envía en peticiones de otros sitios (mitiga CSRF).
     * - Secure: solo por HTTPS cuando el sitio corre en HTTPS.
     */
    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => BASE_URL,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ]);

        session_name('SFESSESSID');
        session_start();

        self::vigilarInactividad();
    }

    /** Cierra la sesión tras SESION_VIDA_MIN minutos sin actividad. */
    private static function vigilarInactividad(): void
    {
        $limite = SESION_VIDA_MIN * 60;

        if (!empty($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > $limite) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['ultima_actividad'] = time();
    }

    /** Renueva el identificador de sesión al cambiar de privilegio (login/logout). */
    public static function renovarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /* ═══════════════════════ CABECERAS HTTP ══════════════════════ */

    /**
     * Cabeceras que endurecen el navegador del visitante.
     * La CSP permite las fuentes de Google y Font Awesome que ya usaba
     * el proyecto, y nada más: cualquier script inyectado desde otro
     * dominio queda bloqueado.
     */
    public static function cabeceras(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header_remove('X-Powered-By');

        $csp = "default-src 'self'; "
             . "img-src 'self' data: https:; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
             . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
             . "script-src 'self' 'unsafe-inline'; "
             . "connect-src 'self'; "
             . "form-action 'self'; "
             . "base-uri 'self'; "
             . "frame-ancestors 'self'";
        header('Content-Security-Policy: ' . $csp);
    }

    /* ════════════════ FRENO DE FUERZA BRUTA (LOGIN) ══════════════ */

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    /** Registra un intento de inicio de sesión (exitoso o no). */
    public static function registrarIntento(string $email, bool $exitoso): void
    {
        try {
            $db = Database::obtenerConexion();
            $stmt = $db->prepare('INSERT INTO intentos_login (email, ip, exitoso, creado_en) VALUES (:email, :ip, :exitoso, NOW())');
            $stmt->execute([
                ':email'   => mb_substr($email, 0, 150),
                ':ip'      => self::ip(),
                ':exitoso' => $exitoso ? 1 : 0,
            ]);

            if ($exitoso) {
                $limpiar = $db->prepare('DELETE FROM intentos_login WHERE email = :email AND exitoso = 0');
                $limpiar->execute([':email' => mb_substr($email, 0, 150)]);
            }
        } catch (Throwable $e) {
            error_log('[Seguridad::registrarIntento] ' . $e->getMessage());
        }
    }

    /**
     * ¿Está bloqueado este correo/IP por exceso de intentos fallidos?
     * Devuelve los minutos que faltan para poder reintentar, o 0.
     */
    public static function minutosBloqueo(string $email): int
    {
        try {
            $db     = Database::obtenerConexion();
            $desde  = date('Y-m-d H:i:s', time() - (LOGIN_VENTANA_MIN * 60));

            $stmt = $db->prepare(
                'SELECT COUNT(*) AS fallidos, MAX(creado_en) AS ultimo
                 FROM intentos_login
                 WHERE exitoso = 0 AND creado_en >= :desde AND (email = :email OR ip = :ip)'
            );
            $stmt->execute([':desde' => $desde, ':email' => mb_substr($email, 0, 150), ':ip' => self::ip()]);
            $fila = $stmt->fetch();

            if (!$fila || (int) $fila['fallidos'] < LOGIN_MAX_INTENTOS) {
                return 0;
            }

            $transcurrido = (time() - strtotime((string) $fila['ultimo'])) / 60;
            $restante     = (int) ceil(LOGIN_VENTANA_MIN - $transcurrido);

            return max($restante, 0);
        } catch (Throwable $e) {
            error_log('[Seguridad::minutosBloqueo] ' . $e->getMessage());
            return 0; // ante un fallo de la BD no dejamos a nadie fuera del sitio
        }
    }

    /* ═════════════════════ SUBIDA DE IMÁGENES ════════════════════ */

    /**
     * Valida y guarda una imagen subida desde el panel.
     * No se confía en la extensión ni en el Content-Type que envía el
     * navegador: se comprueba que el archivo sea realmente una imagen
     * (getimagesize) y se le asigna un nombre propio generado por el
     * servidor, sin nada del nombre original.
     *
     * @return array{ok:bool, ruta?:string, error?:string}
     */
    public static function guardarImagen(array $archivo, string $carpetaDestino): array
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'sin_archivo'];
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'La subida falló (código ' . $archivo['error'] . ').'];
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            return ['ok' => false, 'error' => 'Archivo no válido.'];
        }

        if ($archivo['size'] > MAX_PESO_IMAGEN) {
            return ['ok' => false, 'error' => 'La imagen supera el máximo de ' . round(MAX_PESO_IMAGEN / 1048576) . ' MB.'];
        }

        // Comprobación real del contenido, no de la extensión.
        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
        }

        $permitidos = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF  => 'gif',
        ];

        if (!isset($permitidos[$info[2]])) {
            return ['ok' => false, 'error' => 'Formato no permitido. Usa JPG, PNG, WEBP o GIF.'];
        }

        if (!is_dir($carpetaDestino) && !@mkdir($carpetaDestino, 0775, true)) {
            return ['ok' => false, 'error' => 'No se pudo crear la carpeta de imágenes.'];
        }

        if (!is_writable($carpetaDestino)) {
            return ['ok' => false, 'error' => 'La carpeta de imágenes no tiene permisos de escritura.'];
        }

        $nombre = 'prod_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$info[2]];

        if (!move_uploaded_file($archivo['tmp_name'], rtrim($carpetaDestino, '/') . '/' . $nombre)) {
            return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
        }

        @chmod(rtrim($carpetaDestino, '/') . '/' . $nombre, 0644);

        return ['ok' => true, 'ruta' => RUTA_UPLOADS . $nombre];
    }

    /** Borra una imagen subida previamente (nunca toca las de assets/). */
    public static function borrarImagen(?string $ruta): void
    {
        if (empty($ruta) || !str_starts_with($ruta, RUTA_UPLOADS)) {
            return;
        }
        $archivo = dirname(__DIR__, 2) . '/public/' . $ruta;
        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }
}
