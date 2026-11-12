<?php
/**
 * Database
 * Conexión PDO única y reutilizable (Singleton).
 *
 * El motor de producción es MySQL/MariaDB. Existe además un modo
 * `sqlite` pensado exclusivamente para pruebas y demostraciones sin
 * instalar un servidor de base de datos: en ese modo se registran
 * funciones equivalentes a las de MySQL (NOW, CONCAT, LPAD…) para que
 * las consultas de los modelos funcionen sin cambios. La aplicación
 * real usa MySQL — el modo sqlite no altera la arquitectura.
 */
class Database
{
    private static ?PDO $instancia = null;

    private function __construct() {}

    public static function obtenerConexion(): PDO
    {
        if (self::$instancia !== null) {
            return self::$instancia;
        }

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            if (DB_DRIVER === 'sqlite') {
                self::$instancia = self::conectarSqlite($opciones);
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );
                self::$instancia = new PDO($dsn, DB_USER, DB_PASS, $opciones);
                self::$instancia->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
            }
        } catch (PDOException $e) {
            error_log('[Database] ' . $e->getMessage());

            http_response_code(500);
            if (defined('MODO_DEBUG') && MODO_DEBUG) {
                // Pista concreta según el error más común en XAMPP.
                $codigo = (int) ($e->errorInfo[1] ?? 0);
                $pista = match (true) {
                    $codigo === 1049 => 'La base de datos "' . DB_NAME . '" no existe. Abre http://localhost/phpmyadmin, '
                                      . 'pestaña Importar, y carga el archivo database/sfes.sql.',
                    in_array($codigo, [1045, 1698], true) => 'Usuario o contraseña de MySQL incorrectos. Revisa DB_USER y DB_PASS en el archivo .env '
                                      . '(en XAMPP por defecto: DB_USER=root y DB_PASS vacío).',
                    $codigo === 2002 || str_contains($e->getMessage(), 'refused')
                                     => 'MySQL no responde. Abre el XAMPP Control Panel y pulsa "Start" en MySQL. '
                                      . 'Si tu MySQL usa otro puerto (p. ej. 3307), cámbialo en DB_PORT del .env.',
                    default          => 'Revisa las credenciales en tu archivo .env y que el servicio MySQL esté encendido.',
                };
                die('<pre style="font:14px monospace;padding:24px;white-space:pre-wrap">Error de conexión a la base de datos:'
                    . "\n\n" . htmlspecialchars($e->getMessage())
                    . "\n\n" . htmlspecialchars($pista) . '</pre>');
            }
            die('No fue posible conectar a la base de datos. Intenta de nuevo más tarde.');
        }

        return self::$instancia;
    }

    /**
     * Conexión SQLite + funciones compatibles con MySQL.
     * Solo se usa cuando DB_DRIVER=sqlite (pruebas automatizadas).
     */
    private static function conectarSqlite(array $opciones): PDO
    {
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, $opciones);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $pdo->sqliteCreateFunction('NOW',     fn() => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'), 0);
        $pdo->sqliteCreateFunction('YEAR',    fn($f) => $f ? (int) date('Y', strtotime((string) $f)) : null, 1);
        $pdo->sqliteCreateFunction('MONTH',   fn($f) => $f ? (int) date('n', strtotime((string) $f)) : null, 1);
        $pdo->sqliteCreateFunction('DAY',     fn($f) => $f ? (int) date('j', strtotime((string) $f)) : null, 1);
        $pdo->sqliteCreateFunction('LPAD',    fn($v, $n, $c) => str_pad((string) $v, (int) $n, (string) $c, STR_PAD_LEFT), 3);
        $pdo->sqliteCreateFunction('CONCAT',  fn(...$partes) => implode('', array_map('strval', $partes)));
        $pdo->sqliteCreateFunction('DATE_FORMAT', function ($fecha, $formato) {
            if (!$fecha) {
                return null;
            }
            $mapa = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%H' => 'H', '%i' => 'i', '%s' => 's'];
            return date(strtr($formato, $mapa), strtotime((string) $fecha));
        }, 2);

        return $pdo;
    }

    /** Nombre del motor activo (útil para pruebas y diagnósticos). */
    public static function motor(): string
    {
        return DB_DRIVER === 'sqlite' ? 'sqlite' : 'mysql';
    }
}
