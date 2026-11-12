<?php
/**
 * Vista: gestión de pedidos
 * La tabla se llena por fetch para poder filtrar y paginar sin recargar.
 * Cambiar un pedido a "cancelado" devuelve la mercancía al inventario.
 */
?>
<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Pedidos</h1>
            <p><?= (int) $totales['pedidos'] ?> pedidos · <?= moneda($totales['ingresos']) ?> facturados</p>
        </div>
    </div>
    <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
</div>

<!-- ══════════ RESUMEN POR ESTADO ══════════ -->
<section class="kpis">
    <?php
    $tarjetasEstado = [
        'pendiente'  => ['Pendientes',     'fa-hourglass-half', 'aviso'],
        'procesando' => ['En preparación', 'fa-box',            'info'],
        'enviado'    => ['Enviados',       'fa-truck-fast',     'info'],
        'entregado'  => ['Entregados',     'fa-circle-check',   'exito'],
    ];
    foreach ($tarjetasEstado as $clave => [$etiqueta, $icono, $tono]): ?>
        <button class="kpi" type="button" data-filtro-estado="<?= $clave ?>" style="text-align:left;cursor:pointer">
            <div class="kpi__top">
                <span class="kpi__etiqueta"><?= $etiqueta ?></span>
                <span class="kpi__icono"><i class="fa-solid <?= $icono ?>"></i></span>
            </div>
            <span class="kpi__valor"><?= (int) ($porEstado[$clave] ?? 0) ?></span>
            <div class="kpi__pie"><span>Toca para filtrar</span></div>
        </button>
    <?php endforeach; ?>
</section>

<!-- ══════════ FILTROS ══════════ -->
<div class="filtros-barra">
    <div class="buscar-campo">
        <i class="fa-solid fa-magnifying-glass"></i>
        <label for="buscar-pedido" class="solo-lectores">Buscar pedido</label>
        <input type="search" id="buscar-pedido" class="input" placeholder="Buscar por código, cliente, correo o teléfono…">
    </div>

    <label for="filtro-estado" class="solo-lectores">Filtrar por estado</label>
    <select id="filtro-estado" class="input">
        <option value="">Todos los estados</option>
        <?php foreach ($estados as $estado): ?>
            <option value="<?= e($estado) ?>"><?= estado_pedido_texto($estado) ?> (<?= (int) ($porEstado[$estado] ?? 0) ?>)</option>
        <?php endforeach; ?>
    </select>
</div>

<!-- ══════════ TABLA ══════════ -->
<div class="tabla-envoltura">
    <div class="tabla-envoltura__cabecera">
        <h2>Listado de pedidos</h2>
        <span class="texto-tenue" style="font-size:.82rem" id="conteo-pedidos">Cargando…</span>
    </div>

    <div class="tabla-scroll">
        <table class="datos">
            <thead>
                <tr>
                    <th>Pedido</th><th>Cliente</th><th>Ciudad</th>
                    <th class="num">Uds.</th><th class="num">Total</th>
                    <th>Estado</th><th class="num">Fecha</th><th class="num">Acción</th>
                </tr>
            </thead>
            <tbody id="cuerpo-pedidos">
                <tr><td colspan="8" style="text-align:center;padding:48px"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>
            </tbody>
        </table>
    </div>
</div>

<nav class="paginacion" id="paginacion-pedidos" aria-label="Paginación de pedidos"></nav>

<!-- ══════════ MODAL: DETALLE ══════════ -->
<div class="modal" id="modal-pedido" role="dialog" aria-modal="true" aria-labelledby="pedido-titulo">
    <div class="modal__caja" style="width:min(640px,100%)">
        <div class="modal__cabecera">
            <h3 id="pedido-titulo">Pedido</h3>
            <button class="modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="modal__cuerpo" id="pedido-cuerpo">
            <p class="texto-tenue" style="text-align:center;padding:32px"><i class="fa-solid fa-spinner fa-spin"></i></p>
        </div>

        <div class="modal__pie">
            <button class="btn btn-suave" type="button" data-cerrar-modal>Cerrar</button>
            <button class="btn btn-primario" type="button" id="btn-guardar-estado">Guardar cambios</button>
        </div>
    </div>
</div>
