<?php
/**
 * Vista: personalizar con IA
 * Genera un concepto de pieza a la medida (nombre, descripción, precio
 * estimado) y una vista previa opcional de imagen. Funciona en modo
 * demostración si no hay claves de IA configuradas.
 */
?>
<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-wand-magic-sparkles"></i> Piezas a la medida</span>
                <h2>Diseña tu propia joya</h2>
            </div>
        </div>

        <p class="texto-suave" style="max-width:64ch;margin-bottom:32px">
            Describe la pieza que tienes en mente y te devolvemos una propuesta con nombre, descripción y
            precio estimado para cotizar con nuestro taller. Las piezas a la medida se cotizan, no se compran
            directamente: al final te conectamos con el equipo.
        </p>

        <div class="ficha">
            <!-- ══════════ FORMULARIO ══════════ -->
            <div class="tarjeta">
                <form id="form-personalizar">
                    <div class="campo">
                        <label for="tipo">¿Qué pieza quieres? <span class="req">*</span></label>
                        <input type="text" id="tipo" required maxlength="100" placeholder="Anillo, aretes, cadena, pulsera…">
                    </div>

                    <div class="campo">
                        <label>Metal</label>
                        <div class="chips chips-group" data-target="metal">
                            <?php foreach (['Plata 925', 'Oro 18k', 'Oro blanco', 'Oro rosa'] as $i => $opcion): ?>
                                <button type="button" class="chip <?= $i === 0 ? 'selected' : '' ?>" data-value="<?= e($opcion) ?>"><?= e($opcion) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="metal" value="Plata 925">
                    </div>

                    <div class="campo">
                        <label>Piedra</label>
                        <div class="chips chips-group" data-target="piedra">
                            <?php foreach (['Sin piedra', 'Circón', 'Perla', 'Esmeralda', 'Ópalo'] as $i => $opcion): ?>
                                <button type="button" class="chip <?= $i === 0 ? 'selected' : '' ?>" data-value="<?= e($opcion) ?>"><?= e($opcion) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="piedra" value="Sin piedra">
                    </div>

                    <div class="campo">
                        <label>Estilo</label>
                        <div class="chips chips-group" data-target="estilo">
                            <?php foreach (['Minimalista', 'Clásico', 'Vintage', 'Moderno', 'Bohemio'] as $i => $opcion): ?>
                                <button type="button" class="chip <?= $i === 0 ? 'selected' : '' ?>" data-value="<?= e($opcion) ?>"><?= e($opcion) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="estilo" value="Minimalista">
                    </div>

                    <div class="campo">
                        <label for="descripcion">Detalles adicionales</label>
                        <textarea id="descripcion" maxlength="600"
                                  placeholder="Grabado de iniciales, talla, ocasión, algún referente que te guste…"></textarea>
                    </div>

                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <button class="btn btn-primario" type="submit" id="btn-generar">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Generar propuesta
                        </button>
                        <button class="btn btn-suave" type="button" id="btn-limpiar">Limpiar</button>
                    </div>
                </form>
            </div>

            <!-- ══════════ RESULTADO ══════════ -->
            <div>
                <div class="ia-cargando" id="ia-loading">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <p>Diseñando tu pieza…</p>
                </div>

                <div id="ia-result" style="display:none">
                    <div class="tarjeta" style="margin-bottom:16px">
                        <div class="aviso aviso-info" id="demo-notice" style="display:none">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>Modo demostración: agrega tu <code>ANTHROPIC_API_KEY</code> en el archivo <code>.env</code> para propuestas generadas con IA.</div>
                        </div>

                        <span class="eyebrow">Propuesta</span>
                        <h3 id="res-nombre" style="font-size:var(--txt-xl);margin-bottom:12px"></h3>
                        <p id="res-descripcion" class="texto-suave" style="margin-bottom:20px"></p>

                        <div class="ficha__specs" style="margin-bottom:20px">
                            <div class="ficha__spec">
                                <span>Precio estimado</span>
                                <strong id="res-precio" style="color:var(--acento)"></strong>
                            </div>
                            <div class="ficha__spec">
                                <span>Tiempo de elaboración</span>
                                <strong id="res-tiempo"></strong>
                            </div>
                        </div>

                        <p class="texto-tenue" style="font-size:.78rem;margin-bottom:8px">Sugerencias del taller</p>
                        <div class="chips" id="res-sugerencias"></div>

                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px">
                            <button class="btn btn-contorno btn-sm" type="button" id="btn-regenerar">
                                <i class="fa-solid fa-rotate"></i> Otra propuesta
                            </button>
                            <button class="btn btn-contorno btn-sm" type="button" id="btn-generar-imagen">
                                <i class="fa-solid fa-image"></i> Ver vista previa
                            </button>
                        </div>
                    </div>

                    <!-- Vista previa de imagen -->
                    <div class="ia-cargando" id="img-loading">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <p>Generando la vista previa…</p>
                    </div>

                    <div class="tarjeta" id="img-resultado" style="display:none">
                        <div class="ficha__principal" style="margin-bottom:16px">
                            <img id="img-preview" src="" alt="Vista previa de la pieza personalizada">
                        </div>
                        <p class="texto-tenue" style="font-size:.78rem" id="img-restantes"></p>
                        <a class="btn btn-suave btn-sm" id="img-descargar" href="#" download style="margin-top:12px">
                            <i class="fa-solid fa-download"></i> Descargar
                        </a>
                    </div>

                    <div class="aviso aviso-alerta" id="img-demo" style="display:none">
                        <i class="fa-solid fa-circle-info"></i>
                        <div id="img-demo-texto"></div>
                    </div>

                    <!-- Llamado a la acción -->
                    <div class="tarjeta" id="img-cta" style="text-align:center">
                        <h3 style="font-size:1.05rem;margin-bottom:8px">¿Te gusta la propuesta?</h3>
                        <p class="texto-tenue" style="font-size:.88rem;margin-bottom:20px">
                            Nuestro taller la cotiza sin compromiso y te confirma tiempos y precio final.
                        </p>
                        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                            <a href="https://wa.me/<?= e(TIENDA_WHATSAPP) ?>" class="btn btn-primario" target="_blank" rel="noopener" id="btn-wa-personalizada">
                                <i class="fab fa-whatsapp"></i> Cotizar por WhatsApp
                            </a>
                            <a href="<?= BASE_URL ?>contacto" class="btn btn-contorno">Escribir al taller</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
