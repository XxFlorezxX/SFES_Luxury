<?php
/**
 * Router
 * Enrutador del front controller. Registra rutas con get()/post() y
 * despacha la petición al controlador correspondiente.
 *
 * Soporta parámetros dinámicos:
 *   {id}    → solo dígitos          ej: /producto/12
 *   {slug}  → letras, números y "-" ej: /producto/anillo-solitario
 *   {param} → cualquier segmento
 */
class Router
{
    private array $rutas = ['GET' => [], 'POST' => []];

    public function get(string $ruta, string $controlador, string $metodo): void
    {
        $this->rutas['GET'][$this->normalizar($ruta)] = [$controlador, $metodo];
    }

    public function post(string $ruta, string $controlador, string $metodo): void
    {
        $this->rutas['POST'][$this->normalizar($ruta)] = [$controlador, $metodo];
    }

    private function normalizar(string $ruta): string
    {
        $ruta = '/' . trim($ruta, '/');
        return $ruta === '/' ? '/' : $ruta;
    }

    /** Encuentra la ruta que coincide, instancia el controlador y ejecuta el método. */
    public function despachar(string $uriSolicitada, string $metodoHttp): void
    {
        $uri    = $this->normalizar((string) parse_url($uriSolicitada, PHP_URL_PATH));
        $metodo = strtoupper($metodoHttp);

        foreach ($this->rutas[$metodo] ?? [] as $patron => $accion) {
            $parametros = $this->coincide($patron, $uri);
            if ($parametros === null) {
                continue;
            }

            [$controlador, $accionMetodo] = $accion;

            $archivo = __DIR__ . '/../Controllers/' . basename($controlador) . '.php';
            if (!is_file($archivo)) {
                $this->error404("Controlador no encontrado: {$controlador}");
                return;
            }

            require_once $archivo;

            if (!class_exists($controlador)) {
                $this->error404("Clase no encontrada: {$controlador}");
                return;
            }

            $instancia = new $controlador();

            if (!method_exists($instancia, $accionMetodo)) {
                $this->error404("Acción no encontrada: {$controlador}::{$accionMetodo}");
                return;
            }

            call_user_func_array([$instancia, $accionMetodo], $parametros);
            return;
        }

        // La URL existe pero con otro verbo HTTP → 405 en vez de 404.
        $otroVerbo = $metodo === 'GET' ? 'POST' : 'GET';
        foreach ($this->rutas[$otroVerbo] ?? [] as $patron => $accion) {
            if ($this->coincide($patron, $uri) !== null) {
                http_response_code(405);
                header('Allow: ' . $otroVerbo);
                $this->renderError(405, 'Método no permitido', 'Esta dirección no acepta peticiones ' . $metodo . '.');
                return;
            }
        }

        $this->error404();
    }

    /** Compara un patrón contra la URI real y devuelve los parámetros capturados. */
    private function coincide(string $patron, string $uri): ?array
    {
        if (!str_contains($patron, '{')) {
            return $patron === $uri ? [] : null;
        }

        $expresion = preg_replace_callback(
            '/\{([a-zA-Z_]+)\}/',
            static function (array $m): string {
                return match ($m[1]) {
                    'id'   => '(\d+)',
                    'slug' => '([a-z0-9\-]+)',
                    default => '([^/]+)',
                };
            },
            $patron
        );

        if (preg_match('#^' . $expresion . '$#', $uri, $coincidencias)) {
            array_shift($coincidencias);
            return array_values($coincidencias);
        }

        return null;
    }

    private function error404(string $detalle = ''): void
    {
        if ($detalle !== '') {
            error_log('[Router] ' . $detalle);
        }
        http_response_code(404);
        $this->renderError(404, 'Página no encontrada', 'La dirección que buscas no existe o fue movida.');
    }

    /** Muestra un error dentro del layout público (JSON si la petición era AJAX). */
    private function renderError(int $codigo, string $titulo, string $mensaje): void
    {
        $esJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($esJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'codigo' => $codigo, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
            return;
        }

        $codigoError   = $codigo;
        $tituloError   = $titulo;
        $mensajeError  = $mensaje;
        $titulo        = $titulo . ' | ' . TIENDA_NOMBRE;
        $paginaActual  = '';
        $flash         = null;
        $csrfToken     = $_SESSION['csrf_token'] ?? '';

        require __DIR__ . '/../Views/layouts/header.php';
        require __DIR__ . '/../Views/errores/404.php';
        require __DIR__ . '/../Views/layouts/footer.php';
    }
}
