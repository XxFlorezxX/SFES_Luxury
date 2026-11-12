/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Cuenta del cliente
   Guarda la dirección sin recargar la página. Si el JavaScript falla,
   el mismo formulario se envía por POST normal y funciona igual.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const { aviso, enviar } = window.SFESUI || {};
    if (!enviar) return;

    const formulario = document.getElementById('form-direccion');
    if (!formulario) return;

    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();

        const boton = formulario.querySelector('button[type="submit"]');
        const textoOriginal = boton.innerHTML;
        boton.setAttribute('aria-disabled', 'true');
        boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';

        try {
            const datos = await enviar('mi-cuenta/direccion', {
                nombres:          document.getElementById('dir-nombres').value.trim(),
                telefono:         document.getElementById('dir-telefono').value.trim(),
                direccion:        document.getElementById('dir-direccion').value.trim(),
                ciudad:           document.getElementById('dir-ciudad').value.trim(),
                especificaciones: document.getElementById('dir-especificaciones').value.trim(),
            });

            aviso(datos.mensaje, datos.ok ? 'exito' : 'error');
        } catch (err) {
            // Si el envío por fetch falla, se deja que el formulario se envíe normal.
            formulario.submit();
            return;
        }

        boton.removeAttribute('aria-disabled');
        boton.innerHTML = textoOriginal;
    });
})();
