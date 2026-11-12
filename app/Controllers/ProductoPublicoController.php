<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Resena.php';
require_once __DIR__ . '/../Models/Favorito.php';

/**
 * ProductoPublicoController
 * Ficha de producto: galería, descripción, disponibilidad, reseñas de
 * clientes y piezas relacionadas.
 *
 * Antes no existía esta página: desde el inicio, "Ver más" agregaba la
 * pieza al carrito directamente, sin que el cliente pudiera leer nada
 * sobre ella. Es la pantalla donde realmente se decide una compra.
 */
class ProductoPublicoController extends Controller
{
    public function detalle(string $slug): void
    {
        $productoModelo = new Producto();
        $producto = $productoModelo->buscarPorSlug($slug);

        if (!$producto || (int) $producto['activo'] !== 1) {
            http_response_code(404);
            $this->vista('errores/404', [
                'titulo'       => 'Pieza no encontrada | ' . TIENDA_NOMBRE,
                'codigoError'  => 404,
                'tituloError'  => 'Esta pieza ya no está disponible',
                'mensajeError' => 'Puede que se haya agotado o cambiado de nombre. Explora el catálogo completo.',
                'paginaActual' => 'catalogo',
            ]);
            return;
        }

        $productoModelo->registrarVista((int) $producto['id']);

        $resenaModelo = new Resena();
        $clienteId    = $this->clienteId();

        $this->vista('producto/detalle', [
            'titulo'        => $producto['nombre'] . ' | ' . TIENDA_NOMBRE,
            'descripcion'   => recortar($producto['descripcion'], 155),
            'producto'      => $producto,
            'resenas'       => $resenaModelo->deProducto((int) $producto['id']),
            'resumenResenas'=> $resenaModelo->resumen((int) $producto['id']),
            'miResena'      => $clienteId ? $resenaModelo->deUsuarioYProducto($clienteId, (int) $producto['id']) : null,
            'puedeResenar'  => (bool) $clienteId,
            'compro'        => $clienteId ? $resenaModelo->compro($clienteId, (int) $producto['id']) : false,
            'relacionados'  => $productoModelo->recomendaciones([(int) $producto['id']], 4),
            'esFavorito'    => $clienteId ? (new Favorito())->existe($clienteId, (int) $producto['id']) : false,
            'paginaActual'  => 'catalogo',
            'jsPagina'      => ['tienda', 'producto'],
        ]);
    }

    /** Guarda (o actualiza) la reseña del cliente sobre esta pieza. */
    public function guardarResena(string $id): void
    {
        $this->requerirCliente();
        $this->exigirCsrf('producto/' . $this->slugDe((int) $id));

        $productoId   = (int) $id;
        $calificacion = $this->entero($_POST, 'calificacion', 0);
        $titulo       = $this->texto($_POST, 'titulo', 120);
        $comentario   = $this->texto($_POST, 'comentario', 1500);

        $slug = $this->slugDe($productoId);

        if ($calificacion < 1 || $calificacion > 5) {
            $this->flash('Selecciona una calificación de 1 a 5 estrellas.', 'error');
            $this->redireccionar('producto/' . $slug);
            return;
        }

        if (mb_strlen($comentario) < 10) {
            $this->flash('Cuéntanos un poco más: al menos 10 caracteres.', 'error');
            $this->redireccionar('producto/' . $slug);
            return;
        }

        $ok = (new Resena())->guardar($this->clienteId(), $productoId, $calificacion, $titulo, $comentario);

        $this->flash(
            $ok ? '¡Gracias por tu reseña!' : 'No pudimos guardar tu reseña, intenta de nuevo.',
            $ok ? 'exito' : 'error'
        );
        $this->redireccionar('producto/' . $slug);
    }

    private function slugDe(int $productoId): string
    {
        $producto = (new Producto())->buscarPorId($productoId);
        return $producto['slug'] ?? '';
    }
}
