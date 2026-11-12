/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Finalizar compra
   Envía los datos de entrega y espera la confirmación del servidor.
   Los importes se recalculan allá: aquí solo se muestran.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const { aviso, enviar, BASE, CSRF } = window.SFESUI || {};
    if (!enviar) return;

    const formulario = document.getElementById('form-checkout');
    const boton      = document.getElementById('btn-confirmar');
    if (!formulario || !boton) return;

    const pesos = (n) => '$' + Math.round(n).toLocaleString('es-CO');

    /* ── Recalcular el envío al elegir ciudad ── */
    document.getElementById('ciudad')?.addEventListener('change', async (e) => {
        const ciudad = e.target.value;
        if (!ciudad) return;

        const datos = await enviar('carrito/envio', { ciudad });
        if (!datos.ok) return;

        const t = datos.totales;
        const asignar = (clave, texto) => {
            document.querySelectorAll(`[data-total="${clave}"]`).forEach((el) => { el.textContent = texto; });
        };

        asignar('subtotal', pesos(t.subtotal));
        asignar('envio', t.envio > 0 ? pesos(t.envio) : 'Gratis');
        asignar('total', pesos(t.total));
    });

    /* ── Confirmar el pedido ── */
    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();

        const datos = {
            nombres:          document.getElementById('nombres').value.trim(),
            telefono:         document.getElementById('telefono').value.trim(),
            direccion:        document.getElementById('direccion').value.trim(),
            ciudad:           document.getElementById('ciudad').value,
            especificaciones: document.getElementById('especificaciones').value.trim(),
            email:            document.getElementById('email')?.value.trim() || '',
            metodo_pago:      formulario.querySelector('input[name="metodo_pago"]:checked')?.value || 'contraentrega',
        };

        if (!datos.nombres || !datos.telefono || !datos.direccion || !datos.ciudad) {
            aviso('Completa nombre, teléfono, ciudad y dirección.', 'error');
            return;
        }

        const textoOriginal = boton.innerHTML;
        boton.setAttribute('aria-disabled', 'true');
        boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando tu pedido…';

        try {
            const respuesta = await fetch(`${BASE}checkout/confirmar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ ...datos, csrf_token: CSRF }),
            });
            const r = await respuesta.json();

            if (r.ok) {
                boton.innerHTML = '<i class="fa-solid fa-check"></i> ¡Pedido confirmado!';
                window.location.href = r.redirigir;
                return;
            }

            aviso(r.mensaje, 'error');

            if (r.recargar) {
                setTimeout(() => { window.location.href = BASE + 'carrito'; }, 2200);
                return;
            }
        } catch (err) {
            aviso('No pudimos conectar con el servidor. Revisa tu conexión.', 'error');
        }

        boton.removeAttribute('aria-disabled');
        boton.innerHTML = textoOriginal;
    });
})();
