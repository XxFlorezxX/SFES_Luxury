<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Carrito.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Envio.php';

/**
 * CarritoController
 * La bolsa de compras. Todo el cálculo (subtotal, cupón, envío, total)
 * lo hace el modelo Carrito contra la base de datos; el navegador solo
 * envía qué pieza y qué cantidad quiere el cliente.
 */
class CarritoController extends Controller
{
    public function index(): void
    {
        $carrito   = new Carrito();
        $productos = new Producto();

        $recomendados = $carrito->estaVacio()
            ? $productos->destacados(4)
            : $productos->recomendaciones($carrito->idsProductos(), 4);

        $this->vista('carrito/index', [
            'titulo'       => 'Mi bolsa | ' . TIENDA_NOMBRE,
            'items'        => $carrito->obtenerDetalle(),
            'totales'      => $carrito->totales(),
            'problemas'    => $carrito->problemasDeStock(),
            'recomendados' => $recomendados,
            'ciudades'     => (new Envio())->ciudades(),
            'paginaActual' => 'carrito',
            'jsPagina'     => ['tienda', 'carrito'],
        ]);
    }

    /** Agrega una pieza. Responde JSON si viene por fetch, o redirige si es un enlace. */
    public function agregar(string $id): void
    {
        $carrito  = new Carrito();
        $cantidad = max(1, $this->entero($_POST, 'cantidad', 1));

        $resultado = $carrito->agregar((int) $id, $cantidad);

        if ($this->esAjax()) {
            $this->json($resultado + [
                'totalCarrito' => $carrito->totalArticulos(),
                'totales'      => $carrito->totales(),
            ]);
            return;
        }

        $this->flash($resultado['mensaje'], $resultado['ok'] ? 'exito' : 'error');
        $this->redireccionar($this->texto($_POST, 'volver', 200) ?: 'carrito');
    }

    public function actualizar(string $id): void
    {
        $carrito  = new Carrito();
        $datos    = $this->entradaJson();
        $cantidad = (int) ($datos['cantidad'] ?? 1);

        $resultado = $carrito->actualizarCantidad((int) $id, $cantidad);

        if ($this->esAjax()) {
            $this->json($resultado + [
                'totalCarrito' => $carrito->totalArticulos(),
                'totales'      => $carrito->totales(),
                'vacio'        => $carrito->estaVacio(),
            ]);
            return;
        }

        $this->redireccionar('carrito');
    }

    public function eliminar(string $id): void
    {
        (new Carrito())->eliminar((int) $id);
        $this->flash('Pieza retirada de tu bolsa.', 'exito');
        $this->redireccionar('carrito');
    }

    public function vaciar(): void
    {
        (new Carrito())->vaciar();
        $this->flash('Tu bolsa quedó vacía.', 'exito');
        $this->redireccionar('carrito');
    }

    /* ═══════════════════════════ CUPÓN ═══════════════════════════ */

    public function aplicarCupon(): void
    {
        $datos = $this->entradaJson();
        $this->exigirCsrfJson($datos);

        $carrito = new Carrito();

        if ($carrito->estaVacio()) {
            $this->json(['ok' => false, 'mensaje' => 'Agrega piezas a tu bolsa antes de usar un cupón.']);
            return;
        }

        $codigo    = mb_substr(trim((string) ($datos['codigo'] ?? '')), 0, 40);
        $resultado = $carrito->aplicarCupon($codigo);

        $this->json([
            'ok'      => $resultado['ok'],
            'mensaje' => $resultado['mensaje'],
            'totales' => $carrito->totales(),
        ]);
    }

    public function quitarCupon(): void
    {
        $datos = $this->entradaJson();
        $this->exigirCsrfJson($datos);

        $carrito = new Carrito();
        $carrito->quitarCupon();

        $this->json(['ok' => true, 'mensaje' => 'Cupón retirado.', 'totales' => $carrito->totales()]);
    }

    /* ═══════════════════════════ ENVÍO ═══════════════════════════ */

    public function calcularEnvio(): void
    {
        $datos = $this->entradaJson();
        $this->exigirCsrfJson($datos);

        $carrito = new Carrito();
        $carrito->fijarCiudad(mb_substr(trim((string) ($datos['ciudad'] ?? '')), 0, 100));

        $this->json(['ok' => true, 'totales' => $carrito->totales()]);
    }

    /** Resumen para actualizar el mini-carrito del encabezado. */
    public function resumenJson(): void
    {
        $carrito = new Carrito();

        $items = array_map(static fn(array $i): array => [
            'id'       => (int) $i['id'],
            'nombre'   => $i['nombre'],
            'precio'   => moneda($i['precio']),
            'cantidad' => (int) $i['cantidad'],
            'imagen'   => imagen_producto($i['imagen']),
            'url'      => url('producto/' . $i['slug']),
        ], $carrito->obtenerDetalle());

        $this->json([
            'ok'      => true,
            'items'   => $items,
            'totales' => $carrito->totales(),
        ]);
    }
}
