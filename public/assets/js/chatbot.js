/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Asistente flotante
   Envía el mensaje al servidor, que responde con Claude si hay clave
   configurada, o con respuestas de ejemplo en modo demostración.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const BASE = window.SFES?.base || '/';

    const burbuja  = document.getElementById('chat-burbuja');
    const panel    = document.getElementById('chat-panel');
    const cerrar   = document.getElementById('chat-cerrar');
    const form     = document.getElementById('chat-form');
    const entrada  = document.getElementById('chat-entrada');
    const mensajes = document.getElementById('chat-mensajes');

    if (!burbuja || !panel || !form) return;

    const historial = [];

    const escapar = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };

    function alternar(abrir) {
        panel.classList.toggle('abierto', abrir);
        burbuja.setAttribute('aria-expanded', String(abrir));
        burbuja.innerHTML = abrir
            ? '<i class="fa-solid fa-xmark"></i>'
            : '<i class="fa-solid fa-comment-dots"></i>';
        if (abrir) entrada.focus();
    }

    burbuja.addEventListener('click', () => alternar(!panel.classList.contains('abierto')));
    cerrar.addEventListener('click', () => alternar(false));

    function agregar(texto, quien) {
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (quien === 'yo' ? 'yo' : 'bot');
        div.innerHTML = escapar(texto).replace(/\n/g, '<br>');
        mensajes.appendChild(div);
        mensajes.scrollTop = mensajes.scrollHeight;
        return div;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const texto = entrada.value.trim();
        if (!texto) return;

        agregar(texto, 'yo');
        historial.push({ rol: 'usuario', texto });
        entrada.value = '';
        entrada.disabled = true;

        const cargando = agregar('…', 'bot');

        try {
            const r = await fetch(`${BASE}chatbot`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ mensaje: texto, historial: historial.slice(-8) }),
            });
            const datos = await r.json();

            const respuesta = datos.respuesta || datos.mensaje || 'No pude responder en este momento.';
            cargando.innerHTML = escapar(respuesta).replace(/\n/g, '<br>');
            historial.push({ rol: 'asistente', texto: respuesta });
        } catch (err) {
            cargando.innerHTML = 'No pude conectarme. Escríbenos por WhatsApp y te ayudamos.';
        }

        entrada.disabled = false;
        entrada.focus();
        mensajes.scrollTop = mensajes.scrollHeight;
    });
})();
