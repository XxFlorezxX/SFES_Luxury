<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Resena.php';
require_once __DIR__ . '/../Models/Favorito.php';

/**
 * HomeController
 * Página de inicio. Todo lo que muestra sale de la base de datos:
 * destacados, novedades, categorías y reseñas reales. No hay ningún
 * producto escrito a mano en la vista.
 */
class HomeController extends Controller
{
    public function index(): void
    {
        $productos = new Producto();
        $resenas   = new Resena();

        $favoritos = [];
        if ($this->clienteId()) {
            $favoritos = (new Favorito())->idsDeUsuario($this->clienteId());
        }

        $destacados = $productos->filtrar([
            'solo_destacados' => true,
            'disponibilidad'  => 'disponible',
            'por_pagina'      => 8,
        ])['productos'];

        // Si aún no hay piezas marcadas como destacadas, se muestran las
        // más recientes para que el inicio nunca aparezca vacío.
        if (count($destacados) < 4) {
            $destacados = $productos->destacados(8);
        }

        $novedades = $productos->filtrar([
            'orden'          => 'nuevo',
            'disponibilidad' => 'disponible',
            'por_pagina'     => 4,
        ])['productos'];

        $this->vista('home/index', [
            'titulo'         => TIENDA_NOMBRE . ' — Joyería en oro y plata',
            'descripcion'    => 'Piezas en oro y plata 925 hechas en Colombia: anillos, aretes, cadenas y pulseras con envío a todo el país.',
            'destacados'     => $destacados,
            'novedades'      => $novedades,
            'masVendidos'    => $productos->masVendidos(4),
            'categorias'     => $productos->categorias(),
            'conteos'        => $productos->conteoPorCategoria(),
            'ultimasResenas' => $resenas->ultimas(6),
            'promedioTienda' => $resenas->calificacionPromedioTienda(),
            'favoritos'      => $favoritos,
            'paginaActual'   => 'inicio',
            'jsPagina'       => ['tienda'],
        ]);
    }
}
