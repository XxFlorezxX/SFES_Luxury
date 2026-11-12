<?php
/**
 * Vista: pedido confirmado
 * Resumen de lo comprado y próximos pasos.
 */
?>
<section class="seccion">
    <div class="contenedor">
        <div class="confirmado">
            <div class="confirmado__icono"><i class="fa-solid fa-check"></i></div>

            <h1>¡Gracias por tu compra!</h1>
            <p class="texto-suave">
                Recibimos tu pedido y ya lo estamos preparando.
                Te contactaremos al <strong><?= e($pedido['telefono']) ?></strong> para coordinar la entrega.
            </p>

            <div class="confirmado__codigo"><?= e($pedido['codigo'] ?: '#' . $pedido['id']) ?></div>

            <div class="tarjeta" style="text-align:left;margin-top:32px">
                <h3 style="font-size:1.05rem;margin-bottom:16px">Detalle del pedido</h3>

                <?php foreach ($items as $item): ?>
                    <div class="mini-linea">
                        <img src="<?= e(imagen_producto($item['imagen'])) ?>" alt="" loading="lazy">
                        <div class="mini-linea__txt">
                            <strong><?= e($item['nombre']) ?></strong>
                            <span><?= (int) $item['cantidad'] ?> × <?= moneda($item['precio']) ?></span>
                        </div>
                        <strong style="font-size:.86rem"><?= moneda((float) $item['precio'] * (int) $item['cantidad']) ?></strong>
                    </div>
                <?php endforeach; ?>

                <div style="border-top:1px solid var(--borde);margin-top:16px;padding-top:8px">
                    <div class="resumen__fila"><span>Subtotal</span><span><?= moneda($pedido['subtotal']) ?></span></div>
                    <?php if ((float) $pedido['descuento'] > 0): ?>
                        <div class="resumen__fila descuento">
                            <span>Descuento <?= $pedido['cupon_codigo'] ? '(' . e($pedido['cupon_codigo']) . ')' : '' ?></span>
                            <span>−<?= moneda($pedido['descuento']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="resumen__fila">
                        <span>Envío a <?= e($pedido['ciudad']) ?></span>
                        <span><?= (float) $pedido['envio'] > 0 ? moneda($pedido['envio']) : 'Gratis' ?></span>
                    </div>
                    <div class="resumen__total"><span>Total</span><strong><?= moneda($pedido['total']) ?></strong></div>
                </div>

                <div style="border-top:1px solid var(--borde);margin-top:20px;padding-top:20px;font-size:.86rem">
                    <p class="texto-tenue" style="margin-bottom:4px">Entrega en</p>
                    <p><?= e($pedido['direccion']) ?>, <?= e($pedido['ciudad']) ?></p>
                    <p class="texto-tenue" style="margin-top:12px;margin-bottom:4px">Método de pago</p>
                    <p><?= $pedido['metodo_pago'] === 'transferencia' ? 'Transferencia bancaria' : 'Pago contraentrega' ?></p>
                </div>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:32px">
                <a href="<?= BASE_URL ?>catalogo" class="btn btn-primario">Seguir comprando</a>
                <?php if (!empty($_SESSION['cliente_id'])): ?>
                    <a href="<?= BASE_URL ?>mis-pedidos" class="btn btn-contorno">Ver mis pedidos</a>
                <?php endif; ?>
                <a href="https://wa.me/<?= e(TIENDA_WHATSAPP) ?>?text=<?= urlencode('Hola, acabo de hacer el pedido ' . ($pedido['codigo'] ?: '#' . $pedido['id'])) ?>"
                   class="btn btn-contorno" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i> Escribirnos
                </a>
            </div>
        </div>
    </div>
</section>
