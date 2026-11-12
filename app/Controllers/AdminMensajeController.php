<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Mensaje.php';

/**
 * AdminMensajeController
 * Bandeja de los mensajes recibidos por el formulario de contacto.
 * Antes se guardaban en la base de datos pero nadie podía leerlos:
 * no existía ninguna pantalla que los mostrara.
 */
class AdminMensajeController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        $mensajes = new Mensaje();

        $this->vistaAdmin('admin/mensajes', [
            'titulo'   => 'Mensajes | ' . TIENDA_NOMBRE,
            'seccion'  => 'mensajes',
            'mensajes' => $mensajes->obtenerTodos(200),
            'sinLeer'  => $mensajes->contarSinLeer(),
        ]);
    }

    public function marcarLeido(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/mensajes');

        $id = $this->entero($_POST, 'id', 0);

        if ($id > 0) {
            (new Mensaje())->marcarLeido($id);
            $this->flash('Mensaje marcado como leído.', 'exito');
        }

        $this->redireccionar('admin/mensajes');
    }
}
