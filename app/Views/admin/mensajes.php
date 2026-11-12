<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Mensajes</h1>
            <p><?= count($mensajes) ?> recibidos · <?= (int) $sinLeer ?> sin leer</p>
        </div>
    </div>
    <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
</div>

<?php if (empty($mensajes)): ?>
    <div class="tabla-envoltura">
        <div class="tabla-vacia">
            <i class="fa-solid fa-envelope-open"></i>
            <p>Todavía no has recibido mensajes desde el formulario de contacto.</p>
        </div>
    </div>
<?php else: ?>
    <div class="paneles" style="gap:12px">
        <?php foreach ($mensajes as $m): ?>
            <article class="panel" style="<?= (int) $m['leido'] === 0 ? 'border-color:var(--acento)' : '' ?>">
                <div class="panel__cabecera">
                    <div>
                        <h2 style="font-size:1rem">
                            <?= e($m['asunto'] ?: 'Mensaje de ' . $m['nombre']) ?>
                            <?php if ((int) $m['leido'] === 0): ?>
                                <span class="badge badge-aviso" style="margin-left:8px">Sin leer</span>
                            <?php endif; ?>
                        </h2>
                        <p>
                            <strong><?= e($m['nombre']) ?></strong> ·
                            <a href="mailto:<?= e($m['email']) ?>" style="color:var(--acento)"><?= e($m['email']) ?></a>
                            · <?= fecha_relativa($m['creado_en']) ?>
                        </p>
                    </div>

                    <div style="display:flex;gap:8px">
                        <a href="mailto:<?= e($m['email']) ?>?subject=<?= urlencode('Re: ' . ($m['asunto'] ?: 'Tu mensaje a ' . TIENDA_NOMBRE)) ?>"
                           class="btn btn-suave btn-sm">
                            <i class="fa-solid fa-reply"></i> Responder
                        </a>

                        <?php if ((int) $m['leido'] === 0): ?>
                            <form method="post" action="<?= BASE_URL ?>admin/mensajes/leido">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button class="btn btn-suave btn-sm" type="submit">
                                    <i class="fa-solid fa-check"></i> Marcar leído
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="texto-suave" style="font-size:.9rem;white-space:pre-line"><?= e($m['mensaje']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
