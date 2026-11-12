<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Seguridad.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Inventario.php';

/**
 * ProductoController
 * CRUD de productos del panel de administración.
 *
 * Cambios de seguridad frente a la versión anterior:
 *  · token CSRF obligatorio en guardar y eliminar,
 *  · eliminación por POST (antes bastaba visitar un enlace GET),
 *  · las imágenes se validan por contenido real, no por extensión,
 *  · el stock inicial y sus cambios quedan registrados en el kardex.
 */
class ProductoController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        $productos = new Producto();

        $filtros = [
            'busqueda'          => $this->texto($_GET, 'buscar', 100),
            'categoria'         => $this->texto($_GET, 'categoria', 60) ?: 'Todos',
            'orden'             => 'nuevo',
            'pagina'            => max(1, $this->entero($_GET, 'pagina', 1)),
            'por_pagina'        => 24,
            'incluir_inactivos' => true,
        ];

        $resultado = $productos->filtrar($filtros);

        $this->vistaAdmin('admin/productos', [
            'titulo'      => 'Productos | ' . TIENDA_NOMBRE,
            'seccion'     => 'productos',
            'resultado'   => $resultado,
            'productos'   => $resultado['productos'],
            'filtros'     => $filtros,
            'categorias'  => $productos->categorias(),
            'materiales'  => $productos->materiales(),
            'inventario'  => $productos->valorInventario(),
            'bajoStock'   => $productos->contarBajoStock(),
            'jsPagina'    => ['admin-productos'],
        ]);
    }

    /** Datos de una pieza en JSON (rellena el formulario de edición). */
    public function detalleJson(string $id): void
    {
        $this->requerirAdminJson();

        $producto = (new Producto())->buscarPorId((int) $id);

        if (!$producto) {
            $this->json(['ok' => false, 'mensaje' => 'El producto no existe.'], 404);
            return;
        }

        $producto['imagen_url'] = imagen_producto($producto['imagen']);
        $this->json(['ok' => true, 'producto' => $producto]);
    }

    /** Crea o actualiza una pieza según venga o no un id en el formulario. */
    public function guardar(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/productos');

        $id          = $this->entero($_POST, 'id', 0);
        $nombre      = $this->texto($_POST, 'nombre', 150);
        $descripcion = $this->texto($_POST, 'descripcion', 4000);
        $categoria   = $this->texto($_POST, 'categoria', 60);
        $material    = $this->texto($_POST, 'material', 60) ?: 'Baño de oro';
        $sku         = strtoupper($this->texto($_POST, 'sku', 40));
        $precio      = $this->decimal($_POST, 'precio');
        $precioAntes = $this->decimal($_POST, 'precio_antes');
        $costo       = $this->decimal($_POST, 'costo');
        $peso        = $this->decimal($_POST, 'peso_gramos');
        $stock       = max(0, $this->entero($_POST, 'stock', 0));
        $stockMinimo = max(0, $this->entero($_POST, 'stock_minimo', 3));
        $destacado   = isset($_POST['destacado']) ? 1 : 0;
        $activo      = isset($_POST['activo']) ? 1 : 0;

        // ── Validación ──
        $errores = [];
        if (mb_strlen($nombre) < 3)        $errores[] = 'El nombre debe tener al menos 3 caracteres.';
        if (mb_strlen($descripcion) < 10)  $errores[] = 'La descripción debe tener al menos 10 caracteres.';
        if ($categoria === '')             $errores[] = 'Selecciona una categoría.';
        if ($precio <= 0)                  $errores[] = 'El precio debe ser mayor a cero.';
        if ($costo < 0)                    $errores[] = 'El costo no puede ser negativo.';
        if ($costo > $precio)              $errores[] = 'El costo no puede superar al precio de venta.';
        if ($precioAntes > 0 && $precioAntes <= $precio) {
            $errores[] = 'El "precio antes" debe ser mayor que el precio actual para mostrarse como oferta.';
        }

        if ($errores) {
            $this->flash(implode(' ', $errores), 'error');
            $this->redireccionar('admin/productos');
            return;
        }

        $productoModelo = new Producto();
        $anterior = $id > 0 ? $productoModelo->buscarPorId($id) : null;

        if ($id > 0 && !$anterior) {
            $this->flash('El producto que intentas editar ya no existe.', 'error');
            $this->redireccionar('admin/productos');
            return;
        }

        // ── Imagen ──
        $imagen = $anterior['imagen'] ?? 'assets/img/productos/no-image.png';

        if (!empty($_FILES['imagen']['name'])) {
            $subida = Seguridad::guardarImagen(
                $_FILES['imagen'],
                dirname(__DIR__, 2) . '/public/' . RUTA_UPLOADS
            );

            if ($subida['ok']) {
                if ($anterior) {
                    Seguridad::borrarImagen($anterior['imagen']);
                }
                $imagen = $subida['ruta'];
            } elseif (($subida['error'] ?? '') !== 'sin_archivo') {
                $this->flash('Imagen no guardada: ' . $subida['error'], 'error');
                $this->redireccionar('admin/productos');
                return;
            }
        }

        $datos = [
            'sku'          => $sku,
            'nombre'       => $nombre,
            'slug'         => $productoModelo->slugUnico($nombre, $id),
            'descripcion'  => $descripcion,
            'precio'       => $precio,
            'precio_antes' => $precioAntes > 0 ? $precioAntes : null,
            'costo'        => $costo,
            'categoria'    => $categoria,
            'material'     => $material,
            'peso_gramos'  => $peso > 0 ? $peso : null,
            'stock'        => $stock,
            'stock_minimo' => $stockMinimo,
            'imagen'       => $imagen,
            'destacado'    => $destacado,
            'activo'       => $activo,
        ];

        $inventario = new Inventario();

        try {
            if ($id > 0) {
                $stockAnterior = (int) $anterior['stock'];
                $productoModelo->actualizar($id, $datos);

                if ($stock !== $stockAnterior) {
                    $diferencia = $stock - $stockAnterior;
                    $inventario->registrar(
                        $id,
                        $diferencia > 0 ? 'entrada' : 'salida',
                        abs($diferencia),
                        $stock,
                        'Edición de producto desde el panel',
                        null,
                        $this->adminId()
                    );
                }

                $this->flash('Producto actualizado correctamente.', 'exito');
            } else {
                $nuevoId = $productoModelo->crear($datos);

                if ($stock > 0) {
                    $inventario->registrar($nuevoId, 'entrada', $stock, $stock, 'Stock inicial', null, $this->adminId());
                }

                $this->flash('Producto agregado al catálogo.', 'exito');
            }
        } catch (Throwable $e) {
            error_log('[ProductoController::guardar] ' . $e->getMessage());
            $mensaje = str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')
                ? 'Ya existe otra pieza con ese SKU.'
                : 'No se pudo guardar el producto.';
            $this->flash($mensaje, 'error');
        }

        $this->redireccionar('admin/productos');
    }

    /**
     * Elimina una pieza. Si ya tuvo ventas se desactiva en lugar de
     * borrarse, para no romper el historial de pedidos ni la contabilidad.
     */
    public function eliminar(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/productos');

        $id = $this->entero($_POST, 'id', 0);
        $productoModelo = new Producto();
        $producto = $productoModelo->buscarPorId($id);

        if (!$producto) {
            $this->flash('El producto ya no existe.', 'error');
            $this->redireccionar('admin/productos');
            return;
        }

        $resultado = $productoModelo->eliminar($id);

        if ($resultado === 'eliminado') {
            Seguridad::borrarImagen($producto['imagen']);
            $this->flash('Producto eliminado del catálogo.', 'exito');
        } else {
            $this->flash('"' . $producto['nombre'] . '" tiene ventas registradas: se desactivó en lugar de borrarse, para conservar el historial de pedidos.', 'aviso');
        }

        $this->redireccionar('admin/productos');
    }
}
