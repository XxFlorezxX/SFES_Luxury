<?php
/**
 * Componente: menú lateral de la cuenta del cliente.
 * Espera $seccionCuenta con la sección activa.
 */
$seccionCuenta = $seccionCuenta ?? '';
$opciones = [
    'cuenta'    => ['mi-cuenta',  'fa-user',           'Mi cuenta'],
    'pedidos'   => ['mis-pedidos','fa-box-open',       'Mis pedidos'],
    'favoritos' => ['favoritos',  'fa-heart',          'Lista de deseos'],
    'salir'     => ['salir',      'fa-right-from-bracket', 'Cerrar sesión'],
];
?>
<nav class="cuenta__menu" aria-label="Menú de la cuenta">
    <?php foreach ($opciones as $clave => [$ruta, $icono, $texto]): ?>
        <a href="<?= BASE_URL . $ruta ?>" class="<?= $seccionCuenta === $clave ? 'activo' : '' ?>">
            <i class="fa-solid <?= $icono ?>"></i> <?= $texto ?>
        </a>
    <?php endforeach; ?>
</nav>
