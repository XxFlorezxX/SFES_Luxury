<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Clientes</h1>
            <p><?= (int) $totales['clientes'] ?> registradas · <?= (int) $totales['nuevos'] ?> nuevas en los últimos 30 días</p>
        </div>
    </div>
    <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
</div>

<section class="paneles paneles-2-1">
    <div>
        <div class="filtros-barra">
            <div class="buscar-campo">
                <i class="fa-solid fa-magnifying-glass"></i>
                <label for="buscar-cliente" class="solo-lectores">Buscar cliente</label>
                <input type="search" id="buscar-cliente" class="input" placeholder="Buscar por nombre o correo…">
            </div>
        </div>

        <div class="tabla-envoltura">
            <div class="tabla-envoltura__cabecera">
                <h2>Listado de clientes</h2>
                <span class="texto-tenue" style="font-size:.82rem" id="conteo-clientes">Cargando…</span>
            </div>

            <div class="tabla-scroll">
                <table class="datos">
                    <thead>
                        <tr>
                            <th>Cliente</th><th>Contacto</th>
                            <th class="num">Pedidos</th><th class="num">Total comprado</th>
                            <th class="num">Registro</th><th class="num">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpo-clientes">
                        <tr><td colspan="6" style="text-align:center;padding:48px"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <nav class="paginacion" id="paginacion-clientes" aria-label="Paginación de clientes"></nav>
    </div>

    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Mejores clientas</h2>
                <p>Por total comprado histórico</p>
            </div>
        </div>

        <?php if (empty($mejores)): ?>
            <p class="texto-tenue" style="font-size:.84rem">Todavía no hay compras registradas.</p>
        <?php else: ?>
            <div class="lista-media">
                <?php foreach ($mejores as $cliente): ?>
                    <div class="lista-media__item">
                        <span class="resena__avatar"><?= e(mb_strtoupper(mb_substr($cliente['nombre'], 0, 1))) ?></span>
                        <div class="lista-media__txt">
                            <strong><?= e($cliente['nombre']) ?></strong>
                            <span><?= (int) $cliente['pedidos'] ?> pedido(s)</span>
                        </div>
                        <span class="lista-media__valor"><?= moneda($cliente['gastado']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════ MODAL: FICHA ══════════ -->
<div class="modal" id="modal-cliente" role="dialog" aria-modal="true" aria-labelledby="cliente-titulo">
    <div class="modal__caja">
        <div class="modal__cabecera">
            <h3 id="cliente-titulo">Cliente</h3>
            <button class="modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal__cuerpo" id="cliente-cuerpo"></div>
    </div>
</div>
