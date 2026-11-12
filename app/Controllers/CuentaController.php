<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Direccion.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Favorito.php';

/**
 * CuentaController
 * Área privada del cliente: perfil, dirección de envío, contraseña,
 * historial de pedidos y detalle de cada uno.
 *
 * Todas las consultas filtran por el ID de la sesión, de modo que un
 * cliente nunca puede ver ni modificar datos de otro cambiando un
 * número en la URL.
 */
class CuentaController extends Controller
{
    public function miCuenta(): void
    {
        $this->requerirCliente();

        $usuarioModelo = new Usuario();
        $pedidoModelo  = new Pedido();
        $clienteId     = $this->clienteId();

        $pedidos  = $pedidoModelo->deUsuario($clienteId);
        $gastado  = 0.0;
        foreach ($pedidos as $p) {
            if ($p['estado'] !== 'cancelado') {
                $gastado += (float) $p['total'];
            }
        }

        $this->vista('cuenta/mi-cuenta', [
            'titulo'       => 'Mi cuenta | ' . TIENDA_NOMBRE,
            'usuario'      => $usuarioModelo->buscarPorId($clienteId),
            'direccion'    => (new Direccion())->principalDe($clienteId) ?: null,
            'resumen'      => [
                'pedidos'   => count($pedidos),
                'gastado'   => $gastado,
                'favoritos' => (new Favorito())->contar($clienteId),
            ],
            'ultimosPedidos' => array_slice($pedidos, 0, 3),
            'paginaActual'   => 'cuenta',
            'jsPagina'       => ['tienda', 'cuenta'],
        ]);
    }

    public function guardarPerfil(): void
    {
        $this->requerirCliente();
        $this->exigirCsrf('mi-cuenta');

        $nombre   = $this->texto($_POST, 'nombre', 120);
        $email    = $this->texto($_POST, 'email', 150);
        $telefono = $this->texto($_POST, 'telefono', 30);

        if (mb_strlen($nombre) < 3) {
            $this->flash('Escribe tu nombre completo.', 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('El correo no es válido.', 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        $usuarioModelo = new Usuario();

        if ($usuarioModelo->correoExiste($email, $this->clienteId())) {
            $this->flash('Ese correo ya está registrado en otra cuenta.', 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        $usuarioModelo->actualizarPerfil($this->clienteId(), $nombre, $email, $telefono);

        $_SESSION['cliente_nombre'] = $nombre;
        $_SESSION['cliente_email']  = $email;

        $this->flash('Tus datos quedaron actualizados.', 'exito');
        $this->redireccionar('mi-cuenta');
    }

    public function cambiarClave(): void
    {
        $this->requerirCliente();
        $this->exigirCsrf('mi-cuenta');

        $actual    = (string) ($_POST['password_actual'] ?? '');
        $nueva     = (string) ($_POST['password_nueva'] ?? '');
        $confirmar = (string) ($_POST['password_confirmar'] ?? '');

        $usuarioModelo = new Usuario();
        $usuario = $usuarioModelo->buscarPorId($this->clienteId());

        if (!$usuario || !password_verify($actual, $usuario['password_hash'])) {
            $this->flash('La contraseña actual no es correcta.', 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        if (strlen($nueva) < 8 || !preg_match('/[A-Za-z]/', $nueva) || !preg_match('/\d/', $nueva)) {
            $this->flash('La nueva contraseña debe tener al menos 8 caracteres, con letras y números.', 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        if ($nueva !== $confirmar) {
            $this->flash('La confirmación no coincide.', 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        $usuarioModelo->cambiarPassword($this->clienteId(), $nueva);

        $this->flash('Contraseña actualizada.', 'exito');
        $this->redireccionar('mi-cuenta');
    }

    public function guardarDireccion(): void
    {
        $this->requerirCliente();

        $datos = $this->entradaJson();
        $esJson = $this->esAjax();

        if (!$this->validarCsrf($datos['csrf_token'] ?? null)) {
            if ($esJson) {
                $this->json(['ok' => false, 'mensaje' => 'La sesión expiró. Recarga la página.'], 419);
            }
            $this->flash('La sesión del formulario expiró.', 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        $nombres   = mb_substr(trim((string) ($datos['nombres'] ?? '')), 0, 150);
        $telefono  = mb_substr(trim((string) ($datos['telefono'] ?? '')), 0, 30);
        $direccion = mb_substr(trim((string) ($datos['direccion'] ?? '')), 0, 300);

        if ($nombres === '' || $telefono === '' || $direccion === '') {
            $mensaje = 'Completa nombre, teléfono y dirección.';
            if ($esJson) {
                $this->json(['ok' => false, 'mensaje' => $mensaje]);
            }
            $this->flash($mensaje, 'error');
            $this->redireccionar('mi-cuenta');
            return;
        }

        $ok = (new Direccion())->guardar($this->clienteId(), [
            'nombres'          => $nombres,
            'telefono'         => $telefono,
            'direccion'        => $direccion,
            'ciudad'           => mb_substr(trim((string) ($datos['ciudad'] ?? '')), 0, 100),
            'especificaciones' => mb_substr(trim((string) ($datos['especificaciones'] ?? '')), 0, 500),
        ]);

        $mensaje = $ok ? 'Dirección guardada.' : 'No se pudo guardar la dirección.';

        if ($esJson) {
            $this->json(['ok' => $ok, 'mensaje' => $mensaje]);
            return;
        }

        $this->flash($mensaje, $ok ? 'exito' : 'error');
        $this->redireccionar('mi-cuenta');
    }

    public function misPedidos(): void
    {
        $this->requerirCliente();

        $pedidoModelo = new Pedido();
        $pedidos      = $pedidoModelo->deUsuario($this->clienteId());

        foreach ($pedidos as &$pedido) {
            $pedido['resumen'] = $pedidoModelo->resumenItems((int) $pedido['id']);
        }
        unset($pedido);

        $this->vista('cuenta/mis-pedidos', [
            'titulo'       => 'Mis pedidos | ' . TIENDA_NOMBRE,
            'pedidos'      => $pedidos,
            'paginaActual' => 'cuenta',
            'jsPagina'     => ['tienda'],
        ]);
    }

    public function detallePedido(string $id): void
    {
        $this->requerirCliente();

        $pedidoModelo = new Pedido();
        $pedido = $pedidoModelo->buscarDeUsuario((int) $id, $this->clienteId());

        if (!$pedido) {
            $this->flash('No encontramos ese pedido en tu cuenta.', 'error');
            $this->redireccionar('mis-pedidos');
            return;
        }

        $this->vista('cuenta/detalle-pedido', [
            'titulo'       => 'Pedido ' . ($pedido['codigo'] ?? '#' . $pedido['id']) . ' | ' . TIENDA_NOMBRE,
            'pedido'       => $pedido,
            'items'        => $pedidoModelo->items((int) $id),
            'paginaActual' => 'cuenta',
            'jsPagina'     => ['tienda'],
        ]);
    }
}
