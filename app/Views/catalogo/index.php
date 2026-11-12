<?php
/**
 * Vista: catálogo
 * Filtros por categoría, material, precio y disponibilidad + orden y
 * paginación. Los filtros viajan en la URL, así que un resultado
 * concreto se puede compartir o guardar en favoritos del navegador.
 */

/** Construye una URL conservando los filtros actuales y cambiando uno. */
$urlFiltro = static function (array $cambios) use ($filtros): string {
    $actual = [
        'categoria'      => $filtros['categoria'] !== 'Todos' ? $filtros['categoria'] : null,
        'material'       => $filtros['material']  !== 'Todos' ? $filtros['material']  : null,
        'q'              => $filtros['busqueda'] ?: null,
        'precio_min'     => $filtros['precio_min'] ?: null,
        'precio_max'     => $filtros['precio_max'] ?: null,
        'disponibilidad' => $filtros['disponibilidad'] !== 'todos' ? $filtros['disponibilidad'] : null,
        'orden'          => $filtros['orden'] !== 'relevancia' ? $filtros['orden'] : null,
        'pagina'         => $filtros['pagina'] > 1 ? $filtros['pagina'] : null,
    ];

    foreach ($cambios as $clave => $valor) {
        $actual[$clave] = $valor;
    }
    if (!array_key_exists('pagina', $cambios)) {
        $actual['pagina'] = null;   // cambiar un filtro vuelve a la página 1
    }

    $query = http_build_query(array_filter($actual, static fn($v) => $v !== null && $v !== ''));
    return BASE_URL . 'catalogo' . ($query ? '?' . $query : '');
};

$hayFiltros = $filtros['categoria'] !== 'Todos'
    || $filtros['material'] !== 'Todos'
    || $filtros['disponibilidad'] !== 'todos'
    || $filtros['precio_min'] !== ''
    || $filtros['precio_max'] !== ''
    || !empty($filtros['busqueda']);
?>

<section class="seccion-sm">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <div>
                <span class="eyebrow">Catálogo</span>
                <h2><?= !empty($terminoBusqueda) ? 'Resultados para “' . e($terminoBusqueda) . '”' : 'Todas nuestras piezas' ?></h2>
            </div>
            <button class="btn btn-contorno btn-sm filtros-movil-btn" id="abrir-filtros" type="button">
                <i class="fa-solid fa-sliders"></i> Filtros
            </button>
        </div>

        <div class="catalogo">
            <!-- ══════════ FILTROS ══════════ -->
            <aside class="filtros" id="filtros" aria-label="Filtros del catálogo">
                <div class="seccion-cabecera" style="margin-bottom:8px">
                    <h3 style="margin:0">Filtrar</h3>
                    <button class="icono-btn filtros-movil-btn" id="cerrar-filtros" type="button" aria-label="Cerrar filtros">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($hayFiltros): ?>
                    <a href="<?= BASE_URL ?>catalogo" class="btn-texto" style="font-size:.82rem">
                        <i class="fa-solid fa-rotate-left"></i> Limpiar todos los filtros
                    </a>
                <?php endif; ?>

                <div class="filtros__grupo">
                    <h3>Categoría</h3>
                    <div class="filtros__opciones">
                        <a href="<?= $urlFiltro(['categoria' => null]) ?>"
                           class="filtros__opcion <?= $filtros['categoria'] === 'Todos' ? 'activa' : '' ?>">
                            Todas <small><?= (int) array_sum($conteos) ?></small>
                        </a>
                        <?php foreach ($categorias as $cat): ?>
                            <a href="<?= $urlFiltro(['categoria' => $cat]) ?>"
                               class="filtros__opcion <?= $filtros['categoria'] === $cat ? 'activa' : '' ?>">
                                <?= e($cat) ?> <small><?= (int) ($conteos[$cat] ?? 0) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filtros__grupo">
                    <h3>Material</h3>
                    <div class="filtros__opciones">
                        <a href="<?= $urlFiltro(['material' => null]) ?>"
                           class="filtros__opcion <?= $filtros['material'] === 'Todos' ? 'activa' : '' ?>">Todos</a>
                        <?php foreach ($materiales as $mat): ?>
                            <a href="<?= $urlFiltro(['material' => $mat]) ?>"
                               class="filtros__opcion <?= $filtros['material'] === $mat ? 'activa' : '' ?>">
                                <?= e($mat) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filtros__grupo">
                    <h3>Precio</h3>
                    <form method="get" action="<?= BASE_URL ?>catalogo">
                        <?php foreach (['categoria' => $filtros['categoria'], 'material' => $filtros['material'], 'q' => $filtros['busqueda'], 'orden' => $filtros['orden']] as $campo => $valor): ?>
                            <?php if ($valor && $valor !== 'Todos' && $valor !== 'relevancia'): ?>
                                <input type="hidden" name="<?= $campo ?>" value="<?= e($valor) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="filtros__precio">
                            <label for="precio_min" class="solo-lectores">Precio mínimo</label>
                            <input type="number" id="precio_min" name="precio_min" min="0" step="10000"
                                   placeholder="<?= (int) $rangoPrecios['minimo'] ?>" value="<?= e($filtros['precio_min']) ?>">
                            <span class="texto-tenue">—</span>
                            <label for="precio_max" class="solo-lectores">Precio máximo</label>
                            <input type="number" id="precio_max" name="precio_max" min="0" step="10000"
                                   placeholder="<?= (int) $rangoPrecios['maximo'] ?>" value="<?= e($filtros['precio_max']) ?>">
                        </div>
                        <button class="btn btn-suave btn-sm btn-bloque" type="submit" style="margin-top:12px">Aplicar</button>
                    </form>
                </div>

                <div class="filtros__grupo">
                    <h3>Disponibilidad</h3>
                    <div class="filtros__opciones">
                        <?php
                        $opcionesDisp = ['todos' => 'Ver todo', 'disponible' => 'En stock', 'oferta' => 'En oferta', 'agotado' => 'Agotadas'];
                        foreach ($opcionesDisp as $clave => $texto): ?>
                            <a href="<?= $urlFiltro(['disponibilidad' => $clave === 'todos' ? null : $clave]) ?>"
                               class="filtros__opcion <?= $filtros['disponibilidad'] === $clave ? 'activa' : '' ?>">
                                <?= $texto ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <!-- ══════════ RESULTADOS ══════════ -->
            <div>
                <div class="catalogo__barra">
                    <p>
                        <strong><?= (int) $resultado['total'] ?></strong>
                        <?= $resultado['total'] === 1 ? 'pieza encontrada' : 'piezas encontradas' ?>
                        <?php if ($resultado['paginas'] > 1): ?>
                            · página <?= $resultado['pagina'] ?> de <?= $resultado['paginas'] ?>
                        <?php endif; ?>
                    </p>

                    <form method="get" action="<?= BASE_URL ?>catalogo" id="form-orden">
                        <?php foreach (['categoria' => $filtros['categoria'], 'material' => $filtros['material'], 'q' => $filtros['busqueda'], 'precio_min' => $filtros['precio_min'], 'precio_max' => $filtros['precio_max'], 'disponibilidad' => $filtros['disponibilidad']] as $campo => $valor): ?>
                            <?php if ($valor && $valor !== 'Todos' && $valor !== 'todos'): ?>
                                <input type="hidden" name="<?= $campo ?>" value="<?= e($valor) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <label for="orden" class="solo-lectores">Ordenar por</label>
                        <select name="orden" id="orden" onchange="this.form.submit()">
                            <?php foreach ($ordenes as $clave => $texto): ?>
                                <option value="<?= $clave ?>" <?= $filtros['orden'] === $clave ? 'selected' : '' ?>><?= $texto ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if ($hayFiltros): ?>
                    <div class="chips">
                        <?php if ($filtros['categoria'] !== 'Todos'): ?>
                            <a href="<?= $urlFiltro(['categoria' => null]) ?>" class="chip"><?= e($filtros['categoria']) ?> <i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                        <?php if ($filtros['material'] !== 'Todos'): ?>
                            <a href="<?= $urlFiltro(['material' => null]) ?>" class="chip"><?= e($filtros['material']) ?> <i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                        <?php if ($filtros['precio_min'] !== '' || $filtros['precio_max'] !== ''): ?>
                            <a href="<?= $urlFiltro(['precio_min' => null, 'precio_max' => null]) ?>" class="chip">
                                <?= $filtros['precio_min'] !== '' ? moneda($filtros['precio_min']) : 'Desde $0' ?>
                                — <?= $filtros['precio_max'] !== '' ? moneda($filtros['precio_max']) : 'sin tope' ?>
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($filtros['disponibilidad'] !== 'todos'): ?>
                            <a href="<?= $urlFiltro(['disponibilidad' => null]) ?>" class="chip"><?= e($opcionesDisp[$filtros['disponibilidad']] ?? '') ?> <i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($productos)): ?>
                    <div class="vacio">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <h3>No encontramos piezas con esos filtros</h3>
                        <p>Prueba ampliando el rango de precio o quitando algún filtro.</p>
                        <a href="<?= BASE_URL ?>catalogo" class="btn btn-primario">Ver todo el catálogo</a>
                    </div>
                <?php else: ?>
                    <div class="grid-productos">
                        <?php foreach ($productos as $p): ?>
                            <?php require __DIR__ . '/../shared/tarjeta-producto.php'; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($resultado['paginas'] > 1): ?>
                        <nav class="paginacion" aria-label="Paginación del catálogo">
                            <a href="<?= $urlFiltro(['pagina' => max(1, $resultado['pagina'] - 1)]) ?>"
                               class="<?= $resultado['pagina'] <= 1 ? 'inerte' : '' ?>" aria-label="Página anterior">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>

                            <?php
                            $desde = max(1, $resultado['pagina'] - 2);
                            $hasta = min($resultado['paginas'], $desde + 4);
                            $desde = max(1, $hasta - 4);
                            for ($i = $desde; $i <= $hasta; $i++): ?>
                                <?php if ($i === $resultado['pagina']): ?>
                                    <span class="actual" aria-current="page"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= $urlFiltro(['pagina' => $i]) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <a href="<?= $urlFiltro(['pagina' => min($resultado['paginas'], $resultado['pagina'] + 1)]) ?>"
                               class="<?= $resultado['pagina'] >= $resultado['paginas'] ? 'inerte' : '' ?>" aria-label="Página siguiente">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
