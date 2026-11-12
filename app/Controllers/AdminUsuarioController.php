<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Reporte.php';

/**
 * AdminUsuarioController
 * Clientes registrados y su comportamiento de compra.
 *
 * Se muestra únicamente lo necesario para atenderlos: nombre, correo,
 * teléfono y resumen de compras. El hash de la contraseña nunca sale
 * de la base de datos hacia el panel ni hacia el JSON.
 */
class AdminUsuarioController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        $usuarios = new Usuario();

        $this->vistaAdmin('admin/usuarios', [
            'titulo'   => 'Clientes | ' . TIENDA_NOMBRE,
            'seccion'  => 'usuarios',
            'totales'  => [
                'clientes' => $usuarios->contarClientes(),
                'nuevos'   => $usuarios->clientesNuevos(30),
            ],
            'mejores'  => (new Reporte())->mejoresClientes(5),
            'jsPagina' => ['admin-usuarios'],
        ]);
    }

    public function listarJson(): void
    {
        $this->requerirAdminJson();

        $resultado = (new Usuario())->listarClientes(
            max(1, $this->entero($_GET, 'pagina', 1)),
            20,
            $this->texto($_GET, 'buscar', 100)
        );

        $usuarios = array_map(static fn(array $u): array => [
            'id'            => (int) $u['id'],
            'nombre'        => $u['nombre'],
            'email'         => $u['email'],
            'telefono'      => $u['telefono'] ?: '—',
            'registro'      => fecha_corta($u['creado_en']),
            'ultimo_acceso' => $u['ultimo_acceso'] ? fecha_relativa($u['ultimo_acceso']) : 'Nunca',
            'pedidos'       => (int) $u['total_pedidos'],
            'gastado'       => moneda($u['total_gastado']),
            'gastado_num'   => (float) $u['total_gastado'],
        ], $resultado['usuarios']);

        $this->json([
            'ok'       => true,
            'usuarios' => $usuarios,
            'total'    => $resultado['total'],
            'pagina'   => $resultado['pagina'],
            'paginas'  => $resultado['paginas'],
        ]);
    }

    public function detalleJson(string $id): void
    {
        $this->requerirAdminJson();

        $ficha = (new Usuario())->fichaCliente((int) $id);

        if (!$ficha) {
            $this->json(['ok' => false, 'mensaje' => 'Ese cliente no existe.'], 404);
            return;
        }

        $ficha['pedidos'] = array_map(static fn(array $p): array => [
            'id'     => (int) $p['id'],
            'codigo' => $p['codigo'] ?: '#' . $p['id'],
            'total'  => moneda($p['total']),
            'estado' => estado_pedido_texto($p['estado']),
            'fecha'  => fecha_corta($p['creado_en']),
        ], $ficha['pedidos']);

        $ficha['resumen']['total_gastado_texto'] = moneda($ficha['resumen']['total_gastado']);
        $ficha['resumen']['ticket_texto']        = moneda($ficha['resumen']['ticket']);
        $ficha['usuario']['registro']            = fecha_corta($ficha['usuario']['creado_en']);

        $this->json(['ok' => true] + $ficha);
    }
}
