<?php $seccionCuenta = 'pedidos'; ?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Historial</span>
                <h2>Mis pedidos</h2>
            </div>
        </div>

        <div class="cuenta">
            <?php require __DIR__ . '/../shared/menu-cuenta.php'; ?>

            <div>
                <?php if (empty($pedidos)): ?>
                    <div class="vacio">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>Todavía no tienes pedidos</h3>
                        <p>Cuando hagas tu primera compra la verás aquí, con su estado en tiempo real.</p>
                        <a href="<?= BASE_URL ?>catalogo" class="btn btn-primario">Explorar el catálogo</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <div class="tarjeta" style="margin-bottom:16px">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:12px">
                                <div>
                                    <span class="pedido-fila__id"><?= e($pedido['codigo'] ?: '#' . $pedido['id']) ?></span>
                                    <p class="texto-tenue" style="font-size:.8rem"><?= fecha_corta($pedido['creado_en']) ?> · <?= e($pedido['ciudad']) ?></p>
                                </div>
                                <span class="badge estado-<?= e($pedido['estado']) ?>"><?= estado_pedido_texto($pedido['estado']) ?></span>
                            </div>

                            <p class="texto-suave" style="font-size:.88rem;margin-bottom:16px"><?= e($pedido['resumen']) ?></p>

                            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding-top:12px;border-top:1px solid var(--borde)">
                                <div>
                                    <span class="texto-tenue" style="font-size:.78rem">Total</span>
                                    <strong style="display:block;font-size:1.05rem;color:var(--acento)"><?= moneda($pedido['total']) ?></strong>
                                </div>
                                <a href="<?= BASE_URL ?>mis-pedidos/<?= (int) $pedido['id'] ?>" class="btn btn-suave btn-sm">
                                    Ver detalle <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
