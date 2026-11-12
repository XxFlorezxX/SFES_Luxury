"use strict";
/**
 * personalizar.js
 * Adaptado de vistas/personalizar/personalizar.js de Cerámica Illusions,
 * llevado de "personaliza tu figura" a "personaliza tu joya".
 * WhatsApp usa el mismo número de contacto de ejemplo que contacto/index.php.
 */
document.addEventListener('DOMContentLoaded', () => {
    const WHATSAPP_NUMERO = '573001234567'; // mismo placeholder que en la página de Contacto

    const form         = document.getElementById('form-personalizar');
    const btnGenerar    = document.getElementById('btn-generar');
    const btnLimpiar    = document.getElementById('btn-limpiar');
    const loadingEl      = document.getElementById('ia-loading');
    const resultEl       = document.getElementById('ia-result');
    const demoNotice     = document.getElementById('demo-notice');

    let ultimoResultado = null;

    /* ── Selección de chips (metal / estilo / piedra) ── */
    document.querySelectorAll('.chips-group').forEach((grupo) => {
        const targetInput = document.getElementById(grupo.dataset.target);
        grupo.querySelectorAll('.chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                grupo.querySelectorAll('.chip').forEach((c) => c.classList.remove('selected'));
                chip.classList.add('selected');
                if (targetInput) targetInput.value = chip.dataset.value;
            });
        });
    });

    /* ── Generar concepto (texto) ── */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const tipo = document.getElementById('tipo').value.trim();
        if (!tipo) {
            document.getElementById('tipo').focus();
            return;
        }

        const payload = {
            tipo,
            metal: document.getElementById('metal').value,
            piedra: document.getElementById('piedra').value,
            estilo: document.getElementById('estilo').value,
            descripcion: document.getElementById('descripcion').value.trim(),
        };

        resultEl.style.display = 'none';
        loadingEl.classList.add('show');
        btnGenerar.disabled = true;

        try {
            const res = await fetch(window.BASE_URL + 'personalizar/generar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            loadingEl.classList.remove('show');
            btnGenerar.disabled = false;

            if (!data.ok) {
                if (typeof window.mostrarToast === 'function') window.mostrarToast(data.mensaje || 'No se pudo generar. Intenta de nuevo.');
                return;
            }

            ultimoResultado = { ...payload, ...data };
            renderResultado(ultimoResultado);
        } catch (_) {
            loadingEl.classList.remove('show');
            btnGenerar.disabled = false;
            if (typeof window.mostrarToast === 'function') window.mostrarToast('Error de conexión. Intenta de nuevo.');
        }
    });

    function renderResultado(r) {
        document.getElementById('res-nombre').textContent      = r.nombre || r.tipo;
        document.getElementById('res-precio').textContent      = r.precio_est ? '$' + Number(r.precio_est).toLocaleString('es-CO') + ' COP' : '—';
        document.getElementById('res-tiempo').textContent      = r.tiempo_est || '—';
        document.getElementById('res-descripcion').textContent = r.descripcion || '';

        const sugEl = document.getElementById('res-sugerencias');
        sugEl.innerHTML = (r.sugerencias || []).map((s) => `<span>${s}</span>`).join('') || '<span>—</span>';

        demoNotice.style.display = r.demo ? 'inline-block' : 'none';

        // Reinicia la columna de imagen para un nuevo resultado
        document.getElementById('img-cta').style.display = 'block';
        document.getElementById('img-loading').style.display = 'none';
        document.getElementById('img-resultado').style.display = 'none';
        document.getElementById('img-demo').style.display = 'none';

        // Enlace de WhatsApp con el resumen de la pieza
        const msg = `Hola SFES Luxury! Quiero cotizar esta pieza personalizada:\n` +
                    `${r.nombre || r.tipo} — ${r.metal}${r.piedra && r.piedra !== 'Sin piedra' ? ' con ' + r.piedra : ''}, estilo ${r.estilo}.`;
        document.getElementById('btn-wa-personalizada').href = `https://wa.me/${WHATSAPP_NUMERO}?text=${encodeURIComponent(msg)}`;

        resultEl.style.display = 'block';
        resultEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ── Generar vista previa de imagen ── */
    document.getElementById('btn-generar-imagen').addEventListener('click', generarImagen);
    document.getElementById('btn-regenerar')?.addEventListener('click', generarImagen);

    async function generarImagen() {
        if (!ultimoResultado) return;

        document.getElementById('img-cta').style.display = 'none';
        document.getElementById('img-resultado').style.display = 'none';
        document.getElementById('img-demo').style.display = 'none';
        document.getElementById('img-loading').style.display = 'block';

        try {
            const res = await fetch(window.BASE_URL + 'personalizar/generar-imagen', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo: ultimoResultado.tipo,
                    metal: ultimoResultado.metal,
                    piedra: ultimoResultado.piedra,
                    estilo: ultimoResultado.estilo,
                    descripcion: ultimoResultado.descripcion,
                    nombre_ia: ultimoResultado.nombre,
                }),
            });
            const data = await res.json();
            document.getElementById('img-loading').style.display = 'none';

            if (data.demo) {
                document.getElementById('img-demo-texto').textContent = data.mensaje;
                document.getElementById('img-demo').style.display = 'block';
                return;
            }

            if (!data.ok) {
                if (typeof window.mostrarToast === 'function') window.mostrarToast(data.mensaje || 'No se pudo generar la imagen.');
                document.getElementById('img-cta').style.display = 'block';
                return;
            }

            document.getElementById('img-preview').src = data.imagen_url;
            document.getElementById('img-descargar').href = data.imagen_url;
            document.getElementById('img-resultado').style.display = 'block';

            if (typeof data.restantes === 'number') {
                document.getElementById('img-restantes').textContent = `${data.restantes} imágenes disponibles hoy`;
            }
        } catch (_) {
            document.getElementById('img-loading').style.display = 'none';
            document.getElementById('img-cta').style.display = 'block';
            if (typeof window.mostrarToast === 'function') window.mostrarToast('Error de conexión. Intenta de nuevo.');
        }
    }

    /* ── Limpiar formulario ── */
    btnLimpiar.addEventListener('click', () => {
        setTimeout(() => {
            document.querySelectorAll('.chips-group').forEach((grupo) => {
                grupo.querySelectorAll('.chip').forEach((c, i) => c.classList.toggle('selected', i === 0));
                document.getElementById(grupo.dataset.target).value = grupo.querySelector('.chip').dataset.value;
            });
            resultEl.style.display = 'none';
            ultimoResultado = null;
        }, 0);
    });
});
