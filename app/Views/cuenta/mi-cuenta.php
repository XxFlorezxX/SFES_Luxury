<?php
/**
 * Vista: mi cuenta
 * Perfil, dirección de envío guardada y cambio de contraseña.
 */
$seccionCuenta = 'cuenta';
?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Tu espacio</span>
                <h2>Hola, <?= e(explode(' ', (string) $usuario['nombre'])[0]) ?></h2>
            </div>
        </div>

        <div class="cuenta">
            <?php require __DIR__ . '/../shared/menu-cuenta.php'; ?>

            <div>
                <div class="metricas">
                    <div class="metrica">
                        <span>Pedidos</span>
                        <strong><?= (int) $resumen['pedidos'] ?></strong>
                    </div>
                    <div class="metrica">
                        <span>Total comprado</span>
                        <strong><?= moneda($resumen['gastado']) ?></strong>
                    </div>
                    <div class="metrica">
                        <span>Lista de deseos</span>
                        <strong><?= (int) $resumen['favoritos'] ?></strong>
                    </div>
                </div>

                <!-- ── Datos personales ── -->
                <div class="tarjeta" style="margin-bottom:24px">
                    <h3 style="font-size:1.1rem;margin-bottom:20px"><i class="fa-solid fa-user" style="color:var(--acento)"></i> Datos personales</h3>

                    <form method="post" action="<?= BASE_URL ?>mi-cuenta/perfil">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="campos-2">
                            <div class="campo">
                                <label for="nombre">Nombre completo <span class="req">*</span></label>
                                <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e($usuario['nombre']) ?>">
                            </div>
                            <div class="campo">
                                <label for="telefono">Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" maxlength="30" value="<?= e($usuario['telefono'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="campo">
                            <label for="email">Correo electrónico <span class="req">*</span></label>
                            <input type="email" id="email" name="email" required maxlength="150" value="<?= e($usuario['email']) ?>">
                        </div>

                        <button class="btn btn-primario" type="submit">Guardar cambios</button>
                    </form>
                </div>

                <!-- ── Dirección de envío ── -->
                <div class="tarjeta" style="margin-bottom:24px">
                    <h3 style="font-size:1.1rem;margin-bottom:20px"><i class="fa-solid fa-location-dot" style="color:var(--acento)"></i> Dirección de envío</h3>

                    <form method="post" action="<?= BASE_URL ?>mi-cuenta/direccion" id="form-direccion">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="campos-2">
                            <div class="campo">
                                <label for="dir-nombres">Recibe <span class="req">*</span></label>
                                <input type="text" id="dir-nombres" name="nombres" required maxlength="150"
                                       value="<?= e($direccion['nombres'] ?? $usuario['nombre']) ?>">
                            </div>
                            <div class="campo">
                                <label for="dir-telefono">Teléfono de contacto <span class="req">*</span></label>
                                <input type="tel" id="dir-telefono" name="telefono" required maxlength="30"
                                       value="<?= e($direccion['telefono'] ?? $usuario['telefono'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="campos-2">
                            <div class="campo">
                                <label for="dir-ciudad">Ciudad</label>
                                <input type="text" id="dir-ciudad" name="ciudad" maxlength="100" value="<?= e($direccion['ciudad'] ?? '') ?>">
                            </div>
                            <div class="campo">
                                <label for="dir-direccion">Dirección <span class="req">*</span></label>
                                <input type="text" id="dir-direccion" name="direccion" required maxlength="300"
                                       value="<?= e($direccion['direccion'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="campo">
                            <label for="dir-especificaciones">Indicaciones adicionales</label>
                            <textarea id="dir-especificaciones" name="especificaciones" maxlength="500"><?= e($direccion['especificaciones'] ?? '') ?></textarea>
                        </div>

                        <button class="btn btn-primario" type="submit">Guardar dirección</button>
                    </form>
                </div>

                <!-- ── Contraseña ── -->
                <div class="tarjeta" style="margin-bottom:24px">
                    <h3 style="font-size:1.1rem;margin-bottom:20px"><i class="fa-solid fa-key" style="color:var(--acento)"></i> Cambiar contraseña</h3>

                    <form method="post" action="<?= BASE_URL ?>mi-cuenta/clave">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="campo">
                            <label for="password_actual">Contraseña actual <span class="req">*</span></label>
                            <input type="password" id="password_actual" name="password_actual" required autocomplete="current-password">
                        </div>

                        <div class="campos-2">
                            <div class="campo">
                                <label for="password_nueva">Nueva contraseña <span class="req">*</span></label>
                                <input type="password" id="password_nueva" name="password_nueva" required minlength="8" autocomplete="new-password">
                                <span class="ayuda">Mínimo 8 caracteres, con letras y números.</span>
                            </div>
                            <div class="campo">
                                <label for="password_confirmar">Confirmar <span class="req">*</span></label>
                                <input type="password" id="password_confirmar" name="password_confirmar" required minlength="8" autocomplete="new-password">
                            </div>
                        </div>

                        <button class="btn btn-suave" type="submit">Actualizar contraseña</button>
                    </form>
                </div>

                <!-- ── Últimos pedidos ── -->
                <?php if (!empty($ultimosPedidos)): ?>
                    <div class="seccion-cabecera">
                        <h3 style="font-size:1.1rem">Últimos pedidos</h3>
                        <a href="<?= BASE_URL ?>mis-pedidos" class="btn-texto">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
                    </div>

                    <?php foreach ($ultimosPedidos as $pedido): ?>
                        <div class="pedido-fila">
                            <div>
                                <span class="pedido-fila__id"><?= e($pedido['codigo'] ?: '#' . $pedido['id']) ?></span>
                                <p class="texto-tenue" style="font-size:.8rem"><?= fecha_corta($pedido['creado_en']) ?></p>
                            </div>
                            <span class="badge estado-<?= e($pedido['estado']) ?>"><?= estado_pedido_texto($pedido['estado']) ?></span>
                            <strong style="color:var(--acento)"><?= moneda($pedido['total']) ?></strong>
                            <a href="<?= BASE_URL ?>mis-pedidos/<?= (int) $pedido['id'] ?>" class="btn btn-suave btn-sm">Ver detalle</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
