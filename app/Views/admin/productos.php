<?php
/**
 * Vista: administración de productos
 * Tabla del catálogo + formulario en modal para crear y editar.
 * Muestra margen por pieza, que es lo que permite decidir precios con
 * criterio y no a ojo.
 */
$materialesSugeridos = array_unique(array_merge($materiales, [
    'Plata 925', 'Baño de oro 18k', 'Oro blanco', 'Oro rosa', 'Acero quirúrgico',
]));
$categoriasSugeridas = array_unique(array_merge($categorias, [
    'Anillos', 'Aretes', 'Collares', 'Pulseras', 'Dijes', 'Sets',
]));
?>

<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Productos</h1>
            <p><?= (int) $resultado['total'] ?> referencias · inventario valorado en <?= moneda($inventario['costo']) ?> a costo</p>
        </div>
    </div>

    <div class="admin-topbar__acciones">
        <?php if ($bajoStock > 0): ?>
            <a href="<?= BASE_URL ?>admin/inventario" class="badge badge-aviso" style="padding:8px 14px">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= (int) $bajoStock ?> con stock bajo
            </a>
        <?php endif; ?>
        <button class="btn btn-primario btn-sm" type="button" id="btn-nuevo">
            <i class="fa-solid fa-plus"></i> Nuevo producto
        </button>
        <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
    </div>
</div>

<!-- ══════════ FILTROS ══════════ -->
<form class="filtros-barra" method="get" action="<?= BASE_URL ?>admin/productos">
    <div class="buscar-campo">
        <i class="fa-solid fa-magnifying-glass"></i>
        <label for="buscar" class="solo-lectores">Buscar producto</label>
        <input type="search" id="buscar" name="buscar" class="input" placeholder="Buscar por nombre, SKU o material…"
               value="<?= e($filtros['busqueda']) ?>">
    </div>

    <label for="categoria" class="solo-lectores">Categoría</label>
    <select name="categoria" id="categoria" class="input" onchange="this.form.submit()">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $filtros['categoria'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn-suave btn-sm" type="submit">Filtrar</button>
    <?php if ($filtros['busqueda'] !== '' || $filtros['categoria'] !== 'Todos'): ?>
        <a href="<?= BASE_URL ?>admin/productos" class="btn-texto" style="font-size:.82rem">Limpiar</a>
    <?php endif; ?>
</form>

<!-- ══════════ TABLA ══════════ -->
<div class="tabla-envoltura">
    <?php if (empty($productos)): ?>
        <div class="tabla-vacia">
            <i class="fa-solid fa-gem"></i>
            <p>No hay productos que coincidan con la búsqueda.</p>
            <button class="btn btn-primario btn-sm" type="button" id="btn-nuevo-vacio" style="margin-top:16px">
                <i class="fa-solid fa-plus"></i> Agregar el primero
            </button>
        </div>
    <?php else: ?>
        <div class="tabla-scroll">
            <table class="datos">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th class="num">Costo</th>
                        <th class="num">Precio</th>
                        <th class="num">Margen</th>
                        <th class="num">Stock</th>
                        <th>Estado</th>
                        <th class="num">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                        <?php
                        $margen = (float) $p['precio'] > 0
                            ? (((float) $p['precio'] - (float) $p['costo']) / (float) $p['precio']) * 100
                            : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="mini">
                                    <img src="<?= e(imagen_producto($p['imagen'])) ?>" alt="" loading="lazy">
                                    <div>
                                        <strong><?= e($p['nombre']) ?></strong>
                                        <span><?= e($p['sku'] ?: 'sin SKU') ?> · <?= e($p['material']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($p['categoria']) ?></td>
                            <td class="num"><?= moneda($p['costo']) ?></td>
                            <td class="num">
                                <strong><?= moneda($p['precio']) ?></strong>
                                <?php if (!empty($p['precio_antes'])): ?>
                                    <br><span class="texto-tenue" style="font-size:.72rem;text-decoration:line-through"><?= moneda($p['precio_antes']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="num">
                                <span class="variacion <?= $margen >= 45 ? 'sube' : ($margen >= 25 ? 'igual' : 'baja') ?>">
                                    <?= number_format($margen, 0) ?>%
                                </span>
                            </td>
                            <td class="num">
                                <span class="badge <?= (int) $p['stock'] === 0 ? 'badge-error' : ((int) $p['stock'] <= (int) $p['stock_minimo'] ? 'badge-aviso' : 'badge-neutro') ?>">
                                    <?= (int) $p['stock'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int) $p['activo'] === 1): ?>
                                    <span class="badge badge-exito">Publicado</span>
                                <?php else: ?>
                                    <span class="badge badge-neutro">Oculto</span>
                                <?php endif; ?>
                                <?php if ((int) $p['destacado'] === 1): ?>
                                    <span class="badge badge-info" style="margin-left:4px"><i class="fa-solid fa-star"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acciones">
                                    <a href="<?= BASE_URL ?>producto/<?= e($p['slug']) ?>" target="_blank" rel="noopener"
                                       class="icono-accion" title="Ver en la tienda"><i class="fa-solid fa-eye"></i></a>
                                    <button class="icono-accion" type="button" data-editar="<?= (int) $p['id'] ?>" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="icono-accion peligro" type="button"
                                            data-eliminar="<?= (int) $p['id'] ?>" data-nombre="<?= e($p['nombre']) ?>" title="Eliminar">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($resultado['paginas'] > 1): ?>
    <nav class="paginacion" aria-label="Paginación de productos">
        <?php for ($i = 1; $i <= $resultado['paginas']; $i++): ?>
            <?php if ($i === $resultado['pagina']): ?>
                <span class="actual"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= BASE_URL ?>admin/productos?pagina=<?= $i ?><?= $filtros['busqueda'] ? '&buscar=' . urlencode($filtros['busqueda']) : '' ?><?= $filtros['categoria'] !== 'Todos' ? '&categoria=' . urlencode($filtros['categoria']) : '' ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<!-- ══════════ MODAL: FORMULARIO ══════════ -->
<div class="modal" id="modal-producto" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
    <div class="modal__caja" style="width:min(720px,100%)">
        <div class="modal__cabecera">
            <h3 id="modal-titulo">Nuevo producto</h3>
            <button class="modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="post" action="<?= BASE_URL ?>admin/productos/guardar" enctype="multipart/form-data" id="form-producto">
            <div class="modal__cuerpo">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="f-id" value="0">

                <div class="campos-2">
                    <div class="campo">
                        <label for="f-nombre">Nombre <span class="req">*</span></label>
                        <input type="text" id="f-nombre" name="nombre" required maxlength="150">
                    </div>
                    <div class="campo">
                        <label for="f-sku">SKU / referencia</label>
                        <input type="text" id="f-sku" name="sku" maxlength="40" placeholder="SFS-ANI-001">
                    </div>
                </div>

                <div class="campo">
                    <label for="f-descripcion">Descripción <span class="req">*</span></label>
                    <textarea id="f-descripcion" name="descripcion" required minlength="10" maxlength="4000"
                              placeholder="Material, medidas, cierre, presentación…"></textarea>
                </div>

                <div class="campos-2">
                    <div class="campo">
                        <label for="f-categoria">Categoría <span class="req">*</span></label>
                        <input type="text" id="f-categoria" name="categoria" required maxlength="60" list="lista-categorias">
                        <datalist id="lista-categorias">
                            <?php foreach ($categoriasSugeridas as $cat): ?>
                                <option value="<?= e($cat) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="campo">
                        <label for="f-material">Material</label>
                        <input type="text" id="f-material" name="material" maxlength="60" list="lista-materiales" value="Plata 925">
                        <datalist id="lista-materiales">
                            <?php foreach ($materialesSugeridos as $mat): ?>
                                <option value="<?= e($mat) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="campos-2">
                    <div class="campo">
                        <label for="f-costo">Costo <span class="req">*</span></label>
                        <input type="number" id="f-costo" name="costo" min="0" step="1000" required value="0">
                        <span class="ayuda">Lo que te cuesta la pieza. Alimenta el margen y la contabilidad.</span>
                    </div>
                    <div class="campo">
                        <label for="f-precio">Precio de venta <span class="req">*</span></label>
                        <input type="number" id="f-precio" name="precio" min="0" step="1000" required>
                        <span class="ayuda" id="ayuda-margen">Margen: —</span>
                    </div>
                </div>

                <div class="campos-2">
                    <div class="campo">
                        <label for="f-precio-antes">Precio tachado (oferta)</label>
                        <input type="number" id="f-precio-antes" name="precio_antes" min="0" step="1000">
                        <span class="ayuda">Debe ser mayor al precio actual. Déjalo vacío si no hay oferta.</span>
                    </div>
                    <div class="campo">
                        <label for="f-peso">Peso (gramos)</label>
                        <input type="number" id="f-peso" name="peso_gramos" min="0" step="0.1">
                    </div>
                </div>

                <div class="campos-2">
                    <div class="campo">
                        <label for="f-stock">Stock <span class="req">*</span></label>
                        <input type="number" id="f-stock" name="stock" min="0" required value="0">
                    </div>
                    <div class="campo">
                        <label for="f-stock-minimo">Stock mínimo (alerta)</label>
                        <input type="number" id="f-stock-minimo" name="stock_minimo" min="0" value="3">
                    </div>
                </div>

                <div class="campo">
                    <label for="f-imagen">Imagen</label>
                    <input type="file" id="f-imagen" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif">
                    <span class="ayuda">JPG, PNG, WEBP o GIF · máximo 3 MB. Al editar, déjalo vacío para conservar la actual.</span>
                    <img id="f-imagen-actual" src="" alt="" style="display:none;width:96px;height:96px;object-fit:cover;border-radius:8px;margin-top:8px">
                </div>

                <div style="display:flex;gap:24px;flex-wrap:wrap">
                    <label class="check">
                        <input type="checkbox" name="activo" id="f-activo" checked>
                        <span>Publicado en la tienda</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="destacado" id="f-destacado">
                        <span>Mostrar como destacado en el inicio</span>
                    </label>
                </div>
            </div>

            <div class="modal__pie">
                <button class="btn btn-suave" type="button" data-cerrar-modal>Cancelar</button>
                <button class="btn btn-primario" type="submit">Guardar producto</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════ MODAL: ELIMINAR ══════════ -->
<div class="modal" id="modal-eliminar" role="dialog" aria-modal="true">
    <div class="modal__caja" style="width:min(440px,100%)">
        <div class="modal__cabecera">
            <h3>Eliminar producto</h3>
            <button class="modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="post" action="<?= BASE_URL ?>admin/productos/eliminar">
            <div class="modal__cuerpo">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="eliminar-id">

                <p>¿Seguro que quieres eliminar <strong id="eliminar-nombre"></strong>?</p>
                <div class="aviso aviso-info" style="margin-top:16px">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>Si la pieza ya tuvo ventas no se borra: se oculta de la tienda para conservar el historial de pedidos y la contabilidad.</div>
                </div>
            </div>

            <div class="modal__pie">
                <button class="btn btn-suave" type="button" data-cerrar-modal>Cancelar</button>
                <button class="btn btn-peligro" type="submit">Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>
