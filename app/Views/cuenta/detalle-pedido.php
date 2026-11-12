<?php
/**
 * Vista: detalle de un pedido del cliente, con línea de tiempo del estado.
 */
$seccionCuenta = 'pedidos';
$flujo   = ['pendiente', 'confirmado', 'procesando', 'enviado', 'entregado'];
$actual  = array_search($pedido['estado'], $flujo, true);
$cancelado = $pedido['estado'] === 'cancelado';
?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Pedido <?= e($pedido['codigo'] ?: '#' . $pedido['id']) ?></span>
                <h2><?= fecha_corta($pedido['creado_en']) ?></h2>
            </div>
            <a href="<?= BASE_URL ?>mis-pedidos" class="btn-texto"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        </div>

        <div class="cuenta">
            <?php require __DIR__ . '/../shared/menu-cuenta.php'; ?>

            <div>
                <!-- ── Estado ── -->
                <div class="tarjeta" style="margin-bottom:24px">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px">
                        <h3 style="font-size:1.05rem">Estado del pedido</h3>
                        <span class="badge estado-<?= e($pedido['estado']) ?>"><?= estado_pedido_texto($pedido['estado']) ?></span>
                    </div>

                    <?php if ($cancelado): ?>
                        <div class="aviso aviso-error" style="margin:0">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <div>Este pedido fue cancelado. Si crees que es un error, escríbenos y lo revisamos.</div>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;gap:4px">
                            <?php foreach ($flujo as $i => $paso): ?>
                                <div style="flex:1;text-align:center">
                                    <div style="height:5px;border-radius:99px;background:<?= $i <= $actual ? 'var(--acento)' : 'var(--superficie-hover)' ?>"></div>
                                    <span style="display:block;font-size:.7rem;margin-top:8px;color:<?= $i <= $actual ? 'var(--acento)' : 'var(--tinta-tenue)' ?>">
                                        <?= estado_pedido_texto($paso) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($pedido['notas_admin'])): ?>
                        <div class="aviso aviso-info" style="margin-top:20px;margin-bottom:0">
                            <i class="fa-solid fa-note-sticky"></i>
                            <div><strong>Nota del equipo:</strong> <?= e($pedido['notas_admin']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ── Piezas ── -->
                <div class="tarjeta" style="margin-bottom:24px">
                    <h3 style="font-size:1.05rem;margin-bottom:16px">Piezas del pedido</h3>

                    <?php foreach ($items as $item): ?>
                        <div class="mini-linea" style="border-bottom:1px solid var(--borde);padding-block:12px">
                            <img src="<?= e(imagen_producto($item['imagen'])) ?>" alt="" loading="lazy">
                            <div class="mini-linea__txt">
                                <strong><?= e($item['nombre']) ?></strong>
                                <span><?= (int) $item['cantidad'] ?> × <?= moneda($item['precio']) ?></span>
                            </div>
                            <strong style="font-size:.9rem"><?= moneda((float) $item['precio'] * (int) $item['cantidad']) ?></strong>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top:16px">
                        <div class="resumen__fila"><span>Subtotal</span><span><?= moneda($pedido['subtotal']) ?></span></div>
                        <?php if ((float) $pedido['descuento'] > 0): ?>
                            <div class="resumen__fila descuento">
                                <span>Descuento <?= $pedido['cupon_codigo'] ? '(' . e($pedido['cupon_codigo']) . ')' : '' ?></span>
                                <span>−<?= moneda($pedido['descuento']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="resumen__fila">
                            <span>Envío</span>
                            <span><?= (float) $pedido['envio'] > 0 ? moneda($pedido['envio']) : 'Gratis' ?></span>
                        </div>
                        <div class="resumen__total"><span>Total</span><strong><?= moneda($pedido['total']) ?></strong></div>
                    </div>
                </div>

                <!-- ── Entrega ── -->
                <div class="tarjeta">
                    <h3 style="font-size:1.05rem;margin-bottom:16px">Datos de entrega</h3>
                    <div class="campos-2" style="font-size:.88rem">
                        <div>
                            <p class="texto-tenue" style="font-size:.78rem">Recibe</p>
                            <p style="margin-bottom:12px"><?= e($pedido['nombres']) ?></p>
                            <p class="texto-tenue" style="font-size:.78rem">Teléfono</p>
                            <p><?= e($pedido['telefono']) ?></p>
                        </div>
                        <div>
                            <p class="texto-tenue" style="font-size:.78rem">Dirección</p>
                            <p style="margin-bottom:12px"><?= e($pedido['direccion']) ?>, <?= e($pedido['ciudad']) ?></p>
                            <p class="texto-tenue" style="font-size:.78rem">Método de pago</p>
                            <p><?= $pedido['metodo_pago'] === 'transferencia' ? 'Transferencia bancaria' : 'Pago contraentrega' ?></p>
                        </div>
                    </div>
                    <?php if (!empty($pedido['especificaciones'])): ?>
                        <p class="texto-tenue" style="font-size:.78rem;margin-top:12px">Indicaciones</p>
                        <p style="font-size:.88rem"><?= e($pedido['especificaciones']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
