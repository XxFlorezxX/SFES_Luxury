<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Carrito.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Direccion.php';
require_once __DIR__ . '/../Models/Envio.php';
require_once __DIR__ . '/../Models/Cupon.php';

/**
 * CheckoutController
 * Cierre de la compra.
 *
 * Regla que gobierna todo este archivo: del navegador solo se aceptan
 * los datos de envío. Piezas, precios, costos, descuento y total se
 * recalculan aquí contra la base de datos justo antes de guardar. Aunque
 * alguien manipule el formulario, el pedido que queda registrado es el
 * que corresponde al carrito real.
 */
class CheckoutController extends Controller
{
    public function index(): void
    {
        $carrito = new Carrito();

        if ($carrito->estaVacio()) {
            $this->flash('Tu bolsa está vacía. Explora el catálogo para empezar.', 'aviso');
            $this->redireccionar('catalogo');
            return;
        }

        $direccion = null;
        if ($this->clienteId()) {
            $direccion = (new Direccion())->principalDe($this->clienteId()) ?: null;
            if ($direccion && $carrito->ciudadEnvio() === '' && !empty($direccion['ciudad'])) {
                $carrito->fijarCiudad($direccion['ciudad']);
            }
        }

        $this->vista('carrito/checkout', [
            'titulo'       => 'Finalizar compra | ' . TIENDA_NOMBRE,
            'items'        => $carrito->obtenerDetalle(),
            'totales'      => $carrito->totales(),
            'problemas'    => $carrito->problemasDeStock(),
            'direccion'    => $direccion,
            'ciudades'     => (new Envio())->ciudades(),
            'paginaActual' => 'carrito',
            'jsPagina'     => ['tienda', 'checkout'],
        ]);
    }

    /** Crea el pedido. Responde JSON: el formulario se envía por fetch. */
    public function confirmar(): void
    {
        $datos = $this->entradaJson();
        $this->exigirCsrfJson($datos);

        $carrito = new Carrito();

        if ($carrito->estaVacio()) {
            $this->json(['ok' => false, 'mensaje' => 'Tu bolsa está vacía.']);
            return;
        }

        // ── Datos de envío (lo único que sí viene del cliente) ──
        $nombres   = mb_substr(trim((string) ($datos['nombres'] ?? '')), 0, 150);
        $telefono  = mb_substr(trim((string) ($datos['telefono'] ?? '')), 0, 30);
        $direccion = mb_substr(trim((string) ($datos['direccion'] ?? '')), 0, 300);
        $ciudad    = mb_substr(trim((string) ($datos['ciudad'] ?? '')), 0, 100);
        $notas     = mb_substr(trim((string) ($datos['especificaciones'] ?? '')), 0, 500);
        $email     = mb_substr(trim((string) ($datos['email'] ?? '')), 0, 150);
        $metodo    = in_array($datos['metodo_pago'] ?? '', ['contraentrega', 'transferencia'], true)
            ? $datos['metodo_pago'] : 'contraentrega';

        $errores = [];
        if ($nombres === '')            $errores[] = 'Escribe tu nombre completo.';
        if (strlen(preg_replace('/\D/', '', $telefono) ?? '') < 7) $errores[] = 'Escribe un teléfono válido.';
        if ($direccion === '')          $errores[] = 'Escribe la dirección de entrega.';
        if ($ciudad === '')             $errores[] = 'Selecciona la ciudad de entrega.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo no es válido.';

        if ($errores) {
            $this->json(['ok' => false, 'mensaje' => implode(' ', $errores)]);
            return;
        }

        // ── Disponibilidad antes de cobrar ──
        $problemas = $carrito->problemasDeStock();
        if ($problemas) {
            $this->json([
                'ok'      => false,
                'mensaje' => 'El inventario cambió mientras comprabas: ' . implode(' ', $problemas),
                'recargar'=> true,
            ]);
            return;
        }

        // ── Importes: recalculados aquí, nunca leídos del payload ──
        $carrito->fijarCiudad($ciudad);
        $totales = $carrito->totales();

        $items = [];
        foreach ($carrito->obtenerDetalle() as $item) {
            $items[] = [
                'producto_id' => (int) $item['id'],
                'nombre'      => $item['nombre'],
                'precio'      => (float) $item['precio'],
                'costo'       => (float) $item['costo'],
                'cantidad'    => (int) $item['cantidad'],
                'imagen'      => $item['imagen'],
            ];
        }

        $clienteId = $this->clienteId();
        $emailFinal = $clienteId ? ($_SESSION['cliente_email'] ?? $email) : $email;

        try {
            $pedidoId = (new Pedido())->crear(
                [
                    'nombres'          => $nombres,
                    'email'            => $emailFinal ?: null,
                    'telefono'         => $telefono,
                    'direccion'        => $direccion,
                    'ciudad'           => $ciudad,
                    'especificaciones' => $notas,
                    'metodo_pago'      => $metodo,
                ],
                $items,
                $clienteId,
                [
                    'subtotal'     => $totales['subtotal'],
                    'descuento'    => $totales['descuento'],
                    'cupon_codigo' => $totales['cupon'],
                    'envio'        => $totales['envio'],
                    'total'        => $totales['total'],
                ]
            );
        } catch (RuntimeException $e) {
            // Falta de stock detectada dentro de la transacción.
            $this->json(['ok' => false, 'mensaje' => $e->getMessage(), 'recargar' => true]);
            return;
        } catch (Throwable $e) {
            error_log('[CheckoutController::confirmar] ' . $e->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No pudimos procesar tu pedido. Intenta de nuevo.'], 500);
            return;
        }

        if (!empty($totales['cupon'])) {
            (new Cupon())->registrarUso($totales['cupon']);
        }

        if ($clienteId) {
            (new Direccion())->guardar($clienteId, [
                'nombres'          => $nombres,
                'telefono'         => $telefono,
                'direccion'        => $direccion,
                'ciudad'           => $ciudad,
                'especificaciones' => $notas,
            ]);
        }

        $carrito->vaciar();
        $_SESSION['ultimo_pedido'] = $pedidoId;

        $this->json([
            'ok'        => true,
            'mensaje'   => '¡Pedido registrado!',
            'pedido_id' => $pedidoId,
            'redirigir' => url('pedido/confirmado/' . $pedidoId),
        ]);
    }

    /**
     * Página de agradecimiento.
     * Solo la ve quien acaba de hacer el pedido (o su dueño autenticado):
     * cambiar el número en la URL no muestra pedidos de otras personas.
     */
    public function confirmado(string $id): void
    {
        $pedidoId     = (int) $id;
        $pedidoModelo = new Pedido();
        $esSuyo       = (int) ($_SESSION['ultimo_pedido'] ?? 0) === $pedidoId;

        $pedido = $esSuyo
            ? $pedidoModelo->buscarPorId($pedidoId)
            : ($this->clienteId() ? $pedidoModelo->buscarDeUsuario($pedidoId, $this->clienteId()) : false);

        if (!$pedido) {
            $this->flash('No encontramos ese pedido.', 'error');
            $this->redireccionar('');
            return;
        }

        $this->vista('carrito/confirmado', [
            'titulo'       => 'Pedido ' . ($pedido['codigo'] ?? '#' . $pedido['id']) . ' | ' . TIENDA_NOMBRE,
            'pedido'       => $pedido,
            'items'        => $pedidoModelo->items($pedidoId),
            'paginaActual' => 'carrito',
        ]);
    }
}
