<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Pedido.php';

/**
 * AdminPedidoController
 * Gestión de pedidos: listado con filtros, detalle y cambio de estado.
 *
 * El cambio de estado no es solo cosmético: cancelar un pedido devuelve
 * la mercancía al inventario y reactivarlo la vuelve a descontar
 * (ver Pedido::actualizarEstado).
 */
class AdminPedidoController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        $pedidoModelo = new Pedido();

        $this->vistaAdmin('admin/pedidos', [
            'titulo'    => 'Pedidos | ' . TIENDA_NOMBRE,
            'seccion'   => 'pedidos',
            'estados'   => Pedido::ESTADOS,
            'porEstado' => $pedidoModelo->contarPorEstado(),
            'totales'   => [
                'pedidos'  => $pedidoModelo->contarTotal(),
                'ingresos' => $pedidoModelo->ingresosTotales(),
                'mes'      => $pedidoModelo->ingresosDelMes(),
            ],
            'jsPagina'  => ['admin-pedidos'],
        ]);
    }

    /** Listado paginado en JSON (la tabla se llena por fetch). */
    public function listarJson(): void
    {
        $this->requerirAdminJson();

        $resultado = (new Pedido())->listar(
            max(1, $this->entero($_GET, 'pagina', 1)),
            20,
            $this->texto($_GET, 'estado', 20),
            $this->texto($_GET, 'buscar', 100)
        );

        $pedidos = array_map(static fn(array $p): array => [
            'id'          => (int) $p['id'],
            'codigo'      => $p['codigo'] ?: '#' . $p['id'],
            'cliente'     => $p['nombres'],
            'email'       => $p['email'],
            'telefono'    => $p['telefono'],
            'ciudad'      => $p['ciudad'],
            'unidades'    => (int) $p['unidades'],
            'total'       => moneda($p['total']),
            'total_num'   => (float) $p['total'],
            'estado'      => $p['estado'],
            'estado_texto'=> estado_pedido_texto($p['estado']),
            'fecha'       => fecha_corta($p['creado_en']),
            'fecha_rel'   => fecha_relativa($p['creado_en']),
        ], $resultado['pedidos']);

        $this->json([
            'ok'      => true,
            'pedidos' => $pedidos,
            'total'   => $resultado['total'],
            'pagina'  => $resultado['pagina'],
            'paginas' => $resultado['paginas'],
        ]);
    }

    /** Detalle completo de un pedido para el modal. */
    public function detalleJson(string $id): void
    {
        $this->requerirAdminJson();

        $pedidoModelo = new Pedido();
        $pedido = $pedidoModelo->buscarPorId((int) $id);

        if (!$pedido) {
            $this->json(['ok' => false, 'mensaje' => 'El pedido no existe.'], 404);
            return;
        }

        $items = array_map(static fn(array $i): array => [
            'nombre'   => $i['nombre'],
            'cantidad' => (int) $i['cantidad'],
            'precio'   => moneda($i['precio']),
            'subtotal' => moneda((float) $i['precio'] * (int) $i['cantidad']),
            'imagen'   => imagen_producto($i['imagen']),
        ], $pedidoModelo->items((int) $id));

        $utilidad = (float) $pedido['total'] - (float) $pedido['envio'] - (float) $pedido['costo_total'];

        $this->json([
            'ok'     => true,
            'pedido' => [
                'id'           => (int) $pedido['id'],
                'codigo'       => $pedido['codigo'] ?: '#' . $pedido['id'],
                'cliente'      => $pedido['nombres'],
                'email'        => $pedido['email'],
                'telefono'     => $pedido['telefono'],
                'direccion'    => $pedido['direccion'],
                'ciudad'       => $pedido['ciudad'],
                'notas'        => $pedido['especificaciones'],
                'notas_admin'  => $pedido['notas_admin'],
                'estado'       => $pedido['estado'],
                'metodo_pago'  => $pedido['metodo_pago'],
                'fecha'        => fecha_corta($pedido['creado_en']) . ' · ' . date('H:i', strtotime($pedido['creado_en'])),
                'subtotal'     => moneda($pedido['subtotal']),
                'descuento'    => moneda($pedido['descuento']),
                'cupon'        => $pedido['cupon_codigo'],
                'envio'        => moneda($pedido['envio']),
                'total'        => moneda($pedido['total']),
                'costo'        => moneda($pedido['costo_total']),
                'utilidad'     => moneda($utilidad),
                'margen'       => (float) $pedido['total'] > 0
                    ? round(($utilidad / (float) $pedido['total']) * 100, 1) : 0,
            ],
            'items'   => $items,
            'estados' => Pedido::ESTADOS,
        ]);
    }

    /** Cambia el estado (y ajusta el inventario si corresponde). */
    public function actualizarEstadoJson(string $id): void
    {
        $this->requerirAdminJson();

        $datos = $this->entradaJson();
        $this->exigirCsrfJson($datos);

        $estado = (string) ($datos['estado'] ?? '');
        $notas  = mb_substr(trim((string) ($datos['notas'] ?? '')), 0, 1000);

        $resultado = (new Pedido())->actualizarEstado((int) $id, $estado, $notas);

        $this->json($resultado, $resultado['ok'] ? 200 : 400);
    }
}
