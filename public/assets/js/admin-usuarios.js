/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Panel: clientes
   Listado paginado con búsqueda y ficha de detalle en modal.
   Solo se muestran los datos necesarios para atender al cliente.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const BASE = window.SFES?.base || '/';

    const cuerpo     = document.getElementById('cuerpo-clientes');
    const paginacion = document.getElementById('paginacion-clientes');
    const conteo     = document.getElementById('conteo-clientes');
    const buscar     = document.getElementById('buscar-cliente');
    const modal      = document.getElementById('modal-cliente');

    let pagina = 1;

    const escapar = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };
    const debounce = (fn, ms = 320) => { let id; return (...a) => { clearTimeout(id); id = setTimeout(() => fn(...a), ms); }; };

    async function cargar() {
        cuerpo.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:48px"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>';

        const params = new URLSearchParams({ pagina, buscar: buscar.value.trim() });

        try {
            const r = await fetch(`${BASE}admin/usuarios/api?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const datos = await r.json();

            if (!datos.ok) {
                cuerpo.innerHTML = '<tr><td colspan="6" class="tabla-vacia">No se pudo cargar el listado.</td></tr>';
                return;
            }

            conteo.textContent = `${datos.total} cliente(s)`;

            if (!datos.usuarios.length) {
                cuerpo.innerHTML = '<tr><td colspan="6"><div class="tabla-vacia"><i class="fa-solid fa-users"></i><p>No hay clientes que coincidan.</p></div></td></tr>';
                paginacion.innerHTML = '';
                return;
            }

            cuerpo.innerHTML = datos.usuarios.map((u) => `
                <tr>
                    <td>
                        <div class="mini">
                            <span class="resena__avatar">${escapar(u.nombre.charAt(0).toUpperCase())}</span>
                            <div>
                                <strong>${escapar(u.nombre)}</strong>
                                <span>Último acceso: ${escapar(u.ultimo_acceso)}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        ${escapar(u.email)}<br>
                        <span class="texto-tenue" style="font-size:.75rem">${escapar(u.telefono)}</span>
                    </td>
                    <td class="num">${u.pedidos}</td>
                    <td class="num"><strong>${escapar(u.gastado)}</strong></td>
                    <td class="num">${escapar(u.registro)}</td>
                    <td>
                        <div class="acciones">
                            <button class="icono-accion" type="button" data-ficha="${u.id}" title="Ver ficha">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>`).join('');

            pintarPaginacion(datos.pagina, datos.paginas);
        } catch (e) {
            cuerpo.innerHTML = '<tr><td colspan="6" class="tabla-vacia">Error de conexión.</td></tr>';
        }
    }

    function pintarPaginacion(actual, total) {
        if (total <= 1) { paginacion.innerHTML = ''; return; }
        let html = '';
        for (let i = 1; i <= total; i++) {
            html += i === actual ? `<span class="actual">${i}</span>` : `<a href="#" data-pagina="${i}">${i}</a>`;
        }
        paginacion.innerHTML = html;
    }

    paginacion.addEventListener('click', (e) => {
        const enlace = e.target.closest('[data-pagina]');
        if (!enlace) return;
        e.preventDefault();
        pagina = parseInt(enlace.dataset.pagina, 10);
        cargar();
    });

    buscar.addEventListener('input', debounce(() => { pagina = 1; cargar(); }));

    /* ── Ficha ── */
    document.addEventListener('click', async (e) => {
        const boton = e.target.closest('[data-ficha]');
        if (!boton) return;

        modal.classList.add('abierto');
        const contenedor = document.getElementById('cliente-cuerpo');
        contenedor.innerHTML = '<p class="texto-tenue" style="text-align:center;padding:32px"><i class="fa-solid fa-spinner fa-spin"></i></p>';

        try {
            const r = await fetch(`${BASE}admin/usuarios/api/${boton.dataset.ficha}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();

            if (!d.ok) { contenedor.innerHTML = `<p>${escapar(d.mensaje)}</p>`; return; }

            document.getElementById('cliente-titulo').textContent = d.usuario.nombre;

            const pedidos = d.pedidos.length
                ? d.pedidos.map((p) => `
                    <div class="lista-media__item">
                        <div class="lista-media__txt">
                            <strong>${escapar(p.codigo)}</strong>
                            <span>${escapar(p.fecha)} · ${escapar(p.estado)}</span>
                        </div>
                        <span class="lista-media__valor">${escapar(p.total)}</span>
                    </div>`).join('')
                : '<p class="texto-tenue" style="font-size:.84rem">Todavía no ha hecho pedidos.</p>';

            contenedor.innerHTML = `
                <div class="metricas" style="margin-bottom:20px">
                    <div class="metrica"><span>Pedidos</span><strong>${d.resumen.total_pedidos}</strong></div>
                    <div class="metrica"><span>Total comprado</span><strong>${escapar(d.resumen.total_gastado_texto)}</strong></div>
                    <div class="metrica"><span>Ticket promedio</span><strong>${escapar(d.resumen.ticket_texto)}</strong></div>
                </div>

                <div class="campos-2" style="font-size:.86rem;margin-bottom:20px">
                    <div>
                        <p class="texto-tenue" style="font-size:.76rem">Correo</p>
                        <p style="margin-bottom:10px">${escapar(d.usuario.email)}</p>
                        <p class="texto-tenue" style="font-size:.76rem">Teléfono</p>
                        <p>${escapar(d.usuario.telefono || '—')}</p>
                    </div>
                    <div>
                        <p class="texto-tenue" style="font-size:.76rem">Registro</p>
                        <p style="margin-bottom:10px">${escapar(d.usuario.registro)}</p>
                        <p class="texto-tenue" style="font-size:.76rem">Dirección guardada</p>
                        <p>${d.direccion ? escapar(d.direccion.direccion + ', ' + d.direccion.ciudad) : '—'}</p>
                    </div>
                </div>

                <h4 style="font-size:.95rem;margin-bottom:8px">Últimos pedidos</h4>
                <div class="lista-media">${pedidos}</div>`;
        } catch (err) {
            contenedor.innerHTML = '<p>Error de conexión.</p>';
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-modal]') || e.target === modal) modal.classList.remove('abierto');
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') modal.classList.remove('abierto'); });

    cargar();
})();
