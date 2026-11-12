<?php
/**
 * Vista: finalizar compra
 * Solo se piden los datos de entrega. Los importes que se muestran
 * aquí son los mismos que el servidor volverá a calcular al confirmar,
 * así que lo que ve el cliente y lo que se guarda nunca difieren.
 */
?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Último paso</span>
                <h2>Finalizar compra</h2>
            </div>
            <a href="<?= BASE_URL ?>carrito" class="btn-texto"><i class="fa-solid fa-arrow-left"></i> Volver a la bolsa</a>
        </div>

        <?php if (!empty($problemas)): ?>
            <div class="aviso aviso-alerta">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Ajusta tu bolsa antes de continuar:</strong>
                    <ul style="margin-top:6px">
                        <?php foreach ($problemas as $problema): ?><li><?= e($problema) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($_SESSION['cliente_id'])): ?>
            <div class="aviso aviso-info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    Puedes comprar como invitada.
                    <a href="<?= BASE_URL ?>ingresar" style="color:inherit;text-decoration:underline">Inicia sesión</a>
                    si quieres guardar tu dirección y seguir tus pedidos.
                </div>
            </div>
        <?php endif; ?>

        <form id="form-checkout">
            <div class="checkout">
                <!-- ══════════ DATOS ══════════ -->
                <div>
                    <div class="checkout__paso tarjeta">
                        <h3><span class="num">1</span> Datos de entrega</h3>

                        <div class="campos-2">
                            <div class="campo">
                                <label for="nombres">Nombre completo <span class="req">*</span></label>
                                <input type="text" id="nombres" name="nombres" required maxlength="150"
                                       value="<?= e($direccion['nombres'] ?? $_SESSION['cliente_nombre'] ?? '') ?>">
                            </div>
                            <div class="campo">
                                <label for="telefono">Teléfono / WhatsApp <span class="req">*</span></label>
                                <input type="tel" id="telefono" name="telefono" required maxlength="30"
                                       value="<?= e($direccion['telefono'] ?? '') ?>" placeholder="300 000 0000">
                            </div>
                        </div>

                        <?php if (empty($_SESSION['cliente_id'])): ?>
                            <div class="campo">
                                <label for="email">Correo electrónico</label>
                                <input type="email" id="email" name="email" maxlength="150" placeholder="Para enviarte la confirmación">
                            </div>
                        <?php endif; ?>

                        <div class="campo">
                            <label for="ciudad">Ciudad <span class="req">*</span></label>
                            <select id="ciudad" name="ciudad" required>
                                <option value="">Selecciona tu ciudad</option>
                                <?php foreach ($ciudades as $ciudad): ?>
                                    <option value="<?= e($ciudad['ciudad']) ?>"
                                        <?= ($totales['ciudad'] ?: ($direccion['ciudad'] ?? '')) === $ciudad['ciudad'] ? 'selected' : '' ?>>
                                        <?= e($ciudad['ciudad']) ?> — <?= moneda($ciudad['costo']) ?> (<?= e($ciudad['dias_estimados']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo">
                            <label for="direccion">Dirección <span class="req">*</span></label>
                            <input type="text" id="direccion" name="direccion" required maxlength="300"
                                   placeholder="Calle 00 # 00-00, barrio" value="<?= e($direccion['direccion'] ?? '') ?>">
                        </div>

                        <div class="campo">
                            <label for="especificaciones">Indicaciones para la entrega (opcional)</label>
                            <textarea id="especificaciones" name="especificaciones" maxlength="500"
                                      placeholder="Torre, apartamento, punto de referencia, horario preferido…"><?= e($direccion['especificaciones'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="checkout__paso tarjeta">
                        <h3><span class="num">2</span> Método de pago</h3>

                        <label class="opcion-pago">
                            <input type="radio" name="metodo_pago" value="contraentrega" checked>
                            <div>
                                <strong>Pago contraentrega</strong>
                                <span>Pagas en efectivo cuando recibas tu pedido. Disponible en todo el país.</span>
                            </div>
                        </label>

                        <label class="opcion-pago">
                            <input type="radio" name="metodo_pago" value="transferencia">
                            <div>
                                <strong>Transferencia bancaria</strong>
                                <span>Te enviamos los datos de la cuenta por WhatsApp al confirmar el pedido.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- ══════════ RESUMEN ══════════ -->
                <aside class="resumen">
                    <h3>Tu pedido</h3>

                    <div style="max-height:280px;overflow-y:auto;margin-bottom:16px">
                        <?php foreach ($items as $item): ?>
                            <div class="mini-linea">
                                <img src="<?= e(imagen_producto($item['imagen'])) ?>" alt="" loading="lazy">
                                <div class="mini-linea__txt">
                                    <strong><?= e($item['nombre']) ?></strong>
                                    <span><?= (int) $item['cantidad'] ?> × <?= moneda($item['precio']) ?></span>
                                </div>
                                <strong style="font-size:.86rem"><?= moneda($item['subtotal']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="totales-checkout" style="border-top:1px solid var(--borde);padding-top:8px">
                        <div class="resumen__fila">
                            <span>Subtotal</span><span data-total="subtotal"><?= moneda($totales['subtotal']) ?></span>
                        </div>
                        <div class="resumen__fila descuento" data-fila="descuento" style="<?= $totales['descuento'] > 0 ? '' : 'display:none' ?>">
                            <span>Descuento <?= $totales['cupon'] ? '(' . e($totales['cupon']) . ')' : '' ?></span>
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

                    <button class="btn btn-primario btn-bloque" type="submit" id="btn-confirmar" style="margin-top:20px">
                        <i class="fa-solid fa-lock"></i> Confirmar pedido
                    </button>

                    <p class="texto-tenue texto-centro" style="font-size:.76rem;margin-top:12px">
                        Al confirmar aceptas nuestros términos de compra y política de cambios.
                    </p>
                </aside>
            </div>
        </form>
    </div>
</section>
