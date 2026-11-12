<?php
/**
 * Vista: inicio
 * Todo lo que se muestra viene de la base de datos: piezas destacadas,
 * categorías reales, novedades y reseñas de clientes.
 */
$iconosCategoria = [
    'Anillos'  => 'fa-ring',
    'Aretes'   => 'fa-star',
    'Collares' => 'fa-gem',
    'Pulseras' => 'fa-circle-notch',
    'Dijes'    => 'fa-heart',
    'Sets'     => 'fa-box-open',
];
?>

<!-- ═══════════════ HÉROE ═══════════════ -->
<section class="hero">
    <div class="contenedor hero__grid">
        <div>
            <span class="eyebrow"><i class="fa-solid fa-gem"></i> Colección permanente</span>
            <h1>Oro y plata que<br><em>acompañan</em> tu historia</h1>
            <p>
                Piezas ensambladas a mano en Colombia, en plata 925 y baño de oro de 18 quilates.
                Diseños que resisten el uso diario y las modas que pasan.
            </p>
            <div class="hero__acciones">
                <a href="<?= BASE_URL ?>catalogo" class="btn btn-primario">
                    Ver el catálogo <i class="fa-solid fa-arrow-right"></i>
                </a>
                <a href="<?= BASE_URL ?>personalizar" class="btn btn-contorno">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Diseñar mi pieza
                </a>
            </div>
        </div>

        <div class="hero__figura hero__figura--logo">
            <img src="<?= BASE_URL ?>assets/img/sitio/Gemini_Generated_Image_f7uypzf7uypzf7uy.png" alt="<?= e(TIENDA_NOMBRE) ?>" width="600" height="600">
            <?php if ($promedioTienda > 0): ?>
                <div class="hero__sello">
                    <i class="fa-solid fa-star"></i>
                    <div>
                        <strong><?= number_format($promedioTienda, 1, ',', '.') ?> de 5</strong>
                        <span class="texto-tenue" style="font-size:.78rem"> · calificación de nuestras clientas</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ CINTA DE CONFIANZA ═══════════════ -->
<div class="contenedor">
    <div class="confianza">
        <div class="confianza__item">
            <i class="fa-solid fa-truck-fast"></i>
            <div><strong>Envío gratis desde $200.000</strong><span>A todo Colombia</span></div>
        </div>
        <div class="confianza__item">
            <i class="fa-solid fa-shield-halved"></i>
            <div><strong>Garantía de 6 meses</strong><span>Sobre el baño y las monturas</span></div>
        </div>
        <div class="confianza__item">
            <i class="fa-solid fa-hand-holding-dollar"></i>
            <div><strong>Pago contraentrega</strong><span>Pagas al recibir</span></div>
        </div>
        <div class="confianza__item">
            <i class="fa-solid fa-gem"></i>
            <div><strong>Plata 925 certificada</strong><span>Baño de oro 18k</span></div>
        </div>
    </div>
</div>

<!-- ═══════════════ CATEGORÍAS ═══════════════ -->
<?php if (!empty($categorias)): ?>
<section class="seccion">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Explora</span>
                <h2>Por tipo de pieza</h2>
            </div>
            <a href="<?= BASE_URL ?>catalogo" class="btn-texto">Ver todo <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="grid-categorias">
            <?php foreach ($categorias as $cat): ?>
                <a href="<?= BASE_URL ?>catalogo?categoria=<?= urlencode($cat) ?>" class="categoria-card">
                    <i class="fa-solid <?= $iconosCategoria[$cat] ?? 'fa-gem' ?>"></i>
                    <strong><?= e($cat) ?></strong>
                    <span><?= (int) ($conteos[$cat] ?? 0) ?> piezas</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════ DESTACADOS ═══════════════ -->
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Selección de la casa</span>
                <h2>Piezas destacadas</h2>
            </div>
            <a href="<?= BASE_URL ?>catalogo" class="btn-texto">Ver el catálogo <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <?php if (empty($destacados)): ?>
            <div class="vacio">
                <i class="fa-solid fa-gem"></i>
                <h3>Estamos preparando la colección</h3>
                <p>Muy pronto encontrarás aquí nuestras piezas. Vuelve a visitarnos.</p>
            </div>
        <?php else: ?>
            <div class="grid-productos">
                <?php foreach ($destacados as $p): ?>
                    <?php require __DIR__ . '/../shared/tarjeta-producto.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ═══════════════ FRANJA EDITORIAL ═══════════════ -->
<section class="seccion-sm">
    <div class="contenedor">
        <div class="editorial">
            <div>
                <span class="eyebrow">Hecho en Colombia</span>
                <h2>Una pieza a la medida, sin salir de casa</h2>
                <p>
                    Describe lo que tienes en mente —el tipo de joya, el metal, la piedra, el estilo— y
                    nuestro asistente te devuelve una propuesta con nombre, descripción y precio estimado
                    para que la cotices con nuestro taller.
                </p>
                <ul class="editorial__lista">
                    <li><i class="fa-solid fa-check"></i> Grabado de iniciales o fechas sin costo</li>
                    <li><i class="fa-solid fa-check"></i> Ajuste de talla incluido en anillos</li>
                    <li><i class="fa-solid fa-check"></i> Estuche de regalo en todos los pedidos</li>
                </ul>
                <a href="<?= BASE_URL ?>personalizar" class="btn btn-primario">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Empezar mi diseño
                </a>
            </div>

            <div class="grid-productos" style="grid-template-columns:1fr 1fr">
                <?php foreach (array_slice($novedades, 0, 2) as $p): ?>
                    <?php require __DIR__ . '/../shared/tarjeta-producto.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ MÁS VENDIDOS ═══════════════ -->
<?php if (!empty($masVendidos)): ?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Lo que más se lleva</span>
                <h2>Favoritas de nuestras clientas</h2>
            </div>
        </div>

        <div class="grid-productos">
            <?php foreach ($masVendidos as $p): ?>
                <?php require __DIR__ . '/../shared/tarjeta-producto.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════ RESEÑAS ═══════════════ -->
<?php if (!empty($ultimasResenas)): ?>
<section class="seccion">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Opiniones verificadas</span>
                <h2>Lo que dicen quienes ya compraron</h2>
            </div>
        </div>

        <div class="grid-resenas">
            <?php foreach (array_slice($ultimasResenas, 0, 3) as $r): ?>
                <article class="resena">
                    <div class="resena__cabecera">
                        <div class="resena__autor">
                            <span class="resena__avatar"><?= e(mb_strtoupper(mb_substr($r['autor'], 0, 1))) ?></span>
                            <div>
                                <strong><?= e($r['autor']) ?></strong>
                                <span><?= fecha_corta($r['creado_en']) ?></span>
                            </div>
                        </div>
                        <?php if ((int) $r['verificada'] === 1): ?>
                            <span class="badge badge-exito"><i class="fa-solid fa-circle-check"></i> Verificada</span>
                        <?php endif; ?>
                    </div>

                    <span class="estrellas" aria-label="<?= (int) $r['calificacion'] ?> de 5 estrellas">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-solid fa-star <?= $i <= (int) $r['calificacion'] ? '' : 'vacia' ?>"></i>
                        <?php endfor; ?>
                    </span>

                    <p>“<?= e(recortar($r['comentario'], 190)) ?>”</p>
                    <span class="resena__producto"><i class="fa-solid fa-gem"></i> <?= e($r['producto']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
