<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Favorito.php';

/**
 * CatalogoController
 * Catálogo con filtros avanzados, orden y paginación.
 *
 * El filtrado ocurre en el servidor (SQL con sentencias preparadas), no
 * ocultando tarjetas con JavaScript: así la paginación es real, los
 * resultados son compartibles por URL y el navegador solo descarga lo
 * que va a mostrar.
 */
class CatalogoController extends Controller
{
    public function index(): void
    {
        $productos = new Producto();
        $filtros   = $this->leerFiltros();
        $resultado = $productos->filtrar($filtros);

        $favoritos = $this->clienteId() ? (new Favorito())->idsDeUsuario($this->clienteId()) : [];

        $this->vista('catalogo/index', [
            'titulo'        => 'Catálogo | ' . TIENDA_NOMBRE,
            'descripcion'   => 'Explora anillos, aretes, cadenas y pulseras en oro y plata 925.',
            'resultado'     => $resultado,
            'productos'     => $resultado['productos'],
            'filtros'       => $filtros,
            'categorias'    => $productos->categorias(),
            'materiales'    => $productos->materiales(),
            'conteos'       => $productos->conteoPorCategoria(),
            'rangoPrecios'  => $productos->rangoPrecios(),
            'favoritos'     => $favoritos,
            'ordenes'       => $this->etiquetasOrden(),
            'paginaActual'  => 'catalogo',
            'jsPagina'      => ['tienda', 'catalogo'],
        ]);
    }

    /** Resultados en JSON — usado por el filtrado sin recargar la página. */
    public function filtrarJson(): void
    {
        $productos = new Producto();
        $resultado = $productos->filtrar($this->leerFiltros());
        $favoritos = $this->clienteId() ? (new Favorito())->idsDeUsuario($this->clienteId()) : [];

        $lista = array_map(static function (array $p) use ($favoritos): array {
            return [
                'id'            => (int) $p['id'],
                'nombre'        => $p['nombre'],
                'slug'          => $p['slug'],
                'categoria'     => $p['categoria'],
                'material'      => $p['material'],
                'precio'        => (float) $p['precio'],
                'precio_texto'  => moneda($p['precio']),
                'precio_antes'  => $p['precio_antes'] !== null ? moneda($p['precio_antes']) : null,
                'imagen'        => imagen_producto($p['imagen']),
                'url'           => url('producto/' . $p['slug']),
                'stock'         => (int) $p['stock'],
                'calificacion'  => round((float) ($p['calificacion'] ?? 0), 1),
                'total_resenas' => (int) ($p['total_resenas'] ?? 0),
                'favorito'      => in_array((int) $p['id'], $favoritos, true),
            ];
        }, $resultado['productos']);

        $this->json([
            'ok'        => true,
            'productos' => $lista,
            'total'     => $resultado['total'],
            'pagina'    => $resultado['pagina'],
            'paginas'   => $resultado['paginas'],
        ]);
    }

    /** Búsqueda global desde el buscador del encabezado. */
    public function buscar(): void
    {
        $termino = $this->texto($_GET, 'q', 100);

        if ($termino === '') {
            $this->redireccionar('catalogo');
            return;
        }

        $productos = new Producto();
        $filtros   = $this->leerFiltros();
        $filtros['busqueda'] = $termino;

        $resultado = $productos->filtrar($filtros);
        $favoritos = $this->clienteId() ? (new Favorito())->idsDeUsuario($this->clienteId()) : [];

        $this->vista('catalogo/index', [
            'titulo'        => 'Resultados para "' . $termino . '" | ' . TIENDA_NOMBRE,
            'resultado'     => $resultado,
            'productos'     => $resultado['productos'],
            'filtros'       => $filtros,
            'terminoBusqueda' => $termino,
            'categorias'    => $productos->categorias(),
            'materiales'    => $productos->materiales(),
            'conteos'       => $productos->conteoPorCategoria(),
            'rangoPrecios'  => $productos->rangoPrecios(),
            'favoritos'     => $favoritos,
            'ordenes'       => $this->etiquetasOrden(),
            'paginaActual'  => 'catalogo',
            'jsPagina'      => ['tienda', 'catalogo'],
        ]);
    }

    /** Autocompletado del buscador (máximo 6 sugerencias). */
    public function sugerenciasJson(): void
    {
        $termino = $this->texto($_GET, 'q', 60);

        if (mb_strlen($termino) < 2) {
            $this->json(['ok' => true, 'sugerencias' => []]);
            return;
        }

        $sugerencias = array_map(static fn(array $p): array => [
            'nombre'    => $p['nombre'],
            'categoria' => $p['categoria'],
            'precio'    => moneda($p['precio']),
            'imagen'    => imagen_producto($p['imagen']),
            'url'       => url('producto/' . $p['slug']),
        ], (new Producto())->sugerencias($termino));

        $this->json(['ok' => true, 'sugerencias' => $sugerencias]);
    }

    /** Recomendaciones para la vista rápida de una pieza. */
    public function recomendacionesJson(string $id): void
    {
        $recomendados = (new Producto())->recomendaciones([(int) $id], 4);

        $this->json([
            'ok'              => true,
            'recomendaciones' => array_map(static fn(array $p): array => [
                'id'     => (int) $p['id'],
                'nombre' => $p['nombre'],
                'precio' => moneda($p['precio']),
                'imagen' => imagen_producto($p['imagen']),
                'url'    => url('producto/' . $p['slug']),
            ], $recomendados),
        ]);
    }

    /* ══════════════════════════ INTERNOS ═════════════════════════ */

    /** Lee y sanea los filtros desde la query string. */
    private function leerFiltros(): array
    {
        $orden = $this->texto($_GET, 'orden', 20);

        return [
            'categoria'      => $this->texto($_GET, 'categoria', 60) ?: 'Todos',
            'material'       => $this->texto($_GET, 'material', 60) ?: 'Todos',
            'busqueda'       => $this->texto($_GET, 'buscar', 100) ?: $this->texto($_GET, 'q', 100),
            'precio_min'     => $this->texto($_GET, 'precio_min', 12),
            'precio_max'     => $this->texto($_GET, 'precio_max', 12),
            'disponibilidad' => in_array($_GET['disponibilidad'] ?? '', ['disponible', 'agotado', 'oferta'], true)
                ? $_GET['disponibilidad'] : 'todos',
            'orden'          => array_key_exists($orden, Producto::ORDENES) ? $orden : 'relevancia',
            'pagina'         => max(1, $this->entero($_GET, 'pagina', 1)),
            'por_pagina'     => 12,
        ];
    }

    private function etiquetasOrden(): array
    {
        return [
            'relevancia'  => 'Recomendados',
            'nuevo'       => 'Lo más nuevo',
            'vendidos'    => 'Más vendidos',
            'precio_asc'  => 'Precio: menor a mayor',
            'precio_desc' => 'Precio: mayor a menor',
            'nombre'      => 'Nombre A-Z',
        ];
    }
}
