<?php
/**
 * Layout: encabezado del panel de administración.
 * Barra lateral con la navegación del panel y apertura del <main>.
 */
require_once __DIR__ . '/../../Models/Mensaje.php';
require_once __DIR__ . '/../../Models/Producto.php';

$seccionActual   = $seccion ?? '';
$mensajesPend    = (new Mensaje())->contarSinLeer();
$alertasStock    = (new Producto())->contarBajoStock();

$menuAdmin = [
    'Operación' => [
        'panel'      => ['admin/panel',      'fa-chart-line',    'Dashboard',   null],
        'pedidos'    => ['admin/pedidos',    'fa-bag-shopping',  'Pedidos',     null],
        'productos'  => ['admin/productos',  'fa-gem',           'Productos',   null],
        'inventario' => ['admin/inventario', 'fa-boxes-stacked', 'Inventario',  $alertasStock ?: null],
    ],
    'Negocio' => [
        'contabilidad' => ['admin/contabilidad', 'fa-calculator', 'Contabilidad', null],
        'cupones'      => ['admin/cupones',      'fa-ticket',     'Cupones',      null],
        'usuarios'     => ['admin/usuarios',     'fa-users',      'Clientes',     null],
        'mensajes'     => ['admin/mensajes',     'fa-envelope',   'Mensajes',     $mensajesPend ?: null],
    ],
];
?>
<!DOCTYPE html>
<html lang="es" data-tema="claro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo ?? 'Administración | ' . TIENDA_NOMBRE) ?></title>

    <script>
        (function () {
            try { var t = localStorage.getItem('sfes-tema-admin'); if (t) document.documentElement.dataset.tema = t; } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sfes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
    <link rel="icon" href="<?= BASE_URL ?>assets/img/sitio/logo-sfes.png">

    <script>
        window.SFES = {
            base: <?= json_encode(BASE_URL) ?>,
            csrf: <?= json_encode($csrfToken ?? '') ?>
        };
    </script>
</head>
<body class="admin">

<div class="velo" id="velo"></div>

<aside class="lateral" id="lateral">
    <a href="<?= BASE_URL ?>admin/panel" class="lateral__marca">
        <strong>SFES</strong>
        <span>Administración</span>
    </a>

    <?php foreach ($menuAdmin as $grupo => $opciones): ?>
        <p class="lateral__grupo"><?= e($grupo) ?></p>
        <nav>
            <?php foreach ($opciones as $clave => [$ruta, $icono, $texto, $pastilla]): ?>
                <a href="<?= BASE_URL . $ruta ?>" class="<?= $seccionActual === $clave ? 'activo' : '' ?>">
                    <i class="fa-solid <?= $icono ?>"></i> <?= e($texto) ?>
                    <?php if ($pastilla): ?><span class="pastilla"><?= (int) $pastilla ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>

    <div class="lateral__pie">
        <nav>
            <a href="<?= BASE_URL ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-shop"></i> Ver la tienda
            </a>
            <a href="<?= BASE_URL ?>logout">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </a>
        </nav>
        <p style="font-size:.7rem;color:var(--humo-500);padding:12px 12px 0">
            <?= e($_SESSION['admin_nombre'] ?? 'Administrador') ?>
        </p>
    </div>
</aside>

<main class="contenido">
    <?php if (!empty($flash)): ?>
        <div class="flash flash-<?= e($flash['tipo'] === 'aviso' ? 'aviso' : ($flash['tipo'] === 'error' ? 'error' : 'exito')) ?>">
            <i class="fa-solid fa-<?= $flash['tipo'] === 'error' ? 'circle-exclamation' : ($flash['tipo'] === 'aviso' ? 'triangle-exclamation' : 'circle-check') ?>"></i>
            <div><?= e($flash['mensaje']) ?></div>
        </div>
    <?php endif; ?>
