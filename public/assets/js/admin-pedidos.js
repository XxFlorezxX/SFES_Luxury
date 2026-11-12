/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Panel: pedidos
   Tabla que se llena por fetch (filtro, búsqueda y paginación sin
   recargar) + modal de detalle con cambio de estado.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const BASE = window.SFES?.base || '/';
    const CSRF = window.SFES?.csrf || '';

    const cuerpo      = document.getElementById('cuerpo-pedidos');
    const paginacion  = document.getElementById('paginacion-pedidos');
    const conteo      = document.getElementById('conteo-pedidos');
    const campoBuscar = document.getElementById('buscar-pedido');
    const selEstado   = document.getElementById('filtro-estado');
    const modal       = document.getElementById('modal-pedido');

    let pagina = 1;
    let pedidoAbierto = null;

    const escapar = (t) => {
        const d = document.createElement('div');
        d.textContent = t ?? '';
        return d.innerHTML;
    };

    const debounce = (fn, ms = 320) => {
        let id;
        return (...a) => { clearTimeout(id); id = setTimeout(() => fn(...a), ms); };
    };

    /* ── Listado ── */
    async function cargar() {
        cuerpo.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:48px"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>';

        const params = new URLSearchParams({
            pagina,
            estado: selEstado.value,
            buscar: campoBuscar.value.trim(),
        });

        try {
            const r = await fetch(`${BASE}admin/pedidos/api?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const datos = await r.json();

            if (!datos.ok) {
                cuerpo.innerHTML = `<tr><td colspan="8" class="tabla-vacia">${escapar(datos.mensaje || 'No se pudo cargar.')}</td></tr>`;
                return;
            }

            conteo.textContent = `${datos.total} pedido(s)`;

            if (!datos.pedidos.length) {
                cuerpo.innerHTML = '<tr><td colspan="8"><div class="tabla-vacia"><i class="fa-solid fa-bag-shopping"></i><p>No hay pedidos con esos filtros.</p></div></td></tr>';
                paginacion.innerHTML = '';
                return;
            }

            cuerpo.innerHTML = datos.pedidos.map((p) => `
                <tr>
                    <td><span class="pedido-fila__id">${escapar(p.codigo)}</span></td>
                    <td>
                        <strong>${escapar(p.cliente)}</strong><br>
                        <span class="texto-tenue" style="font-size:.75rem">${escapar(p.telefono)}</span>
                    </td>
                    <td>${escapar(p.ciudad || '—')}</td>
                    <td class="num">${p.unidades}</td>
                    <td class="num"><strong>${escapar(p.total)}</strong></td>
                    <td><span class="badge estado-${escapar(p.estado)}">${escapar(p.estado_texto)}</span></td>
                    <td class="num">${escapar(p.fecha_rel)}</td>
                    <td>
                        <div class="acciones">
                            <button class="icono-accion" type="button" data-ver="${p.id}" title="Ver detalle">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>`).join('');

            pintarPaginacion(datos.pagina, datos.paginas);
        } catch (e) {
            cuerpo.innerHTML = '<tr><td colspan="8" class="tabla-vacia">Error de conexión con el servidor.</td></tr>';
        }
    }

    function pintarPaginacion(actual, total) {
        if (total <= 1) { paginacion.innerHTML = ''; return; }

        let html = `<a href="#" data-pagina="${actual - 1}" class="${actual <= 1 ? 'inerte' : ''}"><i class="fa-solid fa-chevron-left"></i></a>`;
        const desde = Math.max(1, actual - 2);
        const hasta = Math.min(total, desde + 4);

        for (let i = desde; i <= hasta; i++) {
            html += i === actual
                ? `<span class="actual">${i}</span>`
                : `<a href="#" data-pagina="${i}">${i}</a>`;
        }
        html += `<a href="#" data-pagina="${actual + 1}" class="${actual >= total ? 'inerte' : ''}"><i class="fa-solid fa-chevron-right"></i></a>`;
        paginacion.innerHTML = html;
    }

    paginacion.addEventListener('click', (e) => {
        const enlace = e.target.closest('[data-pagina]');
        if (!enlace || enlace.classList.contains('inerte')) return;
        e.preventDefault();
        pagina = parseInt(enlace.dataset.pagina, 10);
        cargar();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    campoBuscar.addEventListener('input', debounce(() => { pagina = 1; cargar(); }));
    selEstado.addEventListener('change', () => { pagina = 1; cargar(); });

    document.querySelectorAll('[data-filtro-estado]').forEach((boton) => {
        boton.addEventListener('click', () => {
            selEstado.value = boton.dataset.filtroEstado;
            pagina = 1;
            cargar();
        });
    });

    /* ── Detalle ── */
    document.addEventListener('click', async (e) => {
        const boton = e.target.closest('[data-ver]');
        if (!boton) return;

        pedidoAbierto = boton.dataset.ver;
        modal.classList.add('abierto');
        const contenedor = document.getElementById('pedido-cuerpo');
        contenedor.innerHTML = '<p class="texto-tenue" style="text-align:center;padding:32px"><i class="fa-solid fa-spinner fa-spin"></i></p>';

        try {
            const r = await fetch(`${BASE}admin/pedidos/api/${pedidoAbierto}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const datos = await r.json();

            if (!datos.ok) {
                contenedor.innerHTML = `<p>${escapar(datos.mensaje)}</p>`;
                return;
            }

            const p = datos.pedido;
            document.getElementById('pedido-titulo').textContent = 'Pedido ' + p.codigo;

            const items = datos.items.map((i) => `
                <div class="mini-linea">
                    <img src="${i.imagen}" alt="" loading="lazy">
                    <div class="mini-linea__txt">
                        <strong>${escapar(i.nombre)}</strong>
                        <span>${i.cantidad} × ${escapar(i.precio)}</span>
                    </div>
                    <strong style="font-size:.86rem">${escapar(i.subtotal)}</strong>
                </div>`).join('');

            const opciones = datos.estados.map((es) =>
                `<option value="${es}" ${es === p.estado ? 'selected' : ''}>${escapar(es.charAt(0).toUpperCase() + es.slice(1))}</option>`
            ).join('');

            contenedor.innerHTML = `
                <div class="campos-2" style="font-size:.86rem;margin-bottom:20px">
                    <div>
                        <p class="texto-tenue" style="font-size:.76rem">Cliente</p>
                        <p style="margin-bottom:10px"><strong>${escapar(p.cliente)}</strong></p>
                        <p class="texto-tenue" style="font-size:.76rem">Contacto</p>
                        <p>${escapar(p.telefono)}<br>${escapar(p.email || '—')}</p>
                    </div>
                    <div>
                        <p class="texto-tenue" style="font-size:.76rem">Entrega</p>
                        <p style="margin-bottom:10px">${escapar(p.direccion)}<br>${escapar(p.ciudad)}</p>
                        <p class="texto-tenue" style="font-size:.76rem">Pago · Fecha</p>
                        <p>${p.metodo_pago === 'transferencia' ? 'Transferencia' : 'Contraentrega'} · ${escapar(p.fecha)}</p>
                    </div>
                </div>

                ${p.notas ? `<div class="aviso aviso-info"><i class="fa-solid fa-note-sticky"></i><div><strong>Nota del cliente:</strong> ${escapar(p.notas)}</div></div>` : ''}

                <h4 style="font-size:.95rem;margin:20px 0 8px">Piezas</h4>
                ${items}

                <div style="border-top:1px solid var(--borde);margin-top:16px;padding-top:8px">
                    <div class="resumen__fila"><span>Subtotal</span><span>${escapar(p.subtotal)}</span></div>
                    ${p.cupon ? `<div class="resumen__fila descuento"><span>Descuento (${escapar(p.cupon)})</span><span>−${escapar(p.descuento)}</span></div>` : ''}
                    <div class="resumen__fila"><span>Envío</span><span>${escapar(p.envio)}</span></div>
                    <div class="resumen__total"><span>Total</span><strong>${escapar(p.total)}</strong></div>
                </div>

                <div class="aviso aviso-info" style="margin-top:16px">
                    <i class="fa-solid fa-calculator"></i>
                    <div>Costo de la mercancía <strong>${escapar(p.costo)}</strong> · utilidad <strong>${escapar(p.utilidad)}</strong> (${p.margen}%)</div>
                </div>

                <div class="campo" style="margin-top:20px">
                    <label for="nuevo-estado">Estado del pedido</label>
                    <select id="nuevo-estado" class="input">${opciones}</select>
                    <span class="ayuda">Cancelar un pedido devuelve las unidades al inventario automáticamente.</span>
                </div>

                <div class="campo">
                    <label for="notas-admin">Nota interna / para el cliente</label>
                    <textarea id="notas-admin" class="input" maxlength="1000">${escapar(p.notas_admin || '')}</textarea>
                </div>`;
        } catch (err) {
            contenedor.innerHTML = '<p>Error de conexión.</p>';
        }
    });

    /* ── Guardar estado ── */
    document.getElementById('btn-guardar-estado').addEventListener('click', async () => {
        if (!pedidoAbierto) return;

        const estado = document.getElementById('nuevo-estado')?.value;
        const notas  = document.getElementById('notas-admin')?.value || '';
        if (!estado) return;

        try {
            const r = await fetch(`${BASE}admin/pedidos/api/${pedidoAbierto}/estado`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ estado, notas, csrf_token: CSRF }),
            });
            const datos = await r.json();

            window.avisoAdmin(datos.mensaje, datos.ok ? 'exito' : 'error');

            if (datos.ok) {
                modal.classList.remove('abierto');
                cargar();
            }
        } catch (e) {
            window.avisoAdmin('No pudimos conectar con el servidor.', 'error');
        }
    });

    /* ── Cerrar modal ── */
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-modal]') || e.target === modal) {
            modal.classList.remove('abierto');
        }
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') modal.classList.remove('abierto'); });

    cargar();
})();
