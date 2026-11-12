<?php
/**
 * Componente: tarjeta de producto
 * Se reutiliza en inicio, catálogo, favoritos, relacionados y
 * recomendaciones del carrito. Antes este bloque estaba copiado —y
 * ligeramente distinto— en cada una de esas vistas.
 *
 * Espera:
 *   $p          array del producto
 *   $favoritos  array opcional de IDs guardados por el cliente
 */
$favoritosTarjeta = $favoritos ?? [];
$esFav      = in_array((int) $p['id'], $favoritosTarjeta, true);
$stock      = (int) ($p['stock'] ?? 0);
$enOferta   = !empty($p['precio_antes']) && (float) $p['precio_antes'] > (float) $p['precio'];
$calif      = (float) ($p['calificacion'] ?? 0);
$totalRes   = (int) ($p['total_resenas'] ?? 0);
$urlFicha   = BASE_URL . 'producto/' . e($p['slug'] ?? '');
$esNueva    = !empty($p['creado_en']) && strtotime($p['creado_en']) > strtotime('-30 days');
?>
<article class="producto">
    <div class="producto__etiquetas">
        <?php if ($stock <= 0): ?>
            <span class="badge badge-agotado">Agotado</span>
        <?php elseif ($enOferta): ?>
            <span class="badge badge-oferta">
                −<?= (int) round(((float) $p['precio_antes'] - (float) $p['precio']) / (float) $p['precio_antes'] * 100) ?>%
            </span>
        <?php elseif ($esNueva): ?>
            <span class="badge badge-nuevo">Nuevo</span>
        <?php endif; ?>

        <?php if ($stock > 0 && $stock <= 3): ?>
            <span class="badge badge-aviso">Últimas <?= $stock ?></span>
        <?php endif; ?>
    </div>

    <button class="producto__favorito <?= $esFav ? 'activo' : '' ?>" type="button"
            data-favorito="<?= (int) $p['id'] ?>"
            aria-label="<?= $esFav ? 'Quitar de la lista de deseos' : 'Guardar en la lista de deseos' ?>">
        <i class="fa-<?= $esFav ? 'solid' : 'regular' ?> fa-heart"></i>
    </button>

    <a href="<?= $urlFicha ?>" class="producto__media">
        <img src="<?= e(imagen_producto($p['imagen'])) ?>" alt="<?= e($p['nombre']) ?>" loading="lazy" width="400" height="400">
    </a>

    <div class="producto__cuerpo">
        <span class="producto__cat"><?= e($p['categoria']) ?><?= !empty($p['material']) ? ' · ' . e($p['material']) : '' ?></span>

        <h3 class="producto__nombre"><a href="<?= $urlFicha ?>"><?= e($p['nombre']) ?></a></h3>

        <?php if ($totalRes > 0): ?>
            <span class="estrellas" aria-label="<?= $calif ?> de 5 estrellas">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa-solid fa-star <?= $i <= round($calif) ? '' : 'vacia' ?>"></i>
                <?php endfor; ?>
                <span class="estrellas-texto">(<?= $totalRes ?>)</span>
            </span>
        <?php endif; ?>

        <div class="producto__precios">
            <span class="producto__precio"><?= moneda($p['precio']) ?></span>
            <?php if ($enOferta): ?>
                <span class="producto__antes"><?= moneda($p['precio_antes']) ?></span>
            <?php endif; ?>
        </div>

        <div class="producto__pie">
            <?php if ($stock > 0): ?>
                <button class="btn btn-primario btn-sm" type="button" data-agregar="<?= (int) $p['id'] ?>">
                    <i class="fa-solid fa-bag-shopping"></i> Agregar
                </button>
            <?php else: ?>
                <a href="<?= $urlFicha ?>" class="btn btn-suave btn-sm">Ver pieza</a>
            <?php endif; ?>
        </div>
    </div>
</article>
