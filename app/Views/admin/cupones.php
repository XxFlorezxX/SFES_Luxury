<div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="icono-btn abrir-lateral" id="abrir-lateral" type="button" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1>Cupones</h1>
            <p><?= (int) $resumen['activos'] ?> activos de <?= (int) $resumen['total'] ?> · <?= (int) $resumen['usos'] ?> usos acumulados</p>
        </div>
    </div>

    <div class="admin-topbar__acciones">
        <button class="btn btn-primario btn-sm" type="button" id="btn-nuevo-cupon">
            <i class="fa-solid fa-plus"></i> Nuevo cupón
        </button>
        <button class="icono-btn" id="btn-tema-admin" type="button" aria-label="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
    </div>
</div>

<section class="kpis">
    <div class="kpi">
        <div class="kpi__top"><span class="kpi__etiqueta">Cupones activos</span><span class="kpi__icono"><i class="fa-solid fa-ticket"></i></span></div>
        <span class="kpi__valor"><?= (int) $resumen['activos'] ?></span>
        <div class="kpi__pie"><span>de <?= (int) $resumen['total'] ?> creados</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__top"><span class="kpi__etiqueta">Usos acumulados</span><span class="kpi__icono"><i class="fa-solid fa-hand-pointer"></i></span></div>
        <span class="kpi__valor"><?= (int) $resumen['usos'] ?></span>
        <div class="kpi__pie"><span>redenciones históricas</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__top"><span class="kpi__etiqueta">Descuento otorgado</span><span class="kpi__icono"><i class="fa-solid fa-percent"></i></span></div>
        <span class="kpi__valor"><?= moneda($resumen['descuento']) ?></span>
        <div class="kpi__pie"><span>en lo que va del mes</span></div>
    </div>
</section>

<div class="tabla-envoltura">
    <div class="tabla-envoltura__cabecera">
        <h2>Todos los cupones</h2>
    </div>

    <?php if (empty($cupones)): ?>
        <div class="tabla-vacia">
            <i class="fa-solid fa-ticket"></i>
            <p>Todavía no has creado cupones.</p>
        </div>
    <?php else: ?>
        <div class="tabla-scroll">
            <table class="datos">
                <thead>
                    <tr>
                        <th>Código</th><th>Descuento</th><th class="num">Compra mínima</th>
                        <th class="num">Usos</th><th class="num">Vigencia</th><th>Estado</th><th class="num">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cupones as $c): ?>
                        <?php
                        $vencido = !empty($c['vence_en']) && $c['vence_en'] < date('Y-m-d');
                        $agotado = $c['usos_maximos'] !== null && (int) $c['usos_actuales'] >= (int) $c['usos_maximos'];
                        ?>
                        <tr>
                            <td>
                                <strong class="pedido-fila__id"><?= e($c['codigo']) ?></strong>
                                <?php if (!empty($c['descripcion'])): ?>
                                    <br><span class="texto-tenue" style="font-size:.75rem"><?= e(recortar($c['descripcion'], 60)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= $c['tipo'] === 'porcentaje'
                                    ? number_format((float) $c['valor'], 0) . '%'
                                    : moneda($c['valor']) ?></strong>
                            </td>
                            <td class="num"><?= (float) $c['minimo_compra'] > 0 ? moneda($c['minimo_compra']) : '—' ?></td>
                            <td class="num">
                                <?= (int) $c['usos_actuales'] ?><?= $c['usos_maximos'] !== null ? ' / ' . (int) $c['usos_maximos'] : '' ?>
                            </td>
                            <td class="num"><?= !empty($c['vence_en']) ? fecha_corta($c['vence_en']) : 'Sin fecha' ?></td>
                            <td>
                                <?php if ((int) $c['activo'] !== 1): ?>
                                    <span class="badge badge-neutro">Desactivado</span>
                                <?php elseif ($vencido): ?>
                                    <span class="badge badge-error">Vencido</span>
                                <?php elseif ($agotado): ?>
                                    <span class="badge badge-aviso">Agotado</span>
                                <?php else: ?>
                                    <span class="badge badge-exito">Activo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acciones">
                                    <button class="icono-accion" type="button" title="Editar"
                                            data-editar-cupon='<?= e(json_encode([
                                                'id' => (int) $c['id'],
                                                'codigo' => $c['codigo'],
                                                'descripcion' => $c['descripcion'],
                                                'tipo' => $c['tipo'],
                                                'valor' => (float) $c['valor'],
                                                'minimo_compra' => (float) $c['minimo_compra'],
                                                'usos_maximos' => $c['usos_maximos'],
                                                'inicia_en' => $c['inicia_en'],
                                                'vence_en' => $c['vence_en'],
                                                'activo' => (int) $c['activo'],
                                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="post" action="<?= BASE_URL ?>admin/cupones/eliminar"
                                          onsubmit="return confirm('¿Eliminar el cupón <?= e($c['codigo']) ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button class="icono-accion peligro" type="submit" title="Eliminar">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ══════════ MODAL ══════════ -->
<div class="modal" id="modal-cupon" role="dialog" aria-modal="true" aria-labelledby="cupon-titulo">
    <div class="modal__caja">
        <div class="modal__cabecera">
            <h3 id="cupon-titulo">Nuevo cupón</h3>
            <button class="modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="post" action="<?= BASE_URL ?>admin/cupones/guardar" id="form-cupon">
            <div class="modal__cuerpo">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="c-id" value="0">

                <div class="campos-2">
                    <div class="campo">
                        <label for="c-codigo">Código <span class="req">*</span></label>
                        <input type="text" id="c-codigo" name="codigo" required minlength="3" maxlength="40"
                               placeholder="BIENVENIDA10" style="text-transform:uppercase">
                        <span class="ayuda">Solo letras, números, guion y guion bajo.</span>
                    </div>
                    <div class="campo">
                        <label for="c-tipo">Tipo <span class="req">*</span></label>
                        <select id="c-tipo" name="tipo" required>
                            <option value="porcentaje">Porcentaje (%)</option>
                            <option value="fijo">Monto fijo ($)</option>
                        </select>
                    </div>
                </div>

                <div class="campo">
                    <label for="c-descripcion">Descripción</label>
                    <input type="text" id="c-descripcion" name="descripcion" maxlength="160"
                           placeholder="Descuento de bienvenida para la primera compra">
                </div>

                <div class="campos-2">
                    <div class="campo">
                        <label for="c-valor">Valor <span class="req">*</span></label>
                        <input type="number" id="c-valor" name="valor" min="1" step="1" required>
                    </div>
                    <div class="campo">
                        <label for="c-minimo">Compra mínima</label>
                        <input type="number" id="c-minimo" name="minimo_compra" min="0" step="1000" value="0">
                    </div>
                </div>

                <div class="campos-2">
                    <div class="campo">
                        <label for="c-usos">Límite de usos</label>
                        <input type="number" id="c-usos" name="usos_maximos" min="0" placeholder="Sin límite">
                        <span class="ayuda">Déjalo vacío o en 0 para usos ilimitados.</span>
                    </div>
                    <div class="campo">
                        <label for="c-vence">Vence el</label>
                        <input type="date" id="c-vence" name="vence_en">
                    </div>
                </div>

                <div class="campo">
                    <label for="c-inicia">Empieza a regir el</label>
                    <input type="date" id="c-inicia" name="inicia_en">
                    <span class="ayuda">Déjalo vacío para que aplique desde hoy.</span>
                </div>

                <label class="check">
                    <input type="checkbox" name="activo" id="c-activo" checked>
                    <span>Cupón activo</span>
                </label>
            </div>

            <div class="modal__pie">
                <button class="btn btn-suave" type="button" data-cerrar-modal>Cancelar</button>
                <button class="btn btn-primario" type="submit">Guardar cupón</button>
            </div>
        </form>
    </div>
</div>
