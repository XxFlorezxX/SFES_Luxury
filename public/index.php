<?php
/**
 * Front Controller — SFES Luxury
 * Único punto de entrada de la aplicación. Todas las peticiones llegan
 * aquí gracias al .htaccess y se reparten desde la tabla de rutas.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Ayuda.php';
require_once __DIR__ . '/../app/Core/Seguridad.php';
require_once __DIR__ . '/../app/Core/Router.php';

Seguridad::iniciarSesion();
Seguridad::cabeceras();

$router = new Router();

/* ═══════════════════════════════════════════════════════════════
   TIENDA PÚBLICA
   ═══════════════════════════════════════════════════════════════ */
$router->get('/',                'HomeController',          'index');
$router->get('/sobre-nosotros',  'SobreNosotrosController', 'index');
$router->get('/contacto',        'ContactoController',      'index');
$router->post('/contacto/enviar','ContactoController',      'enviar');

// Catálogo, filtros y búsqueda global
$router->get('/catalogo',              'CatalogoController', 'index');
$router->get('/catalogo/api',          'CatalogoController', 'filtrarJson');
$router->get('/buscar',                'CatalogoController', 'buscar');
$router->get('/buscar/sugerencias',    'CatalogoController', 'sugerenciasJson');
$router->get('/catalogo/recomendaciones/{id}', 'CatalogoController', 'recomendacionesJson');

// Ficha de producto
$router->get('/producto/{slug}',        'ProductoPublicoController', 'detalle');
$router->post('/producto/{id}/resena',  'ProductoPublicoController', 'guardarResena');

// Lista de deseos
$router->post('/favoritos/alternar', 'FavoritoController', 'alternar');
$router->get('/favoritos',           'FavoritoController', 'index');

/* ═══════════════════════════════════════════════════════════════
   CARRITO Y CHECKOUT
   ═══════════════════════════════════════════════════════════════ */
$router->get('/carrito',                  'CarritoController', 'index');
$router->get('/carrito/agregar/{id}',     'CarritoController', 'agregar');
$router->post('/carrito/agregar/{id}',    'CarritoController', 'agregar');
$router->post('/carrito/actualizar/{id}', 'CarritoController', 'actualizar');
$router->get('/carrito/eliminar/{id}',    'CarritoController', 'eliminar');
$router->get('/carrito/vaciar',           'CarritoController', 'vaciar');
$router->post('/carrito/cupon',           'CarritoController', 'aplicarCupon');
$router->post('/carrito/cupon/quitar',    'CarritoController', 'quitarCupon');
$router->post('/carrito/envio',           'CarritoController', 'calcularEnvio');
$router->get('/carrito/resumen',          'CarritoController', 'resumenJson');

$router->get('/checkout',                 'CheckoutController', 'index');
$router->post('/checkout/confirmar',      'CheckoutController', 'confirmar');
$router->get('/pedido/confirmado/{id}',   'CheckoutController', 'confirmado');

/* ═══════════════════════════════════════════════════════════════
   PERSONALIZAR CON IA + CHATBOT
   ═══════════════════════════════════════════════════════════════ */
$router->get('/personalizar',                 'PersonalizarController', 'index');
$router->post('/personalizar/generar',        'PersonalizarController', 'generarConcepto');
$router->post('/personalizar/generar-imagen', 'PersonalizarController', 'generarImagenPreview');
$router->post('/chatbot',                     'ChatbotController',      'responder');

/* ═══════════════════════════════════════════════════════════════
   AUTENTICACIÓN
   ═══════════════════════════════════════════════════════════════ */
// Administrador
$router->get('/login',   'AuthController', 'login');
$router->post('/login',  'AuthController', 'procesarLogin');
$router->get('/logout',  'AuthController', 'logout');

// Cliente
$router->get('/ingresar',  'AuthController', 'ingresar');
$router->post('/ingresar', 'AuthController', 'procesarIngreso');
$router->get('/registro',  'AuthController', 'registro');
$router->post('/registro', 'AuthController', 'procesarRegistro');
$router->get('/salir',     'AuthController', 'salir');

/* ═══════════════════════════════════════════════════════════════
   CUENTA DEL CLIENTE (requiere sesión de cliente)
   ═══════════════════════════════════════════════════════════════ */
$router->get('/mi-cuenta',             'CuentaController', 'miCuenta');
$router->post('/mi-cuenta/perfil',     'CuentaController', 'guardarPerfil');
$router->post('/mi-cuenta/direccion',  'CuentaController', 'guardarDireccion');
$router->post('/mi-cuenta/clave',      'CuentaController', 'cambiarClave');
$router->get('/mis-pedidos',           'CuentaController', 'misPedidos');
$router->get('/mis-pedidos/{id}',      'CuentaController', 'detallePedido');

/* ═══════════════════════════════════════════════════════════════
   PANEL DE ADMINISTRACIÓN (requiere sesión de administrador)
   ═══════════════════════════════════════════════════════════════ */
// Dashboard y analítica
$router->get('/admin',              'AdminController', 'index');
$router->get('/admin/panel',        'AdminController', 'index');
$router->get('/admin/panel/api',    'AdminController', 'metricasJson');

// Productos
$router->get('/admin/productos',               'ProductoController', 'index');
$router->post('/admin/productos/guardar',      'ProductoController', 'guardar');
$router->post('/admin/productos/eliminar',     'ProductoController', 'eliminar');
$router->get('/admin/productos/api/{id}',      'ProductoController', 'detalleJson');

// Inventario (kardex y ajustes)
$router->get('/admin/inventario',           'AdminInventarioController', 'index');
$router->post('/admin/inventario/ajustar',  'AdminInventarioController', 'ajustar');
$router->get('/admin/inventario/api/{id}',  'AdminInventarioController', 'kardexJson');

// Pedidos
$router->get('/admin/pedidos',                  'AdminPedidoController', 'index');
$router->get('/admin/pedidos/api',              'AdminPedidoController', 'listarJson');
$router->get('/admin/pedidos/api/{id}',         'AdminPedidoController', 'detalleJson');
$router->post('/admin/pedidos/api/{id}/estado', 'AdminPedidoController', 'actualizarEstadoJson');

// Usuarios
$router->get('/admin/usuarios',          'AdminUsuarioController', 'index');
$router->get('/admin/usuarios/api',      'AdminUsuarioController', 'listarJson');
$router->get('/admin/usuarios/api/{id}', 'AdminUsuarioController', 'detalleJson');

// Cupones
$router->get('/admin/cupones',           'AdminCuponController', 'index');
$router->post('/admin/cupones/guardar',  'AdminCuponController', 'guardar');
$router->post('/admin/cupones/eliminar', 'AdminCuponController', 'eliminar');

// Contabilidad
$router->get('/admin/contabilidad',            'AdminContabilidadController', 'index');
$router->get('/admin/contabilidad/api',        'AdminContabilidadController', 'seriesJson');
$router->post('/admin/contabilidad/gasto',     'AdminContabilidadController', 'guardarGasto');
$router->post('/admin/contabilidad/eliminar',  'AdminContabilidadController', 'eliminarGasto');

// Mensajes de contacto
$router->get('/admin/mensajes',        'AdminMensajeController', 'index');
$router->post('/admin/mensajes/leido', 'AdminMensajeController', 'marcarLeido');

/* ═══════════════════════════════════════════════════════════════
   DESPACHO
   ═══════════════════════════════════════════════════════════════ */
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Quita del path la subcarpeta donde está instalado el proyecto
// (BASE_URL se detecta solo, así funciona en la raíz o en /SFES_Luxury/public/).
$base = rtrim(BASE_URL, '/');
// Comparación sin distinguir mayúsculas: en Windows (XAMPP) Apache acepta
// /sfes_luxury/ y /SFES_Luxury/ como la misma carpeta.
if ($base !== '' && stripos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}

$router->despachar($uri === '' ? '/' : $uri, $metodo);
