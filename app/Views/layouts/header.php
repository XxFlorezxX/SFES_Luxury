<?php
/**
 * Layout: encabezado público
 * Compartido por todas las páginas de la tienda. Contiene la barra de
 * anuncio, el encabezado con navegación, buscador global e iconos de
 * cuenta/lista de deseos/bolsa, y el menú móvil.
 */
require_once __DIR__ . '/../../Models/Carrito.php';
require_once __DIR__ . '/../../Models/Favorito.php';

$carritoLayout   = new Carrito();
$articulosBolsa  = $carritoLayout->totalArticulos();
$clienteSesion   = !empty($_SESSION['cliente_id']);
$favoritosLayout = $clienteSesion ? (new Favorito())->contar((int) $_SESSION['cliente_id']) : 0;
$pagina          = $paginaActual ?? '';

$enlaces = [
    ''               => 'Inicio',
    'catalogo'       => 'Catálogo',
    'personalizar'   => 'Personalizar con IA',
    'sobre-nosotros' => 'Nosotros',
    'contacto'       => 'Contacto',
];
$clavePagina = ['inicio' => '', 'catalogo' => 'catalogo', 'personalizar' => 'personalizar', 'nosotros' => 'sobre-nosotros', 'contacto' => 'contacto'][$pagina] ?? null;
?>
<!DOCTYPE html>
<html lang="es" data-tema="oscuro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($descripcion ?? 'Joyería en oro y plata 925 hecha en Colombia. Anillos, aretes, cadenas y pulseras con envío nacional.') ?>">
    <meta name="theme-color" content="#0B0D10">
    <title><?= e($titulo ?? TIENDA_NOMBRE) ?></title>

    <!-- Tema guardado: se aplica antes de pintar para que no haya parpadeo -->
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sfes-tema');
                if (t) document.documentElement.dataset.tema = t;
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sfes.css">
    <link rel="icon" href="<?= BASE_URL ?>assets/img/sitio/logo-sfes.png">

    <script>
        window.SFES = {
            base:   <?= json_encode(BASE_URL) ?>,
            csrf:   <?= json_encode($csrfToken ?? '') ?>,
            sesion: <?= $clienteSesion ? 'true' : 'false' ?>,
            flash:  <?= json_encode($flash ?? null, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
</head>
<body>

<div class="barra-anuncio">
    <i class="fa-solid fa-gem"></i>
    Envío gratis en compras superiores a $200.000 · Piezas en oro y plata 925
</div>

<header class="encabezado">
    <div class="contenedor encabezado__fila">
        <a href="<?= BASE_URL ?>" class="logo" aria-label="<?= e(TIENDA_NOMBRE) ?> — inicio">
            <strong>SFES</strong>
            <span>Luxury</span>
        </a>

        <nav class="nav" aria-label="Navegación principal">
            <?php foreach ($enlaces as $ruta => $texto): ?>
                <a href="<?= BASE_URL . $ruta ?>" class="<?= $clavePagina === $ruta ? 'activo' : '' ?>"><?= e($texto) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="buscador">
            <form action="<?= BASE_URL ?>buscar" method="get" role="search">
                <i class="fa-solid fa-magnifying-glass lupa" aria-hidden="true"></i>
                <label for="buscador-input" class="solo-lectores">Buscar joyas</label>
                <input type="search" id="buscador-input" name="q" placeholder="Buscar anillos, aretes, cadenas…"
                       autocomplete="off" value="<?= e($terminoBusqueda ?? '') ?>">
                <button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right"></i></button>
            </form>
            <div class="sugerencias" id="sugerencias" role="listbox" aria-label="Sugerencias de búsqueda"></div>
        </div>

        <div class="encabezado__acciones">
            <button class="icono-btn" id="btn-tema" type="button" aria-label="Cambiar tema">
                <i class="fa-solid fa-sun"></i>
            </button>

            <a href="<?= BASE_URL ?>favoritos" class="icono-btn" aria-label="Lista de deseos">
                <i class="fa-regular fa-heart"></i>
                <span class="contador" id="contador-favoritos" data-cero="<?= $favoritosLayout > 0 ? '0' : '1' ?>"><?= $favoritosLayout > 0 ? $favoritosLayout : '' ?></span>
            </a>

            <a href="<?= BASE_URL . ($clienteSesion ? 'mi-cuenta' : 'ingresar') ?>" class="icono-btn"
               aria-label="<?= $clienteSesion ? 'Mi cuenta' : 'Iniciar sesión' ?>">
                <i class="fa-<?= $clienteSesion ? 'solid' : 'regular' ?> fa-user"></i>
            </a>

            <a href="<?= BASE_URL ?>carrito" class="icono-btn" aria-label="Mi bolsa">
                <i class="fa-solid fa-bag-shopping"></i>
                <span class="contador" id="contador-carrito" data-cero="<?= $articulosBolsa > 0 ? '0' : '1' ?>"><?= $articulosBolsa > 0 ? $articulosBolsa : '' ?></span>
            </a>

            <button class="icono-btn hamburguesa" id="abrir-menu" type="button" aria-label="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<!-- Menú móvil -->
<div class="menu-movil" id="menu-movil">
    <div class="menu-movil__cabecera">
        <span class="logo"><strong>SFES</strong><span>Luxury</span></span>
        <button class="icono-btn" id="cerrar-menu" type="button" aria-label="Cerrar menú">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <form action="<?= BASE_URL ?>buscar" method="get" class="campo" role="search">
        <label for="buscar-movil" class="solo-lectores">Buscar joyas</label>
        <input type="search" id="buscar-movil" name="q" placeholder="Buscar joyas…">
    </form>

    <nav aria-label="Navegación móvil">
        <?php foreach ($enlaces as $ruta => $texto): ?>
            <a href="<?= BASE_URL . $ruta ?>" class="<?= $clavePagina === $ruta ? 'activo' : '' ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
        <?php if ($clienteSesion): ?>
            <a href="<?= BASE_URL ?>mi-cuenta">Mi cuenta</a>
            <a href="<?= BASE_URL ?>mis-pedidos">Mis pedidos</a>
            <a href="<?= BASE_URL ?>favoritos">Lista de deseos</a>
            <a href="<?= BASE_URL ?>salir">Cerrar sesión</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>ingresar">Iniciar sesión</a>
            <a href="<?= BASE_URL ?>registro">Crear cuenta</a>
        <?php endif; ?>
    </nav>
</div>

<main>
