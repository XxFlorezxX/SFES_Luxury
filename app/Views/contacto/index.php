<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Estamos para ayudarte</span>
                <h2>Hablemos de tu pieza</h2>
            </div>
        </div>

        <div class="contacto">
            <!-- ══════════ DATOS ══════════ -->
            <div>
                <p class="texto-suave" style="margin-bottom:32px">
                    ¿Buscas algo específico, quieres cotizar una pieza a la medida o necesitas ayuda con un pedido?
                    Escríbenos y te respondemos el mismo día hábil.
                </p>

                <div class="contacto__dato">
                    <i class="fa-brands fa-whatsapp"></i>
                    <div>
                        <strong>WhatsApp</strong>
                        <span><a href="https://wa.me/<?= e(TIENDA_WHATSAPP) ?>" target="_blank" rel="noopener"><?= e(TIENDA_WHATSAPP) ?></a></span>
                    </div>
                </div>

                <div class="contacto__dato">
                    <i class="fa-solid fa-envelope"></i>
                    <div>
                        <strong>Correo</strong>
                        <span><a href="mailto:<?= e(TIENDA_EMAIL) ?>"><?= e(TIENDA_EMAIL) ?></a></span>
                    </div>
                </div>

                <div class="contacto__dato">
                    <i class="fa-solid fa-clock"></i>
                    <div>
                        <strong>Horario de atención</strong>
                        <span>Lunes a viernes, 9:00 a.m. – 6:00 p.m.<br>Sábados, 9:00 a.m. – 1:00 p.m.</span>
                    </div>
                </div>

                <div class="contacto__dato">
                    <i class="fa-solid fa-truck-fast"></i>
                    <div>
                        <strong>Envíos</strong>
                        <span>A todo Colombia. Gratis desde $200.000.</span>
                    </div>
                </div>
            </div>

            <!-- ══════════ FORMULARIO ══════════ -->
            <div class="tarjeta">
                <?php if (!empty($enviado)): ?>
                    <div class="aviso aviso-exito">
                        <i class="fa-solid fa-circle-check"></i>
                        <div>
                            <strong>¡Mensaje enviado!</strong><br>
                            Te responderemos muy pronto al correo que nos dejaste.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errores)): ?>
                    <div class="aviso aviso-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= BASE_URL ?>contacto/enviar">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <!-- Campo trampa: invisible para las personas -->
                    <div class="honeypot" aria-hidden="true">
                        <label for="sitio_web">No llenar este campo</label>
                        <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="campos-2">
                        <div class="campo">
                            <label for="nombre">Nombre <span class="req">*</span></label>
                            <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e($valores['nombre']) ?>">
                        </div>
                        <div class="campo">
                            <label for="email">Correo <span class="req">*</span></label>
                            <input type="email" id="email" name="email" required maxlength="150" value="<?= e($valores['email']) ?>">
                        </div>
                    </div>

                    <div class="campo">
                        <label for="asunto">Asunto</label>
                        <input type="text" id="asunto" name="asunto" maxlength="160"
                               placeholder="Cotizar una pieza, estado de un pedido…" value="<?= e($valores['asunto']) ?>">
                    </div>

                    <div class="campo">
                        <label for="mensaje">Tu mensaje <span class="req">*</span></label>
                        <textarea id="mensaje" name="mensaje" required minlength="10" maxlength="2000"
                                  placeholder="Cuéntanos qué tienes en mente: tipo de pieza, metal, presupuesto aproximado…"><?= e($valores['mensaje']) ?></textarea>
                    </div>

                    <button class="btn btn-primario btn-bloque" type="submit">
                        <i class="fa-solid fa-paper-plane"></i> Enviar mensaje
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
