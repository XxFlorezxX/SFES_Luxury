<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Resena.php';
require_once __DIR__ . '/../Models/Usuario.php';

/**
 * SobreNosotrosController
 * Página institucional. Las cifras que muestra (piezas en catálogo,
 * clientes, calificación promedio) se leen de la base de datos, no son
 * números escritos a mano en el HTML.
 */
class SobreNosotrosController extends Controller
{
    public function index(): void
    {
        $this->vista('sobrenosotros/index', [
            'titulo'      => 'Nuestra historia | ' . TIENDA_NOMBRE,
            'descripcion' => 'Joyería en oro y plata 925 diseñada y ensamblada en Colombia.',
            'cifras'      => [
                'piezas'     => (new Producto())->contar(true),
                'clientes'   => (new Usuario())->contarClientes(),
                'promedio'   => (new Resena())->calificacionPromedioTienda(),
                'categorias' => count((new Producto())->categorias()),
            ],
            'paginaActual' => 'nosotros',
            'jsPagina'     => ['tienda'],
        ]);
    }
}
