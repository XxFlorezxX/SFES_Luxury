<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Reporte.php';
require_once __DIR__ . '/../Models/Inventario.php';
require_once __DIR__ . '/../Models/Mensaje.php';

/**
 * AdminController
 * Dashboard del panel: indicadores del periodo, gráficas de ventas,
 * inventario y ranking de productos.
 *
 * Las gráficas se dibujan en el navegador con un módulo SVG propio
 * (assets/js/graficas.js) alimentado por metricasJson(). No se carga
 * ninguna librería externa de gráficos: menos peso, sin depender de un
 * CDN y con los colores de la marca.
 */
class AdminController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        [$desde, $hasta] = $this->rangoSolicitado();

        $reporte    = new Reporte();
        $productos  = new Producto();
        $pedidos    = new Pedido();
        $inventario = new Inventario();

        $this->vistaAdmin('admin/panel', [
            'titulo'          => 'Dashboard | ' . TIENDA_NOMBRE,
            'seccion'         => 'panel',
            'rango'           => ['desde' => $desde, 'hasta' => $hasta, 'preset' => $this->presetActual()],
            'resumen'         => $reporte->resumen($desde, $hasta),
            'serieDiaria'     => $reporte->ventasPorDia($desde, $hasta),
            'serieMensual'    => $reporte->ventasPorMes(12),
            'porEstado'       => $reporte->pedidosPorEstado(),
            'porCategoria'    => $reporte->ventasPorCategoria($desde, $hasta),
            'topProductos'    => $reporte->topProductos($desde, $hasta, 6),
            'mejoresClientes' => $reporte->mejoresClientes(5),
            'clientesNuevos'  => $reporte->clientesNuevosPorMes(6),
            'inventario'      => $productos->valorInventario(),
            'bajoStock'       => $productos->bajoStock(6),
            'totalProductos'  => $productos->contar(true),
            'totalStock'      => $productos->sumaStock(),
            'totalClientes'   => (new Usuario())->contarClientes(),
            'ultimosPedidos'  => $pedidos->ultimos(6),
            'movimientos'     => $inventario->ultimos(6),
            'mensajesSinLeer' => (new Mensaje())->contarSinLeer(),
            'jsPagina'        => ['graficas', 'panel'],
        ]);
    }

    /** Series en JSON para redibujar las gráficas al cambiar el rango. */
    public function metricasJson(): void
    {
        $this->requerirAdminJson();

        [$desde, $hasta] = $this->rangoSolicitado();
        $reporte = new Reporte();

        $this->json([
            'ok'           => true,
            'rango'        => ['desde' => $desde, 'hasta' => $hasta],
            'resumen'      => $reporte->resumen($desde, $hasta),
            'serieDiaria'  => $reporte->ventasPorDia($desde, $hasta),
            'serieMensual' => $reporte->ventasPorMes(12),
            'porEstado'    => $reporte->pedidosPorEstado(),
            'porCategoria' => $reporte->ventasPorCategoria($desde, $hasta),
            'topProductos' => $reporte->topProductos($desde, $hasta, 6),
        ]);
    }

    /* ══════════════════════════ INTERNOS ═════════════════════════ */

    private function presetActual(): string
    {
        $preset = $this->texto($_GET, 'periodo', 12);
        return in_array($preset, ['7d', '30d', '90d', 'mes', 'anio', 'personalizado'], true) ? $preset : '30d';
    }

    /**
     * Rango de fechas del dashboard. Acepta atajos (7d, 30d, mes…) o un
     * rango escrito a mano, siempre validado con formato AAAA-MM-DD.
     *
     * @return array{0:string, 1:string}
     */
    private function rangoSolicitado(): array
    {
        $hoy    = date('Y-m-d');
        $preset = $this->presetActual();

        if ($preset === 'personalizado') {
            $desde = $this->fechaValida($this->texto($_GET, 'desde', 10), date('Y-m-d', strtotime('-29 days')));
            $hasta = $this->fechaValida($this->texto($_GET, 'hasta', 10), $hoy);

            if (strtotime($desde) > strtotime($hasta)) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            return [$desde, $hasta];
        }

        return match ($preset) {
            '7d'   => [date('Y-m-d', strtotime('-6 days')), $hoy],
            '90d'  => [date('Y-m-d', strtotime('-89 days')), $hoy],
            'mes'  => [date('Y-m-01'), $hoy],
            'anio' => [date('Y-01-01'), $hoy],
            default => [date('Y-m-d', strtotime('-29 days')), $hoy],
        };
    }

    private function fechaValida(string $fecha, string $porDefecto): string
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return ($d && $d->format('Y-m-d') === $fecha) ? $fecha : $porDefecto;
    }
}
