<?php
/**
 * Vista: dashboard del panel
 * Indicadores del periodo con variación contra el periodo anterior,
 * evolución de ventas, reparto por estado y categoría, ranking de
 * productos y alertas de inventario.
 */
$presets = [
    '7d'   => '7 días',
    '30d'  => '30 días',
    '90d'  => '90 días',
    'mes'  => 'Este mes',
    'anio' => 'Este año',
];

/** Pinta la variación porcentual contra el periodo anterior. */
$pintarVariacion = static function (?float $valor): string {
    if ($valor === null) {
        return '<span class="variacion igual">sin datos previos</span>';
    }
    $clase = $valor > 0 ? 'sube' : ($valor < 0 ? 'baja' : 'igual');
    $icono = $valor > 0 ? 'fa-arrow-trend-up' : ($valor < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
    return '<span class="variacion ' . $clase . '"><i class="fa-solid ' . $icono . '"></i> '
        . ($valor > 0 ? '+' : '') . number_format($valor, 1, ',', '.') . '%</span>';
};

$estadosEtiqueta = [
    'pendiente'  => 'Pendientes',
    'confirmado' => 'Confirmados',
    'procesando' => 'En preparación',
    'enviado'    => 'Enviados',
    'entregado'  => 'Entregados',
    'cancelado'  => 'Cancelados',
];
?>

<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Dashboard</h1>
            <p><?= fecha_corta($rango['desde']) ?> — <?= fecha_corta($rango['hasta']) ?> · <?= (int) $resumen['periodo']['dias'] ?> días</p>
        </div>
    </div>

    <div class="admin-topbar__acciones">
        <nav class="periodos" aria-label="Periodo del informe">
            <?php foreach ($presets as $clave => $texto): ?>
                <a href="<?= BASE_URL ?>admin/panel?periodo=<?= $clave ?>" class="<?= $rango['preset'] === $clave ? 'activo' : '' ?>"><?= $texto ?></a>
            <?php endforeach; ?>
        </nav>
        <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
    </div>
</div>

<!-- ═══════════════ INDICADORES ═══════════════ -->
<section class="kpis">
    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Ingresos</span>
            <span class="kpi__icono"><i class="fa-solid fa-sack-dollar"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($resumen['ingresos']) ?></span>
        <div class="kpi__pie">
            <?= $pintarVariacion($resumen['var_ingresos']) ?>
            <span>vs. periodo anterior</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Pedidos</span>
            <span class="kpi__icono"><i class="fa-solid fa-bag-shopping"></i></span>
        </div>
        <span class="kpi__valor"><?= (int) $resumen['pedidos'] ?></span>
        <div class="kpi__pie">
            <?= $pintarVariacion($resumen['var_pedidos']) ?>
            <span><?= (int) $resumen['unidades'] ?> unidades</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Utilidad bruta</span>
            <span class="kpi__icono"><i class="fa-solid fa-chart-pie"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($resumen['utilidad_bruta']) ?></span>
        <div class="kpi__pie">
            <span class="variacion <?= $resumen['margen'] >= 40 ? 'sube' : ($resumen['margen'] >= 20 ? 'igual' : 'baja') ?>">
                <?= number_format($resumen['margen'], 1, ',', '.') ?>% margen
            </span>
            <span>costo <?= moneda($resumen['costo']) ?></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__top">
            <span class="kpi__etiqueta">Ticket promedio</span>
            <span class="kpi__icono"><i class="fa-solid fa-receipt"></i></span>
        </div>
        <span class="kpi__valor"><?= moneda($resumen['ticket_promedio']) ?></span>
        <div class="kpi__pie"><span>por pedido en el periodo</span></div>
    </div>
</section>

<!-- ═══════════════ VENTAS EN EL TIEMPO ═══════════════ -->
<section class="paneles paneles-2-1">
    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Ventas del periodo</h2>
                <p>Ingresos y utilidad bruta, día por día</p>
            </div>
        </div>
        <div class="grafica" id="g-ventas-dia"></div>
    </div>

    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Pedidos por estado</h2>
                <p>Situación actual de toda la operación</p>
            </div>
        </div>
        <div class="grafica" id="g-estados"></div>
    </div>
</section>

<!-- ═══════════════ MENSUAL + CATEGORÍAS ═══════════════ -->
<section class="paneles paneles-2">
    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Últimos 12 meses</h2>
                <p>Ingresos frente a gastos operativos</p>
            </div>
        </div>
        <div class="grafica" id="g-mensual"></div>
    </div>

    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Ventas por línea</h2>
                <p>Qué categorías mueven el negocio</p>
            </div>
        </div>
        <div id="g-categorias"></div>
    </div>
</section>

<!-- ═══════════════ INVENTARIO Y TOP ═══════════════ -->
<section class="paneles paneles-3">
    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Piezas más vendidas</h2>
                <p>Unidades en el periodo</p>
            </div>
        </div>
        <div id="g-top-productos"></div>
    </div>

    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Inventario</h2>
                <p><?= (int) $totalProductos ?> referencias · <?= (int) $totalStock ?> unidades</p>
            </div>
            <a href="<?= BASE_URL ?>admin/inventario" class="btn-texto" style="font-size:.8rem">Gestionar →</a>
        </div>

        <div style="margin-bottom:16px">
            <div class="resumen__fila"><span>Valor a costo</span><strong><?= moneda($inventario['costo']) ?></strong></div>
            <div class="resumen__fila"><span>Valor a precio de venta</span><strong><?= moneda($inventario['venta']) ?></strong></div>
            <div class="resumen__fila" style="border-top:1px solid var(--borde);padding-top:12px">
                <span>Utilidad potencial</span>
                <strong style="color:var(--exito)"><?= moneda($inventario['potencial']) ?></strong>
            </div>
        </div>

        <?php if (!empty($bajoStock)): ?>
            <div class="aviso aviso-alerta" style="margin-bottom:12px;padding:10px 12px">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><strong><?= count($bajoStock) ?></strong> pieza(s) por debajo del stock mínimo</div>
            </div>
            <div class="lista-media">
                <?php foreach (array_slice($bajoStock, 0, 4) as $pieza): ?>
                    <div class="lista-media__item">
                        <img src="<?= e(imagen_producto($pieza['imagen'])) ?>" alt="" loading="lazy">
                        <div class="lista-media__txt">
                            <strong><?= e($pieza['nombre']) ?></strong>
                            <span>Mínimo: <?= (int) $pieza['stock_minimo'] ?></span>
                        </div>
                        <span class="badge <?= (int) $pieza['stock'] === 0 ? 'badge-error' : 'badge-aviso' ?>">
                            <?= (int) $pieza['stock'] ?> uds
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="texto-tenue" style="font-size:.84rem"><i class="fa-solid fa-circle-check" style="color:var(--exito)"></i> Todo el inventario está por encima del mínimo.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel__cabecera">
            <div>
                <h2>Mejores clientas</h2>
                <p>Por total comprado histórico</p>
            </div>
        </div>

        <?php if (empty($mejoresClientes)): ?>
            <p class="texto-tenue" style="font-size:.84rem">Todavía no hay compras registradas.</p>
        <?php else: ?>
            <div class="lista-media">
                <?php foreach ($mejoresClientes as $cliente): ?>
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

<!-- ═══════════════ ÚLTIMOS PEDIDOS Y MOVIMIENTOS ═══════════════ -->
<section class="paneles paneles-2">
    <div class="tabla-envoltura">
        <div class="tabla-envoltura__cabecera">
            <h2>Últimos pedidos</h2>
            <a href="<?= BASE_URL ?>admin/pedidos" class="btn-texto" style="font-size:.8rem">Ver todos →</a>
        </div>

        <?php if (empty($ultimosPedidos)): ?>
            <div class="tabla-vacia">
                <i class="fa-solid fa-bag-shopping"></i>
                <p>Todavía no hay pedidos registrados.</p>
            </div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="datos">
                    <thead>
                        <tr><th>Pedido</th><th>Cliente</th><th class="num">Total</th><th>Estado</th><th class="num">Fecha</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosPedidos as $pedido): ?>
                            <tr>
                                <td><span class="pedido-fila__id"><?= e($pedido['codigo'] ?: '#' . $pedido['id']) ?></span></td>
                                <td><?= e($pedido['nombres']) ?></td>
                                <td class="num"><?= moneda($pedido['total']) ?></td>
                                <td><span class="badge estado-<?= e($pedido['estado']) ?>"><?= estado_pedido_texto($pedido['estado']) ?></span></td>
                                <td class="num"><?= fecha_relativa($pedido['creado_en']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="tabla-envoltura">
        <div class="tabla-envoltura__cabecera">
            <h2>Movimientos de inventario</h2>
            <a href="<?= BASE_URL ?>admin/inventario" class="btn-texto" style="font-size:.8rem">Ver todos →</a>
        </div>

        <?php if (empty($movimientos)): ?>
            <div class="tabla-vacia">
                <i class="fa-solid fa-boxes-stacked"></i>
                <p>Sin movimientos registrados.</p>
            </div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="datos">
                    <thead>
                        <tr><th>Pieza</th><th>Movimiento</th><th class="num">Stock</th><th class="num">Cuándo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td>
                                    <div class="mini">
                                        <img src="<?= e(imagen_producto($mov['imagen'])) ?>" alt="" loading="lazy">
                                        <div>
                                            <strong><?= e($mov['producto']) ?></strong>
                                            <span><?= e($mov['motivo']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $mov['tipo'] === 'entrada' ? 'badge-exito' : 'badge-aviso' ?>">
                                        <?= $mov['tipo'] === 'entrada' ? '+' : '−' ?><?= (int) $mov['cantidad'] ?>
                                    </span>
                                </td>
                                <td class="num"><?= (int) $mov['stock_resultante'] ?></td>
                                <td class="num"><?= fecha_relativa($mov['creado_en']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Datos para las gráficas -->
<script id="datos-panel" type="application/json"><?= json_encode([
    'serieDiaria'  => $serieDiaria,
    'serieMensual' => $serieMensual,
    'porEstado'    => array_map(
        static fn($clave) => ['etiqueta' => $estadosEtiqueta[$clave], 'valor' => $porEstado[$clave]],
        array_keys($porEstado)
    ),
    'porCategoria' => array_map(
        static fn(array $c): array => ['etiqueta' => $c['categoria'], 'valor' => $c['ingresos']],
        $porCategoria
    ),
    'topProductos' => array_map(
        static fn(array $p): array => ['etiqueta' => $p['nombre'], 'valor' => $p['unidades']],
        $topProductos
    ),
], JSON_UNESCAPED_UNICODE) ?></script>
