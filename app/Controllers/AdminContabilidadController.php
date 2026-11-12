<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Reporte.php';
require_once __DIR__ . '/../Models/Gasto.php';
require_once __DIR__ . '/../Models/Producto.php';

/**
 * AdminContabilidadController
 * Módulo contable: estado de resultados del periodo, evolución mensual
 * de ingresos contra gastos, desglose de egresos por categoría y
 * registro de gastos operativos.
 *
 * La utilidad se calcula con el costo congelado en cada venta
 * (pedidos.costo_total), no con el costo actual del producto: así la
 * utilidad de un mes cerrado no cambia si mañana sube el proveedor.
 */
class AdminContabilidadController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        [$desde, $hasta] = $this->rangoSolicitado();

        $reporte = new Reporte();
        $gastos  = new Gasto();

        $this->vistaAdmin('admin/contabilidad', [
            'titulo'         => 'Contabilidad | ' . TIENDA_NOMBRE,
            'seccion'        => 'contabilidad',
            'rango'          => ['desde' => $desde, 'hasta' => $hasta, 'preset' => $this->presetActual()],
            'estado'         => $reporte->estadoResultados($desde, $hasta),
            'serieMensual'   => $reporte->ventasPorMes(12),
            'gastos'         => $gastos->listar($desde, $hasta, $this->texto($_GET, 'categoria', 30)),
            'porCategoria'   => $gastos->porCategoria($desde, $hasta),
            'categoriasGasto'=> Gasto::CATEGORIAS,
            'categoriaFiltro'=> $this->texto($_GET, 'categoria', 30),
            'inventario'     => (new Producto())->valorInventario(),
            'topProductos'   => $reporte->topProductos($desde, $hasta, 8),
            'jsPagina'       => ['graficas', 'admin-contabilidad'],
        ]);
    }

    /** Series para redibujar las gráficas del módulo contable. */
    public function seriesJson(): void
    {
        $this->requerirAdminJson();

        [$desde, $hasta] = $this->rangoSolicitado();
        $reporte = new Reporte();

        $this->json([
            'ok'           => true,
            'rango'        => compact('desde', 'hasta'),
            'estado'       => $reporte->estadoResultados($desde, $hasta),
            'serieMensual' => $reporte->ventasPorMes(12),
            'serieDiaria'  => $reporte->ventasPorDia($desde, $hasta),
            'porCategoria' => (new Gasto())->porCategoria($desde, $hasta),
        ]);
    }

    public function guardarGasto(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/contabilidad');

        $concepto  = $this->texto($_POST, 'concepto', 160);
        $categoria = $this->texto($_POST, 'categoria', 30);
        $monto     = $this->decimal($_POST, 'monto');
        $fecha     = $this->fechaValida($this->texto($_POST, 'fecha', 10)) ?? date('Y-m-d');
        $nota      = $this->texto($_POST, 'nota', 500);

        $errores = [];
        if (mb_strlen($concepto) < 3) {
            $errores[] = 'Describe el gasto en al menos 3 caracteres.';
        }
        if ($monto <= 0) {
            $errores[] = 'El monto debe ser mayor a cero.';
        }
        if (strtotime($fecha) > strtotime(date('Y-m-d'))) {
            $errores[] = 'No puedes registrar un gasto con fecha futura.';
        }

        if ($errores) {
            $this->flash(implode(' ', $errores), 'error');
            $this->redireccionar('admin/contabilidad');
            return;
        }

        try {
            (new Gasto())->crear(compact('concepto', 'categoria', 'monto', 'fecha', 'nota'), $this->adminId());
            $this->flash('Gasto registrado: ' . moneda($monto) . '.', 'exito');
        } catch (Throwable $e) {
            error_log('[AdminContabilidadController::guardarGasto] ' . $e->getMessage());
            $this->flash('No se pudo registrar el gasto.', 'error');
        }

        $this->redireccionar('admin/contabilidad');
    }

    public function eliminarGasto(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/contabilidad');

        $id = $this->entero($_POST, 'id', 0);

        if ($id > 0 && (new Gasto())->eliminar($id)) {
            $this->flash('Gasto eliminado.', 'exito');
        } else {
            $this->flash('No se pudo eliminar el gasto.', 'error');
        }

        $this->redireccionar('admin/contabilidad');
    }

    /* ══════════════════════════ INTERNOS ═════════════════════════ */

    private function presetActual(): string
    {
        $preset = $this->texto($_GET, 'periodo', 12);
        return in_array($preset, ['mes', 'mes_anterior', '90d', 'anio', 'personalizado'], true) ? $preset : 'mes';
    }

    /** @return array{0:string, 1:string} */
    private function rangoSolicitado(): array
    {
        $hoy    = date('Y-m-d');
        $preset = $this->presetActual();

        if ($preset === 'personalizado') {
            $desde = $this->fechaValida($this->texto($_GET, 'desde', 10)) ?? date('Y-m-01');
            $hasta = $this->fechaValida($this->texto($_GET, 'hasta', 10)) ?? $hoy;

            if (strtotime($desde) > strtotime($hasta)) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            return [$desde, $hasta];
        }

        return match ($preset) {
            'mes_anterior' => [
                date('Y-m-01', strtotime('first day of last month')),
                date('Y-m-t', strtotime('last day of last month')),
            ],
            '90d'  => [date('Y-m-d', strtotime('-89 days')), $hoy],
            'anio' => [date('Y-01-01'), $hoy],
            default => [date('Y-m-01'), $hoy],
        };
    }

    private function fechaValida(string $fecha): ?string
    {
        if ($fecha === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return ($d && $d->format('Y-m-d') === $fecha) ? $fecha : null;
    }
}
