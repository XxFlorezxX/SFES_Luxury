<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Mensaje.php';

/**
 * ContactoController
 * Formulario de contacto con validación en servidor, token CSRF y un
 * campo trampa oculto ("honeypot") que los robots de spam suelen
 * rellenar y las personas no: si viene lleno, el mensaje se descarta en
 * silencio.
 */
class ContactoController extends Controller
{
    public function index(): void
    {
        $this->mostrar();
    }

    public function enviar(): void
    {
        if (!$this->validarCsrf($_POST['csrf_token'] ?? null)) {
            $this->mostrar(['La sesión del formulario expiró. Intenta de nuevo.']);
            return;
        }

        // Trampa antispam: el campo está oculto por CSS para las personas.
        if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
            $this->mostrar([], true);
            return;
        }

        $nombre  = $this->texto($_POST, 'nombre', 120);
        $email   = $this->texto($_POST, 'email', 150);
        $asunto  = $this->texto($_POST, 'asunto', 160);
        $mensaje = $this->texto($_POST, 'mensaje', 2000);

        $errores = [];

        if (mb_strlen($nombre) < 2) {
            $errores[] = 'Escribe tu nombre.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresa un correo electrónico válido.';
        }
        if (mb_strlen($mensaje) < 10) {
            $errores[] = 'Cuéntanos un poco más sobre la pieza que tienes en mente.';
        }

        if ($errores) {
            $this->mostrar($errores, false, compact('nombre', 'email', 'asunto', 'mensaje'));
            return;
        }

        try {
            (new Mensaje())->crear($nombre, $email, $mensaje, $asunto);
        } catch (Throwable $e) {
            error_log('[ContactoController::enviar] ' . $e->getMessage());
            $this->mostrar(['No pudimos enviar tu mensaje. Intenta de nuevo en un momento.'], false, compact('nombre', 'email', 'asunto', 'mensaje'));
            return;
        }

        $this->mostrar([], true);
    }

    private function mostrar(array $errores = [], bool $enviado = false, array $valores = []): void
    {
        $this->vista('contacto/index', [
            'titulo'       => 'Contacto | ' . TIENDA_NOMBRE,
            'descripcion'  => 'Escríbenos para cotizar una pieza a la medida o resolver cualquier duda.',
            'enviado'      => $enviado,
            'errores'      => $errores,
            'valores'      => $valores + ['nombre' => '', 'email' => '', 'asunto' => '', 'mensaje' => ''],
            'paginaActual' => 'contacto',
            'jsPagina'     => ['tienda'],
        ]);
    }
}
