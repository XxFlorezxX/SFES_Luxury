<?php
/**
 * Vista: inventario
 * Valor del stock, alertas de reposición, ajuste manual con motivo y
 * kardex por pieza (historial auditable de cada movimiento).
 */
?>
<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Inventario</h1>
            <p><?= (int) $totalStock ?> unidades en <?= (int) $resultado['total'] ?> referencias</p>
        </div>
    </div>
    <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
</div>

<!-- ══════════ INDICADORES ══════════ -->
<section class="kpis">
    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Valor a costo</span>
            <span class="kpi__icono"><i class="fa-solid fa-warehouse"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($valor['costo']) ?></span>
        <div class="kpi__pie"><span>Capital inmovilizado en mercancía</span></div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Valor a precio de venta</span>
            <span class="kpi__icono"><i class="fa-solid fa-tags"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($valor['venta']) ?></span>
        <div class="kpi__pie"><span>Si se vendiera todo el stock</span></div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Utilidad potencial</span>
            <span class="kpi__icono"><i class="fa-solid fa-arrow-trend-up"></i></span>
        </div>
        <span class="kpi__valor" style="color:var(--exito)"><?= moneda($valor['potencial']) ?></span>
        <div class="kpi__pie"><span>Margen esperado del inventario</span></div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Reposición urgente</span>
            <span class="kpi__icono"><i class="fa-solid fa-triangle-exclamation"></i></span>
        </div>
        <span class="kpi__valor" style="color:<?= count($bajoStock) > 0 ? 'var(--aviso)' : 'inherit' ?>"><?= count($bajoStock) ?></span>
        <div class="kpi__pie"><span>Piezas en o bajo el mínimo</span></div>
    </div>
</section>

<!-- ══════════ ALERTAS ══════════ -->
<?php if (!empty($bajoStock)): ?>
<section class="panel" style="margin-bottom:24px">
    <div class="panel__cabecera">
        <div>
            <h2>Piezas por reponer</h2>
            <p>Están en o por debajo de su stock mínimo</p>
        </div>
    </div>

    <div class="tabla-scroll">
        <table class="datos">
            <thead>
                <tr><th>Pieza</th><th class="num">Stock</th><th class="num">Mínimo</th><th class="num">Reponer</th><th class="num">Costo estimado</th><th class="num">Acción</th></tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($bajoStock, 0, 12) as $pieza): ?>
                    <?php $sugerido = max(1, ((int) $pieza['stock_minimo'] * 3) - (int) $pieza['stock']); ?>
                    <tr>
                        <td>
                            <div class="mini">
                                <img src="<?= e(imagen_producto($pieza['imagen'])) ?>" alt="" loading="lazy">
                                <div>
                                    <strong><?= e($pieza['nombre']) ?></strong>
                                    <span><?= e($pieza['sku'] ?: $pieza['categoria']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="num">
                            <span class="badge <?= (int) $pieza['stock'] === 0 ? 'badge-error' : 'badge-aviso' ?>"><?= (int) $pieza['stock'] ?></span>
                        </td>
                        <td class="num"><?= (int) $pieza['stock_minimo'] ?></td>
                        <td class="num"><?= $sugerido ?> uds</td>
                        <td class="num"><?= moneda($sugerido * (float) ($pieza['costo'] ?? 0)) ?></td>
                        <td>
                            <div class="acciones">
                                <button class="icono-accion" type="button" title="Ajustar stock"
                                        data-ajustar="<?= (int) $pieza['id'] ?>"
                                        data-nombre="<?= e($pieza['nombre']) ?>"
                                        data-stock="<?= (int) $pieza['stock'] ?>">
                                    <i class="fa-solid fa-plus-minus"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<!-- ══════════ FILTROS ══════════ -->
<form class="filtros-barra" method="get" action="<?= BASE_URL ?>admin/inventario">
    <div class="buscar-campo">
        <i class="fa-solid fa-magnifying-glass"></i>
        <label for="buscar" class="solo-lectores">Buscar pieza</label>
        <input type="search" id="buscar" name="buscar" class="input" placeholder="Buscar pieza…" value="<?= e($filtros['busqueda']) ?>">
    </div>

    <label for="categoria" class="solo-lectores">Categoría</label>
    <select name="categoria" id="categoria" class="input" onchange="this.form.submit()">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $filtros['categoria'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn-suave btn-sm" type="submit">Filtrar</button>
</form>

<!-- ══════════ EXISTENCIAS ══════════ -->
<div class="tabla-envoltura" style="margin-bottom:24px">
    <div class="tabla-envoltura__cabecera">
        <h2>Existencias</h2>
    </div>

    <div class="tabla-scroll">
        <table class="datos">
            <thead>
                <tr><th>Pieza</th><th>Categoría</th><th class="num">Stock</th><th class="num">Mínimo</th><th class="num">Valor a costo</th><th class="num">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr>
                        <td>
                            <div class="mini">
                                <img src="<?= e(imagen_producto($p['imagen'])) ?>" alt="" loading="lazy">
                                <div>
                                    <strong><?= e($p['nombre']) ?></strong>
                                    <span><?= e($p['sku'] ?: 'sin SKU') ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= e($p['categoria']) ?></td>
                        <td class="num">
                            <span class="badge <?= (int) $p['stock'] === 0 ? 'badge-error' : ((int) $p['stock'] <= (int) $p['stock_minimo'] ? 'badge-aviso' : 'badge-exito') ?>">
                                <?= (int) $p['stock'] ?>
                            </span>
                        </td>
                        <td class="num"><?= (int) $p['stock_minimo'] ?></td>
                        <td class="num"><?= moneda((int) $p['stock'] * (float) $p['costo']) ?></td>
                        <td>
                            <div class="acciones">
                                <button class="icono-accion" type="button" title="Ver historial" data-kardex="<?= (int) $p['id'] ?>">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </button>
                                <button class="icono-accion" type="button" title="Ajustar stock"
                                        data-ajustar="<?= (int) $p['id'] ?>"
                                        data-nombre="<?= e($p['nombre']) ?>"
                                        data-stock="<?= (int) $p['stock'] ?>">
                                    <i class="fa-solid fa-plus-minus"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($resultado['paginas'] > 1): ?>
    <nav class="paginacion">
        <?php for ($i = 1; $i <= $resultado['paginas']; $i++): ?>
            <?php if ($i === $resultado['pagina']): ?>
                <span class="actual"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= BASE_URL ?>admin/inventario?pagina=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<!-- ══════════ MOVIMIENTOS RECIENTES ══════════ -->
<section class="paneles paneles-2">
    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Flujo de inventario</h2>
                <p>Entradas y salidas de los últimos 30 días</p>
            </div>
        </div>
        <div class="grafica" id="g-flujo"></div>
    </div>

    <div class="tabla-envoltura">
        <div class="tabla-envoltura__cabecera">
            <h2>Movimientos recientes</h2>
        </div>

        <?php if (empty($movimientos)): ?>
            <div class="tabla-vacia"><i class="fa-solid fa-boxes-stacked"></i><p>Sin movimientos registrados.</p></div>
        <?php else: ?>
            <div class="tabla-scroll" style="max-height:420px;overflow-y:auto">
                <table class="datos">
                    <thead><tr><th>Pieza</th><th>Motivo</th><th class="num">Cant.</th><th class="num">Cuándo</th></tr></thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td><strong style="font-weight:500"><?= e($mov['producto']) ?></strong></td>
                                <td><span class="texto-tenue" style="font-size:.8rem"><?= e($mov['motivo']) ?></span></td>
                                <td class="num">
                                    <span class="badge <?= $mov['tipo'] === 'entrada' ? 'badge-exito' : 'badge-aviso' ?>">
                                        <?= $mov['tipo'] === 'entrada' ? '+' : '−' ?><?= (int) $mov['cantidad'] ?>
                                    </span>
                                </td>
                                <td class="num"><?= fecha_relativa($mov['creado_en']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════ MODAL: AJUSTE ══════════ -->
<div class="modal" id="modal-ajuste" role="dialog" aria-modal="true">
    <div class="modal__caja" style="width:min(480px,100%)">
        <div class="modal__cabecera">
            <h3>Ajustar inventario</h3>
            <button class="modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="post" action="<?= BASE_URL ?>admin/inventario/ajustar">
            <div class="modal__cuerpo">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="producto_id" id="ajuste-id">

                <p style="margin-bottom:20px">Pieza: <strong id="ajuste-nombre"></strong></p>

                <div class="campos-2">
                    <div class="campo">
                        <label>Stock actual</label>
                        <input type="text" id="ajuste-actual" class="input" readonly>
                    </div>
                    <div class="campo">
                        <label for="ajuste-stock">Nuevo stock <span class="req">*</span></label>
                        <input type="number" id="ajuste-stock" name="stock" min="0" required>
                    </div>
                </div>

                <div class="campo">
                    <label for="ajuste-motivo">Motivo del ajuste <span class="req">*</span></label>
                    <input type="text" id="ajuste-motivo" name="motivo" required minlength="3" maxlength="120"
                           list="motivos" placeholder="Reposición del proveedor, merma, corrección de conteo…">
                    <datalist id="motivos">
                        <option value="Reposición del proveedor"></option>
                        <option value="Corrección de conteo físico"></option>
                        <option value="Merma o pieza dañada"></option>
                        <option value="Devolución de cliente"></option>
                        <option value="Muestra o exhibición"></option>
                    </datalist>
                    <span class="ayuda">Queda registrado en el historial con tu usuario y la fecha.</span>
                </div>
            </div>

            <div class="modal__pie">
                <button class="btn btn-suave" type="button" data-cerrar-modal>Cancelar</button>
                <button class="btn btn-primario" type="submit">Guardar ajuste</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════ MODAL: KARDEX ══════════ -->
<div class="modal" id="modal-kardex" role="dialog" aria-modal="true">
    <div class="modal__caja">
        <div class="modal__cabecera">
            <h3 id="kardex-titulo">Historial de la pieza</h3>
            <button class="modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal__cuerpo" id="kardex-cuerpo"></div>
    </div>
</div>

<script id="datos-inventario" type="application/json"><?= json_encode($flujo, JSON_UNESCAPED_UNICODE) ?></script>
