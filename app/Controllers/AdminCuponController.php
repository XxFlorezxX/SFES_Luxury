<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Cupon.php';

/**
 * AdminCuponController
 * Alta, edición y baja de códigos de descuento.
 * El descuento se calcula siempre en el servidor al momento de aplicar
 * el cupón (ver modelo Cupon), así que aquí solo se administran reglas.
 */
class AdminCuponController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        $cupones = (new Cupon())->todos();

        $activos = 0;
        $usos    = 0;
        foreach ($cupones as $c) {
            $activos += (int) $c['activo'];
            $usos    += (int) $c['usos_actuales'];
        }

        $this->vistaAdmin('admin/cupones', [
            'titulo'    => 'Cupones | ' . TIENDA_NOMBRE,
            'seccion'   => 'cupones',
            'cupones'   => $cupones,
            'resumen'   => [
                'total'     => count($cupones),
                'activos'   => $activos,
                'usos'      => $usos,
                'descuento' => (new Cupon())->descuentoOtorgado(date('Y-m-01'), date('Y-m-d')),
            ],
            'jsPagina'  => ['admin-cupones'],
        ]);
    }

    public function guardar(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/cupones');

        $id     = $this->entero($_POST, 'id', 0);
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', $this->texto($_POST, 'codigo', 40)) ?? '');
        $tipo   = ($_POST['tipo'] ?? 'porcentaje') === 'fijo' ? 'fijo' : 'porcentaje';
        $valor  = $this->decimal($_POST, 'valor');

        $errores = [];
        if (mb_strlen($codigo) < 3) {
            $errores[] = 'El código debe tener al menos 3 caracteres (solo letras, números, guion o guion bajo).';
        }
        if ($valor <= 0) {
            $errores[] = 'El valor del descuento debe ser mayor a cero.';
        }
        if ($tipo === 'porcentaje' && $valor > 90) {
            $errores[] = 'Un descuento porcentual mayor a 90% no es razonable; revisa el valor.';
        }

        $inicia = $this->fechaValida($this->texto($_POST, 'inicia_en', 10));
        $vence  = $this->fechaValida($this->texto($_POST, 'vence_en', 10));

        if ($inicia && $vence && strtotime($inicia) > strtotime($vence)) {
            $errores[] = 'La fecha de inicio no puede ser posterior a la de vencimiento.';
        }

        if ($errores) {
            $this->flash(implode(' ', $errores), 'error');
            $this->redireccionar('admin/cupones');
            return;
        }

        $datos = [
            'codigo'        => $codigo,
            'descripcion'   => $this->texto($_POST, 'descripcion', 160),
            'tipo'          => $tipo,
            'valor'         => $valor,
            'minimo_compra' => max(0, $this->decimal($_POST, 'minimo_compra')),
            'usos_maximos'  => max(0, $this->entero($_POST, 'usos_maximos', 0)),
            'inicia_en'     => $inicia,
            'vence_en'      => $vence,
            'activo'        => isset($_POST['activo']) ? 1 : 0,
        ];

        try {
            (new Cupon())->guardar($id, $datos);
            $this->flash($id > 0 ? 'Cupón actualizado.' : 'Cupón creado.', 'exito');
        } catch (Throwable $e) {
            error_log('[AdminCuponController::guardar] ' . $e->getMessage());
            $mensaje = str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')
                ? 'Ya existe un cupón con ese código.'
                : 'No se pudo guardar el cupón.';
            $this->flash($mensaje, 'error');
        }

        $this->redireccionar('admin/cupones');
    }

    public function eliminar(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/cupones');

        $id = $this->entero($_POST, 'id', 0);

        if ($id > 0 && (new Cupon())->eliminar($id)) {
            $this->flash('Cupón eliminado.', 'exito');
        } else {
            $this->flash('No se pudo eliminar el cupón.', 'error');
        }

        $this->redireccionar('admin/cupones');
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
