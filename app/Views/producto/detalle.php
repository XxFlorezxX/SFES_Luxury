<?php
/**
 * Vista: ficha de producto
 * La pantalla donde se decide la compra: fotografía grande, ficha
 * técnica, disponibilidad real, reseñas de clientes verificados y
 * piezas relacionadas.
 */
$stock    = (int) $producto['stock'];
$enOferta = !empty($producto['precio_antes']) && (float) $producto['precio_antes'] > (float) $producto['precio'];
$califica = (float) ($resumenResenas['promedio'] ?? 0);
$totalRes = (int) ($resumenResenas['total'] ?? 0);
?>

<section class="seccion-sm">
    <div class="contenedor">

        <nav aria-label="Ruta de navegación" style="margin-bottom:24px;font-size:.84rem">
            <a href="<?= BASE_URL ?>" class="texto-tenue">Inicio</a>
            <span class="texto-tenue"> / </span>
            <a href="<?= BASE_URL ?>catalogo?categoria=<?= urlencode($producto['categoria']) ?>" class="texto-tenue"><?= e($producto['categoria']) ?></a>
            <span class="texto-tenue"> / </span>
            <span><?= e($producto['nombre']) ?></span>
        </nav>

        <div class="ficha">
            <!-- ══════════ GALERÍA ══════════ -->
            <div class="ficha__galeria">
                <div class="ficha__principal">
                    <img src="<?= e(imagen_producto($producto['imagen'])) ?>" alt="<?= e($producto['nombre']) ?>"
                         id="imagen-principal" width="700" height="700">
                    <div class="producto__etiquetas">
                        <?php if ($stock <= 0): ?>
                            <span class="badge badge-agotado">Agotado</span>
                        <?php elseif ($enOferta): ?>
                            <span class="badge badge-oferta">
                                −<?= (int) round(((float) $producto['precio_antes'] - (float) $producto['precio']) / (float) $producto['precio_antes'] * 100) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ══════════ INFORMACIÓN ══════════ -->
            <div class="ficha__info">
                <span class="eyebrow"><?= e($producto['categoria']) ?> · <?= e($producto['material']) ?></span>
                <h1><?= e($producto['nombre']) ?></h1>

                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px">
                    <?php if ($totalRes > 0): ?>
                        <span class="estrellas" aria-label="<?= $califica ?> de 5 estrellas">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-solid fa-star <?= $i <= round($califica) ? '' : 'vacia' ?>"></i>
                            <?php endfor; ?>
                            <span class="estrellas-texto"><?= number_format($califica, 1, ',', '.') ?> · <?= $totalRes ?> reseña<?= $totalRes === 1 ? '' : 's' ?></span>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($producto['sku'])): ?>
                        <span class="texto-tenue" style="font-size:.78rem">Ref. <?= e($producto['sku']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="ficha__precio-fila">
                    <span class="ficha__precio"><?= moneda($producto['precio']) ?></span>
                    <?php if ($enOferta): ?>
                        <span class="ficha__antes"><?= moneda($producto['precio_antes']) ?></span>
                        <span class="badge badge-oferta">Ahorras <?= moneda((float) $producto['precio_antes'] - (float) $producto['precio']) ?></span>
                    <?php endif; ?>
                </div>

                <p class="ficha__desc"><?= nl2br(e($producto['descripcion'])) ?></p>

                <div class="ficha__specs">
                    <div class="ficha__spec">
                        <span>Material</span>
                        <strong><?= e($producto['material']) ?></strong>
                    </div>
                    <?php if (!empty($producto['peso_gramos'])): ?>
                        <div class="ficha__spec">
                            <span>Peso</span>
                            <strong><?= number_format((float) $producto['peso_gramos'], 1, ',', '.') ?> g</strong>
                        </div>
                    <?php endif; ?>
                    <div class="ficha__spec">
                        <span>Disponibilidad</span>
                        <strong style="color:var(--<?= $stock > 3 ? 'exito' : ($stock > 0 ? 'aviso' : 'error') ?>)">
                            <?= $stock > 3 ? 'En stock' : ($stock > 0 ? 'Últimas ' . $stock : 'Agotado') ?>
                        </strong>
                    </div>
                    <?php if ((int) ($producto['vendidos'] ?? 0) > 0): ?>
                        <div class="ficha__spec">
                            <span>Vendidas</span>
                            <strong><?= (int) $producto['vendidos'] ?> unidades</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($stock > 0): ?>
                    <div class="ficha__compra">
                        <div class="cantidad">
                            <button type="button" id="menos" aria-label="Restar una unidad"><i class="fa-solid fa-minus"></i></button>
                            <label for="cantidad" class="solo-lectores">Cantidad</label>
                            <input type="number" id="cantidad" value="1" min="1" max="<?= $stock ?>" aria-label="Cantidad">
                            <button type="button" id="mas" aria-label="Sumar una unidad"><i class="fa-solid fa-plus"></i></button>
                        </div>

                        <button class="btn btn-primario" type="button" data-agregar="<?= (int) $producto['id'] ?>" style="flex:1;min-width:200px">
                            <i class="fa-solid fa-bag-shopping"></i> Agregar a la bolsa
                        </button>

                        <button class="btn btn-contorno <?= $esFavorito ? 'activo' : '' ?>" type="button"
                                data-favorito="<?= (int) $producto['id'] ?>" aria-label="Guardar en la lista de deseos">
                            <i class="fa-<?= $esFavorito ? 'solid' : 'regular' ?> fa-heart"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="aviso aviso-alerta">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>Pieza agotada por ahora.</strong>
                            Escríbenos y te avisamos cuando vuelva a estar disponible.
                            <a href="<?= BASE_URL ?>contacto" class="btn-texto" style="display:block;margin-top:6px">Contactar al taller →</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="confianza" style="border-bottom:none;padding-bottom:0;grid-template-columns:1fr 1fr">
                    <div class="confianza__item">
                        <i class="fa-solid fa-truck-fast"></i>
                        <div><strong>Envío gratis</strong><span>Desde $200.000</span></div>
                    </div>
                    <div class="confianza__item">
                        <i class="fa-solid fa-rotate-left"></i>
                        <div><strong>Cambios en 8 días</strong><span>Si no es lo que esperabas</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ RESEÑAS ═══════════════ -->
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Opiniones</span>
                <h2>Reseñas de esta pieza</h2>
            </div>
        </div>

        <div class="tarjeta">
            <?php if ($totalRes > 0): ?>
                <div class="resumen-resenas">
                    <div class="resumen-resenas__nota">
                        <strong><?= number_format($califica, 1, ',', '.') ?></strong>
                        <span class="estrellas">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-solid fa-star <?= $i <= round($califica) ? '' : 'vacia' ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <p class="texto-tenue" style="font-size:.82rem"><?= $totalRes ?> reseña<?= $totalRes === 1 ? '' : 's' ?></p>
                    </div>

                    <div class="barras-estrellas">
                        <?php foreach ([5, 4, 3, 2, 1] as $estrella): ?>
                            <?php $cuantas = (int) ($resumenResenas['distribucion'][$estrella] ?? 0); ?>
                            <div class="barra-estrella">
                                <span><?= $estrella ?> <i class="fa-solid fa-star" style="color:var(--acento);font-size:10px"></i></span>
                                <span class="barra-estrella__pista">
                                    <span class="barra-estrella__valor" style="width:<?= porcentaje($cuantas, $totalRes) ?>%"></span>
                                </span>
                                <span><?= $cuantas ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($resenas)): ?>
                <div class="vacio" style="padding:32px 16px">
                    <i class="fa-regular fa-comment"></i>
                    <h3>Todavía no hay reseñas</h3>
                    <p>Sé la primera persona en contar cómo te fue con esta pieza.</p>
                </div>
            <?php else: ?>
                <div class="grid-resenas" style="margin-top:24px">
                    <?php foreach ($resenas as $r): ?>
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
                                    <span class="badge badge-exito"><i class="fa-solid fa-circle-check"></i> Compra verificada</span>
                                <?php endif; ?>
                            </div>

                            <span class="estrellas" aria-label="<?= (int) $r['calificacion'] ?> de 5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-solid fa-star <?= $i <= (int) $r['calificacion'] ? '' : 'vacia' ?>"></i>
                                <?php endfor; ?>
                            </span>

                            <?php if (!empty($r['titulo'])): ?>
                                <strong><?= e($r['titulo']) ?></strong>
                            <?php endif; ?>
                            <p><?= nl2br(e($r['comentario'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ── Escribir reseña ── -->
            <div style="margin-top:32px;padding-top:32px;border-top:1px solid var(--borde)">
                <?php if (!$puedeResenar): ?>
                    <p class="texto-tenue">
                        <a href="<?= BASE_URL ?>ingresar" class="btn-texto">Inicia sesión</a>
                        para escribir una reseña sobre esta pieza.
                    </p>
                <?php else: ?>
                    <h3 style="font-size:1.1rem;margin-bottom:16px">
                        <?= $miResena ? 'Editar tu reseña' : 'Escribe tu reseña' ?>
                        <?php if ($compro): ?>
                            <span class="badge badge-exito" style="margin-left:8px"><i class="fa-solid fa-circle-check"></i> Compraste esta pieza</span>
                        <?php endif; ?>
                    </h3>

                    <form method="post" action="<?= BASE_URL ?>producto/<?= (int) $producto['id'] ?>/resena">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="campo">
                            <label>Tu calificación <span class="req">*</span></label>
                            <div class="selector-estrellas">
                                <?php foreach ([5, 4, 3, 2, 1] as $valor): ?>
                                    <input type="radio" name="calificacion" id="estrella-<?= $valor ?>" value="<?= $valor ?>"
                                           <?= (int) ($miResena['calificacion'] ?? 0) === $valor ? 'checked' : '' ?>>
                                    <label for="estrella-<?= $valor ?>" title="<?= $valor ?> estrellas">
                                        <i class="fa-solid fa-star"></i>
                                        <span class="solo-lectores"><?= $valor ?> estrellas</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="campo">
                            <label for="titulo">Título (opcional)</label>
                            <input type="text" id="titulo" name="titulo" maxlength="120"
                                   placeholder="Ej: Superó lo que esperaba" value="<?= e($miResena['titulo'] ?? '') ?>">
                        </div>

                        <div class="campo">
                            <label for="comentario">Tu experiencia <span class="req">*</span></label>
                            <textarea id="comentario" name="comentario" required minlength="10" maxlength="1500"
                                      placeholder="¿Cómo te llegó? ¿Cómo se ve puesta? ¿La recomendarías?"><?= e($miResena['comentario'] ?? '') ?></textarea>
                        </div>

                        <button class="btn btn-primario" type="submit">
                            <?= $miResena ? 'Actualizar reseña' : 'Publicar reseña' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ RELACIONADOS ═══════════════ -->
<?php if (!empty($relacionados)): ?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">También te puede gustar</span>
                <h2>Piezas relacionadas</h2>
            </div>
        </div>
        <div class="grid-productos">
            <?php $favoritos = $favoritos ?? []; ?>
            <?php foreach ($relacionados as $p): ?>
                <?php require __DIR__ . '/../shared/tarjeta-producto.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
