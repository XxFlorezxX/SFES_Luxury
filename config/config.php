<?php
/**
 * Configuración global — SFES Luxury
 *
 * Toda la configuración sensible vive en el archivo .env de la raíz
 * (ver .env.example). Este archivo solo define los valores por defecto
 * y las constantes que el resto de la aplicación consume.
 *
 * NUNCA escribas credenciales reales aquí: van en .env, que está
 * excluido del control de versiones.
 */

// ─────────────────────────────────────────────────────────────
//  Cargador de .env
// ─────────────────────────────────────────────────────────────
function sfes_cargar_env(string $ruta): void
{
    if (!is_readable($ruta)) {
        return;
    }

    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor, " \t\n\r\0\x0B\"'");
        if ($clave !== '' && getenv($clave) === false) {
            putenv("{$clave}={$valor}");
        }
    }
}

sfes_cargar_env(dirname(__DIR__) . '/.env');

function sfes_env(string $clave, string $porDefecto = ''): string
{
    $valor = getenv($clave);
    return $valor === false || $valor === '' ? $porDefecto : $valor;
}

function sfes_env_bool(string $clave, bool $porDefecto = false): bool
{
    $valor = strtolower(sfes_env($clave, $porDefecto ? 'true' : 'false'));
    return in_array($valor, ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
}

// ─────────────────────────────────────────────────────────────
//  Base de datos
// ─────────────────────────────────────────────────────────────
define('DB_DRIVER',  sfes_env('DB_DRIVER', 'mysql'));   // mysql | sqlite (sqlite solo para pruebas)
define('DB_HOST',    sfes_env('DB_HOST', '127.0.0.1'));
define('DB_PORT',    sfes_env('DB_PORT', '3306'));
define('DB_NAME',    sfes_env('DB_NAME', 'sfes_luxury'));
define('DB_USER',    sfes_env('DB_USER', 'root'));
define('DB_PASS',    sfes_env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');
define('DB_SQLITE_PATH', sfes_env('DB_SQLITE_PATH', dirname(__DIR__) . '/storage/sfes.sqlite'));

// ─────────────────────────────────────────────────────────────
//  Aplicación
// ─────────────────────────────────────────────────────────────

/**
 * URL base del sitio.
 * Si no se define BASE_URL en el .env se detecta automáticamente a
 * partir de la ubicación de public/index.php, así el proyecto funciona
 * igual en la raíz del dominio, en una subcarpeta de XAMPP o con el
 * servidor embebido de PHP, sin editar ningún archivo.
 */
function sfes_detectar_base_url(): string
{
    $configurada = sfes_env('BASE_URL', '');
    if ($configurada !== '') {
        return '/' . trim($configurada, '/') . (trim($configurada, '/') === '' ? '' : '/');
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');

    // Si se entra por http://localhost/SFES_Luxury/ (el .htaccess de la
    // raíz reenvía a public/ por dentro), la URL que ve el navegador NO
    // lleva "/public": se quita para que enlaces, CSS y cookies coincidan.
    $uri = rawurldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
    if (str_ends_with($base, '/public') && !str_starts_with($uri . '/', $base . '/')) {
        $base = substr($base, 0, -strlen('/public'));
    }

    return $base === '' ? '/' : $base . '/';
}

define('BASE_URL', sfes_detectar_base_url());

// Carpeta de imágenes subidas desde el panel (relativa a /public)
define('RUTA_UPLOADS', 'uploads/productos/');
define('MAX_PESO_IMAGEN', 3 * 1024 * 1024); // 3 MB por archivo

// Identidad de la tienda (valores por defecto; la tabla `configuracion`
// puede sobrescribirlos desde el panel de administración).
define('TIENDA_NOMBRE',   sfes_env('TIENDA_NOMBRE', 'SFES Luxury'));
define('TIENDA_EMAIL',    sfes_env('TIENDA_EMAIL', 'contacto@sfesluxury.com'));
define('TIENDA_WHATSAPP', sfes_env('TIENDA_WHATSAPP', '573000000000'));

// ─────────────────────────────────────────────────────────────
//  Seguridad
// ─────────────────────────────────────────────────────────────
define('LOGIN_MAX_INTENTOS',  5);   // intentos fallidos permitidos…
define('LOGIN_VENTANA_MIN',  15);   // …dentro de esta ventana de minutos
define('SESION_VIDA_MIN',   120);   // minutos de inactividad antes de cerrar sesión

// ─────────────────────────────────────────────────────────────
//  Inteligencia artificial (opcional — el sitio funciona sin claves)
// ─────────────────────────────────────────────────────────────
define('ANTHROPIC_API_KEY', sfes_env('ANTHROPIC_API_KEY', ''));
define('OPENAI_API_KEY',    sfes_env('OPENAI_API_KEY', ''));

// ─────────────────────────────────────────────────────────────
//  Entorno / errores
//  En producción pon APP_DEBUG=false en el .env: los errores dejan de
//  mostrarse al visitante y se escriben en storage/logs/error.log.
// ─────────────────────────────────────────────────────────────
define('MODO_DEBUG', sfes_env_bool('APP_DEBUG', true));

$rutaLogs = dirname(__DIR__) . '/storage/logs';
if (!is_dir($rutaLogs)) {
    @mkdir($rutaLogs, 0775, true);
}

ini_set('log_errors', '1');
ini_set('error_log', $rutaLogs . '/error.log');

if (MODO_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

date_default_timezone_set(sfes_env('APP_TIMEZONE', 'America/Bogota'));
