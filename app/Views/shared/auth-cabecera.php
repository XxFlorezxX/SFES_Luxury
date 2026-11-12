<?php
/**
 * Cabecera de las pantallas de acceso (login, registro).
 * Son páginas a pantalla completa sin el encabezado de la tienda, así
 * que traen su propio <head> mínimo.
 */
?>
<!DOCTYPE html>
<html lang="es" data-tema="oscuro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($titulo ?? TIENDA_NOMBRE) ?></title>

    <script>
        (function () {
            try { var t = localStorage.getItem('sfes-tema'); if (t) document.documentElement.dataset.tema = t; } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sfes.css">
    <link rel="icon" href="<?= BASE_URL ?>assets/img/sitio/logo-sfes.png">
</head>
<body>
<div class="auth">
    <aside class="auth__lateral">
        <a href="<?= BASE_URL ?>" class="logo"><strong>SFES</strong><span>Luxury</span></a>

        <div>
            <h2><?= e($lateralTitulo ?? 'Joyería en oro y plata, hecha en Colombia') ?></h2>
            <p><?= e($lateralTexto ?? 'Piezas en plata 925 y baño de oro 18k, ensambladas a mano y pensadas para durar.') ?></p>

            <ul class="auth__lista">
                <?php foreach ($lateralPuntos ?? ['Sigue tus pedidos en tiempo real', 'Guarda tus piezas favoritas', 'Tu dirección lista para la próxima compra'] as $punto): ?>
                    <li><i class="fa-solid fa-circle-check"></i> <?= e($punto) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <p style="font-size:.78rem;opacity:.6">&copy; <?= date('Y') ?> <?= e(TIENDA_NOMBRE) ?></p>
    </aside>

    <main class="auth__panel">
        <div class="auth__caja">
