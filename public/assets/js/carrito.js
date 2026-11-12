/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Bolsa de compras
   Cantidades, cupón y cálculo de envío sin recargar la página.
   Todos los importes que se pintan vienen del servidor: aquí no se
   calcula ningún precio.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const { aviso, enviar, BASE, CSRF, pintarContador } = window.SFESUI || {};
    if (!enviar) return;

    /* ── Pintar los totales que devuelve el servidor ── */
    function pintarTotales(t) {
        const asignar = (clave, texto) => {
            document.querySelectorAll(`[data-total="${clave}"]`).forEach((el) => { el.textContent = texto; });
        };

        const pesos = (n) => '$' + Math.round(n).toLocaleString('es-CO');

        asignar('subtotal', pesos(t.subtotal));
        asignar('descuento', '−' + pesos(t.descuento));
        asignar('envio', t.ciudad === '' ? 'Por calcular' : (t.envio > 0 ? pesos(t.envio) : 'Gratis'));
        asignar('total', pesos(t.total));

        document.querySelectorAll('[data-fila="descuento"]').forEach((el) => {
            el.style.display = t.descuento > 0 ? '' : 'none';
        });

        if (pintarContador) pintarContador(t.articulos);
    }

    /* ── Cantidades ── */
    async function cambiarCantidad(id, cantidad) {
        try {
            const r = await fetch(`${BASE}carrito/actualizar/${id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ cantidad, csrf_token: CSRF }),
            });
            const datos = await r.json();

            if (!datos.ok) {
                aviso(datos.mensaje, 'error');
                return;
            }

            if (datos.vacio || cantidad <= 0) {
                window.location.reload();
                return;
            }

            pintarTotales(datos.totales);

            // Subtotal de la línea
            const input = document.querySelector(`[data-input-cantidad="${id}"]`);
            const linea = document.querySelector(`[data-linea="${id}"]`);
            if (input && linea) {
                const real = parseInt(input.value, 10);
                if (real !== cantidad) aviso(datos.mensaje, 'error');
            }

            // El subtotal por línea lo recalcula el servidor en la próxima carga;
            // aquí se refresca leyendo el resumen para no duplicar lógica de precios.
            refrescarLineas();
        } catch (e) {
            aviso('No pudimos conectar con el servidor.', 'error');
        }
    }

    async function refrescarLineas() {
        try {
            const r = await fetch(`${BASE}carrito/resumen`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const datos = await r.json();
            if (!datos.ok) return;

            datos.items.forEach((item) => {
                const destino = document.querySelector(`[data-subtotal="${item.id}"]`);
                if (!destino) return;
                const precioNum = Number(String(item.precio).replace(/[^\d]/g, ''));
                destino.textContent = '$' + (precioNum * item.cantidad).toLocaleString('es-CO');
                const pie = destino.nextElementSibling;
                if (pie) pie.textContent = `${item.cantidad} × ${item.precio}`;
            });
        } catch (e) { /* silencioso: los totales ya se actualizaron */ }
    }

    document.addEventListener('click', (e) => {
        const boton = e.target.closest('[data-cantidad]');
        if (!boton) return;

        const id = boton.dataset.id;
        const input = document.querySelector(`[data-input-cantidad="${id}"]`);
        if (!input) return;

        const maximo = parseInt(input.max, 10) || 99;
        let valor = parseInt(input.value, 10) || 1;

        valor = boton.dataset.cantidad === 'mas' ? Math.min(valor + 1, maximo) : valor - 1;

        if (valor <= 0) {
            if (!confirm('¿Quitar esta pieza de la bolsa?')) return;
            window.location.href = `${BASE}carrito/eliminar/${id}`;
            return;
        }

        input.value = valor;
        cambiarCantidad(id, valor);
    });

    document.addEventListener('change', (e) => {
        const input = e.target.closest('[data-input-cantidad]');
        if (!input) return;
        const valor = Math.max(1, parseInt(input.value, 10) || 1);
        input.value = valor;
        cambiarCantidad(input.dataset.inputCantidad, valor);
    });

    /* ── Cupón ── */
    document.getElementById('form-cupon')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const campo = document.getElementById('codigo-cupon');
        const codigo = campo.value.trim();
        if (!codigo) { aviso('Escribe un código.', 'error'); return; }

        const datos = await enviar('carrito/cupon', { codigo });
        aviso(datos.mensaje, datos.ok ? 'exito' : 'error');

        if (datos.ok) {
            pintarTotales(datos.totales);
            setTimeout(() => window.location.reload(), 800);
        }
    });

    document.getElementById('quitar-cupon')?.addEventListener('click', async () => {
        const datos = await enviar('carrito/cupon/quitar');
        aviso(datos.mensaje);
        setTimeout(() => window.location.reload(), 600);
    });

    /* ── Envío ── */
    document.getElementById('ciudad-envio')?.addEventListener('change', async (e) => {
        const ciudad = e.target.value;
        if (!ciudad) return;

        const datos = await enviar('carrito/envio', { ciudad });
        if (datos.ok) {
            pintarTotales(datos.totales);
            aviso(datos.totales.envio > 0
                ? `Envío a ${ciudad}: $${Math.round(datos.totales.envio).toLocaleString('es-CO')} (${datos.totales.envio_dias})`
                : `¡Envío gratis a ${ciudad}!`);
        }
    });
})();
