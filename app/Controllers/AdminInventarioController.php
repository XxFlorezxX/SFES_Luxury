<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Inventario.php';

/**
 * AdminInventarioController
 * Control de existencias: valor del inventario, alertas de reposición,
 * ajustes manuales y kardex (historial de movimientos por pieza).
 *
 * Antes el stock se editaba directamente en el formulario del producto
 * sin dejar rastro de quién lo cambió ni por qué. Ahora todo ajuste
 * exige un motivo y queda registrado.
 */
class AdminInventarioController extends Controller
{
    public function index(): void
    {
        $this->requerirAdmin();

        $productos  = new Producto();
        $inventario = new Inventario();

        $filtros = [
            'busqueda'          => $this->texto($_GET, 'buscar', 100),
            'categoria'         => $this->texto($_GET, 'categoria', 60) ?: 'Todos',
            'orden'             => 'nombre',
            'pagina'            => max(1, $this->entero($_GET, 'pagina', 1)),
            'por_pagina'        => 24,
            'incluir_inactivos' => true,
        ];

        $resultado = $productos->filtrar($filtros);
        $hasta     = date('Y-m-d');
        $desde     = date('Y-m-d', strtotime('-29 days'));

        $this->vistaAdmin('admin/inventario', [
            'titulo'      => 'Inventario | ' . TIENDA_NOMBRE,
            'seccion'     => 'inventario',
            'resultado'   => $resultado,
            'productos'   => $resultado['productos'],
            'filtros'     => $filtros,
            'categorias'  => $productos->categorias(),
            'valor'       => $productos->valorInventario(),
            'bajoStock'   => $productos->bajoStock(50),
            'totalStock'  => $productos->sumaStock(),
            'movimientos' => $inventario->ultimos(20),
            'flujo'       => $inventario->flujoPorDia($desde, $hasta),
            'jsPagina'    => ['graficas', 'admin-inventario'],
        ]);
    }

    /** Ajuste manual de existencias (reposición, merma, corrección de conteo). */
    public function ajustar(): void
    {
        $this->requerirAdmin();
        $this->exigirCsrf('admin/inventario');

        $productoId = $this->entero($_POST, 'producto_id', 0);
        $nuevoStock = $this->entero($_POST, 'stock', -1);
        $motivo     = $this->texto($_POST, 'motivo', 120);

        if ($productoId <= 0 || $nuevoStock < 0) {
            $this->flash('Indica un producto y una cantidad válida.', 'error');
            $this->redireccionar('admin/inventario');
            return;
        }

        if (mb_strlen($motivo) < 3) {
            $this->flash('Escribe el motivo del ajuste (queda registrado en el historial).', 'error');
            $this->redireccionar('admin/inventario');
            return;
        }

        $resultado = (new Inventario())->ajustar($productoId, $nuevoStock, $motivo, $this->adminId());

        $this->flash($resultado['mensaje'], $resultado['ok'] ? 'exito' : 'aviso');
        $this->redireccionar('admin/inventario');
    }

    /** Historial de movimientos de una pieza (modal del panel). */
    public function kardexJson(string $id): void
    {
        $this->requerirAdminJson();

        $producto = (new Producto())->buscarPorId((int) $id);

        if (!$producto) {
            $this->json(['ok' => false, 'mensaje' => 'El producto no existe.'], 404);
            return;
        }

        $movimientos = array_map(static fn(array $m): array => [
            'fecha'  => fecha_corta($m['creado_en']) . ' · ' . date('H:i', strtotime($m['creado_en'])),
            'tipo'   => $m['tipo'],
            'cantidad' => (int) $m['cantidad'],
            'stock'  => (int) $m['stock_resultante'],
            'motivo' => $m['motivo'],
        ], (new Inventario())->kardex((int) $id, 60));

        $this->json([
            'ok' => true,
            'producto' => [
                'id'           => (int) $producto['id'],
                'nombre'       => $producto['nombre'],
                'sku'          => $producto['sku'],
                'stock'        => (int) $producto['stock'],
                'stock_minimo' => (int) $producto['stock_minimo'],
                'imagen'       => imagen_producto($producto['imagen']),
                'costo'        => moneda($producto['costo']),
                'precio'       => moneda($producto['precio']),
            ],
            'movimientos' => $movimientos,
        ]);
    }
}
