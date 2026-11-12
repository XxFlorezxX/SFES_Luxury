/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Panel: inventario
   Ajustes manuales con motivo obligatorio y consulta del kardex.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const BASE = window.SFES?.base || '/';

    const modalAjuste = document.getElementById('modal-ajuste');
    const modalKardex = document.getElementById('modal-kardex');

    const escapar = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };

    /* ── Abrir ajuste ── */
    document.addEventListener('click', (e) => {
        const boton = e.target.closest('[data-ajustar]');
        if (!boton) return;

        document.getElementById('ajuste-id').value      = boton.dataset.ajustar;
        document.getElementById('ajuste-nombre').textContent = boton.dataset.nombre;
        document.getElementById('ajuste-actual').value  = boton.dataset.stock + ' unidades';
        document.getElementById('ajuste-stock').value   = boton.dataset.stock;
        document.getElementById('ajuste-motivo').value  = '';

        modalAjuste.classList.add('abierto');
        document.getElementById('ajuste-stock').focus();
    });

    /* ── Kardex ── */
    document.addEventListener('click', async (e) => {
        const boton = e.target.closest('[data-kardex]');
        if (!boton) return;

        modalKardex.classList.add('abierto');
        const contenedor = document.getElementById('kardex-cuerpo');
        contenedor.innerHTML = '<p class="texto-tenue" style="text-align:center;padding:32px"><i class="fa-solid fa-spinner fa-spin"></i></p>';

        try {
            const r = await fetch(`${BASE}admin/inventario/api/${boton.dataset.kardex}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();

            if (!d.ok) { contenedor.innerHTML = `<p>${escapar(d.mensaje)}</p>`; return; }

            document.getElementById('kardex-titulo').textContent = 'Historial: ' + d.producto.nombre;

            const filas = d.movimientos.length
                ? d.movimientos.map((m) => `
                    <tr>
                        <td>${escapar(m.fecha)}</td>
                        <td><span class="badge ${m.tipo === 'entrada' ? 'badge-exito' : 'badge-aviso'}">${m.tipo === 'entrada' ? '+' : '−'}${m.cantidad}</span></td>
                        <td class="num">${m.stock}</td>
                        <td>${escapar(m.motivo)}</td>
                    </tr>`).join('')
                : '<tr><td colspan="4" style="text-align:center;padding:24px" class="texto-tenue">Sin movimientos registrados.</td></tr>';

            contenedor.innerHTML = `
                <div class="metricas" style="margin-bottom:20px">
                    <div class="metrica"><span>Stock actual</span><strong>${d.producto.stock}</strong></div>
                    <div class="metrica"><span>Mínimo</span><strong>${d.producto.stock_minimo}</strong></div>
                    <div class="metrica"><span>Costo unitario</span><strong>${escapar(d.producto.costo)}</strong></div>
                </div>
                <div class="tabla-scroll" style="max-height:340px;overflow-y:auto">
                    <table class="datos">
                        <thead><tr><th>Fecha</th><th>Movimiento</th><th class="num">Stock</th><th>Motivo</th></tr></thead>
                        <tbody>${filas}</tbody>
                    </table>
                </div>`;
        } catch (err) {
            contenedor.innerHTML = '<p>Error de conexión.</p>';
        }
    });

    /* ── Cerrar modales ── */
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-modal]') || e.target === modalAjuste || e.target === modalKardex) {
            modalAjuste.classList.remove('abierto');
            modalKardex.classList.remove('abierto');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            modalAjuste.classList.remove('abierto');
            modalKardex.classList.remove('abierto');
        }
    });

    /* ── Gráfica de flujo ── */
    const nodo = document.getElementById('datos-inventario');
    if (nodo && window.SFESGraficas) {
        let flujo = [];
        try { flujo = JSON.parse(nodo.textContent) || []; } catch (e) { flujo = []; }

        function pintar() {
            SFESGraficas.barras('#g-flujo', {
                titulo: 'Flujo de inventario',
                ejeX: 'Día',
                formato: 'numero',
                etiquetas: flujo.map((f) => String(f.dia).slice(5)),
                series: [
                    { nombre: 'Entradas', datos: flujo.map((f) => Number(f.entradas) || 0) },
                    { nombre: 'Salidas',  datos: flujo.map((f) => Number(f.salidas) || 0) },
                ],
            });
        }

        pintar();
        document.addEventListener('sfes:tema', pintar);
    }
})();
