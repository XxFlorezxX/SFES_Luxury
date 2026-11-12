<?php
/**
 * Vista: bolsa de compras
 * Cantidades, cupón, cálculo de envío y recomendaciones.
 * Todos los importes provienen del servidor (modelo Carrito): el
 * navegador solo los muestra.
 */
?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Tu selección</span>
                <h2>Mi bolsa <?= $totales['articulos'] > 0 ? '(' . $totales['articulos'] . ')' : '' ?></h2>
            </div>
            <?php if (!empty($items)): ?>
                <a href="<?= BASE_URL ?>carrito/vaciar" class="btn-texto" style="color:var(--error)"
                   onclick="return confirm('¿Vaciar toda la bolsa?')">
                    <i class="fa-solid fa-trash-can"></i> Vaciar bolsa
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($problemas)): ?>
            <div class="aviso aviso-alerta">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>El inventario cambió mientras comprabas:</strong>
                    <ul style="margin-top:6px">
                        <?php foreach ($problemas as $problema): ?>
                            <li><?= e($problema) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="vacio">
                <i class="fa-solid fa-bag-shopping"></i>
                <h3>Tu bolsa está vacía</h3>
                <p>Explora el catálogo y guarda aquí las piezas que te gusten.</p>
                <a href="<?= BASE_URL ?>catalogo" class="btn btn-primario">Ver el catálogo</a>
            </div>
        <?php else: ?>

            <div class="carrito">
                <!-- ══════════ LÍNEAS ══════════ -->
                <div>
                    <?php foreach ($items as $item): ?>
                        <div class="carrito__linea" data-linea="<?= (int) $item['id'] ?>">
                            <a href="<?= BASE_URL ?>producto/<?= e($item['slug']) ?>">
                                <img src="<?= e(imagen_producto($item['imagen'])) ?>" alt="<?= e($item['nombre']) ?>" loading="lazy">
                            </a>

                            <div>
                                <h3><a href="<?= BASE_URL ?>producto/<?= e($item['slug']) ?>"><?= e($item['nombre']) ?></a></h3>
                                <p class="meta"><?= e($item['categoria']) ?> · <?= e($item['material']) ?> · <?= moneda($item['precio']) ?> c/u</p>

                                <?php if ($item['excede_stock']): ?>
                                    <p class="texto-tenue" style="color:var(--error);font-size:.78rem;margin-bottom:8px">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        Solo quedan <?= (int) $item['stock_restante'] ?> unidad(es)
                                    </p>
                                <?php endif; ?>

                                <div class="carrito__acciones-linea">
                                    <div class="cantidad">
                                        <button type="button" data-cantidad="menos" data-id="<?= (int) $item['id'] ?>" aria-label="Restar">
                                            <i class="fa-solid fa-minus"></i>
                                        </button>
                                        <label for="cant-<?= (int) $item['id'] ?>" class="solo-lectores">Cantidad de <?= e($item['nombre']) ?></label>
                                        <input type="number" id="cant-<?= (int) $item['id'] ?>" value="<?= (int) $item['cantidad'] ?>"
                                               min="1" max="<?= (int) $item['stock_restante'] ?>" data-input-cantidad="<?= (int) $item['id'] ?>">
                                        <button type="button" data-cantidad="mas" data-id="<?= (int) $item['id'] ?>" aria-label="Sumar">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>

                                    <a href="<?= BASE_URL ?>carrito/eliminar/<?= (int) $item['id'] ?>" class="btn-texto"
                                       style="color:var(--tinta-tenue);font-size:.8rem">
                                        <i class="fa-regular fa-trash-can"></i> Quitar
                                    </a>
                                </div>
                            </div>

                            <div class="carrito__precio">
                                <strong data-subtotal="<?= (int) $item['id'] ?>"><?= moneda($item['subtotal']) ?></strong>
                                <small><?= (int) $item['cantidad'] ?> × <?= moneda($item['precio']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <a href="<?= BASE_URL ?>catalogo" class="btn-texto" style="margin-top:20px;display:inline-block">
                        <i class="fa-solid fa-arrow-left"></i> Seguir comprando
                    </a>
                </div>

                <!-- ══════════ RESUMEN ══════════ -->
                <aside class="resumen" id="resumen">
                    <h3>Resumen</h3>

                    <?php if ($totales['falta_para_envio_gratis'] > 0 && $totales['umbral_envio_gratis'] > 0): ?>
                        <div class="barra-envio-gratis" id="barra-envio">
                            Te faltan <strong><?= moneda($totales['falta_para_envio_gratis']) ?></strong> para el envío gratis
                            <span class="barra-envio-gratis__pista">
                                <span class="barra-envio-gratis__valor"
                                      style="width:<?= min(100, porcentaje($totales['subtotal'], $totales['umbral_envio_gratis'])) ?>%"></span>
                            </span>
                        </div>
                    <?php elseif ($totales['envio_gratis']): ?>
                        <div class="aviso aviso-exito" style="padding:12px">
                            <i class="fa-solid fa-truck-fast"></i> ¡Tienes envío gratis!
                        </div>
                    <?php endif; ?>

                    <!-- Cupón -->
                    <?php if ($totales['cupon']): ?>
                        <div class="aviso aviso-exito" style="padding:12px 14px;justify-content:space-between">
                            <span><i class="fa-solid fa-ticket"></i> Cupón <strong><?= e($totales['cupon']) ?></strong></span>
                            <button type="button" id="quitar-cupon" class="btn-texto" style="color:inherit" aria-label="Quitar cupón">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <form class="cupon-form" id="form-cupon">
                            <label for="codigo-cupon" class="solo-lectores">Código de cupón</label>
                            <input type="text" id="codigo-cupon" placeholder="Código de cupón" maxlength="40" autocomplete="off">
                            <button class="btn btn-suave" type="submit">Aplicar</button>
                        </form>
                    <?php endif; ?>

                    <!-- Ciudad de envío -->
                    <div class="campo" style="margin-top:16px">
                        <label for="ciudad-envio">Calcular envío</label>
                        <select id="ciudad-envio">
                            <option value="">Selecciona tu ciudad</option>
                            <?php foreach ($ciudades as $ciudad): ?>
                                <option value="<?= e($ciudad['ciudad']) ?>" <?= $totales['ciudad'] === $ciudad['ciudad'] ? 'selected' : '' ?>>
                                    <?= e($ciudad['ciudad']) ?> — <?= moneda($ciudad['costo']) ?> (<?= e($ciudad['dias_estimados']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Totales -->
                    <div id="totales-carrito">
                        <div class="resumen__fila">
                            <span>Subtotal</span>
                            <span data-total="subtotal"><?= moneda($totales['subtotal']) ?></span>
                        </div>

                        <div class="resumen__fila descuento" data-fila="descuento" style="<?= $totales['descuento'] > 0 ? '' : 'display:none' ?>">
                            <span>Descuento</span>
                            <span data-total="descuento">−<?= moneda($totales['descuento']) ?></span>
                        </div>

                        <div class="resumen__fila">
                            <span>Envío</span>
                            <span data-total="envio">
                                <?= $totales['ciudad'] === '' ? 'Por calcular' : ($totales['envio'] > 0 ? moneda($totales['envio']) : 'Gratis') ?>
                            </span>
                        </div>

                        <div class="resumen__total">
                            <span>Total</span>
                            <strong data-total="total"><?= moneda($totales['total']) ?></strong>
                        </div>
                    </div>

                    <a href="<?= BASE_URL ?>checkout" class="btn btn-primario btn-bloque" style="margin-top:20px">
                        Finalizar compra <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <p class="texto-tenue texto-centro" style="font-size:.76rem;margin-top:12px">
                        <i class="fa-solid fa-lock"></i> Compra protegida · Pago contraentrega disponible
                    </p>
                </aside>
            </div>

            <!-- ══════════ RECOMENDADOS ══════════ -->
            <?php if (!empty($recomendados)): ?>
                <div style="margin-top:64px">
                    <div class="seccion-cabecera">
                        <div>
                            <span class="eyebrow">Combínalo con</span>
                            <h2>También te puede gustar</h2>
                        </div>
                    </div>
                    <div class="grid-productos">
                        <?php $favoritos = $favoritos ?? []; ?>
                        <?php foreach ($recomendados as $p): ?>
                            <?php require __DIR__ . '/../shared/tarjeta-producto.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>
