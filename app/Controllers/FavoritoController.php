<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Favorito.php';

/**
 * FavoritoController
 * Lista de deseos del cliente. Se guarda en base de datos y no en el
 * navegador, así el cliente la conserva al cambiar de dispositivo.
 */
class FavoritoController extends Controller
{
    public function index(): void
    {
        $this->requerirCliente();

        $this->vista('cuenta/favoritos', [
            'titulo'       => 'Mi lista de deseos | ' . TIENDA_NOMBRE,
            'productos'    => (new Favorito())->deUsuario($this->clienteId()),
            'paginaActual' => 'cuenta',
            'jsPagina'     => ['tienda'],
        ]);
    }

    /** Marca o desmarca una pieza (responde JSON al corazón de la tarjeta). */
    public function alternar(): void
    {
        $datos = $this->entradaJson();

        $this->requerirClienteJson();
        $this->exigirCsrfJson($datos);

        $productoId = (int) ($datos['producto_id'] ?? 0);
        if ($productoId <= 0) {
            $this->json(['ok' => false, 'mensaje' => 'Pieza no válida.']);
            return;
        }

        $favoritos = new Favorito();

        try {
            $guardado = $favoritos->alternar($this->clienteId(), $productoId);
        } catch (Throwable $e) {
            error_log('[FavoritoController::alternar] ' . $e->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No pudimos guardar tu selección.'], 500);
            return;
        }

        $this->json([
            'ok'       => true,
            'favorito' => $guardado,
            'total'    => $favoritos->contar($this->clienteId()),
            'mensaje'  => $guardado ? 'Guardado en tu lista de deseos.' : 'Quitado de tu lista de deseos.',
        ]);
    }
}
