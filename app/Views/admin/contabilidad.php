<?php
/**
 * Vista: contabilidad
 * Estado de resultados del periodo, evolución mensual, desglose de
 * gastos y registro de egresos operativos.
 *
 * La utilidad se calcula con el costo congelado en cada venta, no con
 * el costo actual del producto: así un cambio de precio del proveedor
 * no altera la utilidad de meses ya cerrados.
 */
$presets = [
    'mes'          => 'Este mes',
    'mes_anterior' => 'Mes anterior',
    '90d'          => 'Últimos 90 días',
    'anio'         => 'Este año',
];
?>

<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Contabilidad</h1>
            <p><?= fecha_corta($rango['desde']) ?> — <?= fecha_corta($rango['hasta']) ?></p>
        </div>
    </div>

    <div class="admin-topbar__acciones">
        <nav class="periodos" aria-label="Periodo contable">
            <?php foreach ($presets as $clave => $texto): ?>
                <a href="<?= BASE_URL ?>admin/contabilidad?periodo=<?= $clave ?>" class="<?= $rango['preset'] === $clave ? 'activo' : '' ?>"><?= $texto ?></a>
            <?php endforeach; ?>
        </nav>
        <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
    </div>
</div>

<!-- ══════════ INDICADORES ══════════ -->
<section class="kpis">
    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Ventas netas</span>
            <span class="kpi__icono"><i class="fa-solid fa-cash-register"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($estado['ventas_netas']) ?></span>
        <div class="kpi__pie"><span><?= (int) $estado['pedidos'] ?> pedidos · <?= (int) $estado['unidades'] ?> unidades</span></div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Utilidad bruta</span>
            <span class="kpi__icono"><i class="fa-solid fa-chart-pie"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($estado['utilidad_bruta']) ?></span>
        <div class="kpi__pie">
            <span class="variacion <?= $estado['margen_bruto'] >= 40 ? 'sube' : ($estado['margen_bruto'] >= 20 ? 'igual' : 'baja') ?>">
                <?= number_format($estado['margen_bruto'], 1, ',', '.') ?>% margen
            </span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Gastos operativos</span>
            <span class="kpi__icono"><i class="fa-solid fa-receipt"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($estado['gastos_totales']) ?></span>
        <div class="kpi__pie"><span><?= count($gastos) ?> registro(s) en el periodo</span></div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Utilidad neta</span>
            <span class="kpi__icono"><i class="fa-solid fa-scale-balanced"></i></span>
        </div>
        <span class="kpi__valor" style="color:<?= $estado['utilidad_neta'] >= 0 ? 'var(--exito)' : 'var(--error)' ?>">
            <?= moneda($estado['utilidad_neta']) ?>
        </span>
        <div class="kpi__pie">
            <span class="variacion <?= $estado['utilidad_neta'] >= 0 ? 'sube' : 'baja' ?>">
                <?= number_format($estado['margen_neto'], 1, ',', '.') ?>% sobre ventas
            </span>
        </div>
    </div>
</section>

<!-- ══════════ ESTADO DE RESULTADOS ══════════ -->
<section class="paneles paneles-2">
    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Estado de resultados</h2>
                <p><?= fecha_corta($rango['desde']) ?> — <?= fecha_corta($rango['hasta']) ?></p>
            </div>
        </div>

        <table class="pyg">
            <tbody>
                <tr>
                    <td>Ventas brutas</td>
                    <td><?= moneda($estado['ventas_brutas']) ?></td>
                </tr>
                <tr>
                    <td class="sangria">(−) Descuentos y cupones</td>
                    <td>−<?= moneda($estado['descuentos']) ?></td>
                </tr>
                <tr class="subtotal">
                    <td>Ventas netas</td>
                    <td><?= moneda($estado['ventas_netas']) ?></td>
                </tr>
                <tr>
                    <td class="sangria">(−) Costo de la mercancía vendida <span class="nota">— costo congelado al vender</span></td>
                    <td>−<?= moneda($estado['costo_mercancia']) ?></td>
                </tr>
                <tr class="subtotal">
                    <td>Utilidad bruta <span class="nota">(<?= number_format($estado['margen_bruto'], 1, ',', '.') ?>%)</span></td>
                    <td><?= moneda($estado['utilidad_bruta']) ?></td>
                </tr>
                <tr>
                    <td class="sangria">(+) Ingresos por envío cobrado</td>
                    <td><?= moneda($estado['ingresos_envio']) ?></td>
                </tr>

                <?php foreach ($estado['gastos_categoria'] as $categoria): ?>
                    <tr>
                        <td class="sangria">(−) <?= e($categoria['etiqueta']) ?></td>
                        <td>−<?= moneda($categoria['total']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($estado['gastos_categoria'])): ?>
                    <tr>
                        <td class="sangria">(−) Gastos operativos</td>
                        <td>−<?= moneda(0) ?></td>
                    </tr>
                <?php endif; ?>

                <tr class="resultado <?= $estado['utilidad_neta'] < 0 ? 'negativo' : '' ?>">
                    <td>Utilidad neta</td>
                    <td><?= moneda($estado['utilidad_neta']) ?></td>
                </tr>
            </tbody>
        </table>

        <p class="texto-tenue" style="font-size:.76rem;margin-top:16px">
            <i class="fa-solid fa-circle-info"></i>
            Los pedidos cancelados no cuentan como ingreso. El inventario en bodega vale
            <strong><?= moneda($inventario['costo']) ?></strong> a costo y no aparece como gasto hasta que se vende.
        </p>
    </div>

    <div>
        <div class="panel" style="margin-bottom:24px">
            <div class="panel__cabecera">
                <div>
                    <h2>Ingresos, costo y utilidad</h2>
                    <p>Evolución de los últimos 12 meses</p>
                </div>
            </div>
            <div class="grafica" id="g-pyg-mensual"></div>
        </div>

        <div class="panel">
            <div class="panel__cabecera">
                <div>
                    <h2>En qué se van los gastos</h2>
                    <p>Reparto del periodo por categoría</p>
                </div>
            </div>
            <div id="g-gastos"></div>
        </div>
    </div>
</section>

<!-- ══════════ GASTOS ══════════ -->
<section class="paneles paneles-2-1">
    <div class="tabla-envoltura">
        <div class="tabla-envoltura__cabecera">
            <h2>Gastos del periodo</h2>
            <form method="get" action="<?= BASE_URL ?>admin/contabilidad" style="display:flex;gap:8px;align-items:center">
                <input type="hidden" name="periodo" value="<?= e($rango['preset']) ?>">
                <label for="filtro-categoria" class="solo-lectores">Filtrar por categoría</label>
                <select name="categoria" id="filtro-categoria" class="input" onchange="this.form.submit()" style="min-height:38px">
                    <option value="">Todas</option>
                    <?php foreach ($categoriasGasto as $clave => $etiqueta): ?>
                        <option value="<?= e($clave) ?>" <?= $categoriaFiltro === $clave ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($gastos)): ?>
            <div class="tabla-vacia">
                <i class="fa-solid fa-receipt"></i>
                <p>No hay gastos registrados en este periodo.</p>
            </div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="datos">
                    <thead>
                        <tr><th>Concepto</th><th>Categoría</th><th class="num">Monto</th><th class="num">Fecha</th><th class="num"></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gastos as $gasto): ?>
                            <tr>
                                <td>
                                    <strong style="font-weight:500"><?= e($gasto['concepto']) ?></strong>
                                    <?php if (!empty($gasto['nota'])): ?>
                                        <br><span class="texto-tenue" style="font-size:.75rem"><?= e(recortar($gasto['nota'], 70)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-neutro"><?= e($categoriasGasto[$gasto['categoria']] ?? $gasto['categoria']) ?></span></td>
                                <td class="num"><strong><?= moneda($gasto['monto']) ?></strong></td>
                                <td class="num"><?= fecha_corta($gasto['fecha']) ?></td>
                                <td>
                                    <form method="post" action="<?= BASE_URL ?>admin/contabilidad/eliminar"
                                          onsubmit="return confirm('¿Eliminar este gasto del registro contable?')">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $gasto['id'] ?>">
                                        <button class="icono-accion peligro" type="submit" title="Eliminar gasto">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Registrar gasto</h2>
                <p>Entra al estado de resultados del periodo</p>
            </div>
        </div>

        <form method="post" action="<?= BASE_URL ?>admin/contabilidad/gasto">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="campo">
                <label for="concepto">Concepto <span class="req">*</span></label>
                <input type="text" id="concepto" name="concepto" required minlength="3" maxlength="160"
                       placeholder="Compra de cadenas al proveedor">
            </div>

            <div class="campo">
                <label for="categoria-gasto">Categoría <span class="req">*</span></label>
                <select id="categoria-gasto" name="categoria" required>
                    <?php foreach ($categoriasGasto as $clave => $etiqueta): ?>
                        <option value="<?= e($clave) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campos-2">
                <div class="campo">
                    <label for="monto">Monto <span class="req">*</span></label>
                    <input type="number" id="monto" name="monto" min="1" step="1000" required>
                </div>
                <div class="campo">
                    <label for="fecha">Fecha <span class="req">*</span></label>
                    <input type="date" id="fecha" name="fecha" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="campo">
                <label for="nota">Nota (opcional)</label>
                <textarea id="nota" name="nota" maxlength="500" placeholder="Número de factura, proveedor…"></textarea>
            </div>

            <button class="btn btn-primario btn-bloque" type="submit">
                <i class="fa-solid fa-plus"></i> Registrar gasto
            </button>
        </form>
    </div>
</section>

<!-- ══════════ TOP PRODUCTOS POR UTILIDAD ══════════ -->
<section class="panel">
    <div class="panel__cabecera">
        <div>
            <h2>Qué piezas dejan más utilidad</h2>
            <p>Ordenadas por unidades vendidas en el periodo</p>
        </div>
    </div>

    <?php if (empty($topProductos)): ?>
        <p class="texto-tenue" style="font-size:.84rem">Sin ventas en este periodo.</p>
    <?php else: ?>
        <div class="tabla-scroll">
            <table class="datos">
                <thead>
                    <tr><th>Pieza</th><th class="num">Unidades</th><th class="num">Ingresos</th><th class="num">Utilidad</th><th class="num">Margen</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topProductos as $p): ?>
                        <?php $margen = $p['ingresos'] > 0 ? ($p['utilidad'] / $p['ingresos']) * 100 : 0; ?>
                        <tr>
                            <td>
                                <div class="mini">
                                    <img src="<?= e(imagen_producto($p['imagen'])) ?>" alt="" loading="lazy">
                                    <div><strong><?= e($p['nombre']) ?></strong></div>
                                </div>
                            </td>
                            <td class="num"><?= (int) $p['unidades'] ?></td>
                            <td class="num"><?= moneda($p['ingresos']) ?></td>
                            <td class="num"><strong style="color:var(--exito)"><?= moneda($p['utilidad']) ?></strong></td>
                            <td class="num">
                                <span class="variacion <?= $margen >= 45 ? 'sube' : ($margen >= 25 ? 'igual' : 'baja') ?>">
                                    <?= number_format($margen, 0) ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script id="datos-contabilidad" type="application/json"><?= json_encode([
    'serieMensual' => $serieMensual,
    'gastos'       => array_map(
        static fn(array $c): array => ['etiqueta' => $c['etiqueta'], 'valor' => $c['total']],
        $porCategoria
    ),
], JSON_UNESCAPED_UNICODE) ?></script>
