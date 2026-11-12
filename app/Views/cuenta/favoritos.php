<?php
$seccionCuenta = 'favoritos';
$favoritos = array_map(static fn(array $p): int => (int) $p['id'], $productos);
?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Guardadas</span>
                <h2>Mi lista de deseos</h2>
            </div>
        </div>

        <div class="cuenta">
            <?php require __DIR__ . '/../shared/menu-cuenta.php'; ?>

            <div>
                <?php if (empty($productos)): ?>
                    <div class="vacio">
                        <i class="fa-regular fa-heart"></i>
                        <h3>Tu lista está vacía</h3>
                        <p>Toca el corazón en cualquier pieza del catálogo para guardarla aquí.</p>
                        <a href="<?= BASE_URL ?>catalogo" class="btn btn-primario">Explorar el catálogo</a>
                    </div>
                <?php else: ?>
                    <p class="texto-tenue" style="margin-bottom:20px">
                        <?= count($productos) ?> pieza<?= count($productos) === 1 ? '' : 's' ?> guardada<?= count($productos) === 1 ? '' : 's' ?>
                    </p>
                    <div class="grid-productos">
                        <?php foreach ($productos as $p): ?>
                            <?php require __DIR__ . '/../shared/tarjeta-producto.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
