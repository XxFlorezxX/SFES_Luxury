/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Ficha de producto
   Selector de cantidad respetando el stock disponible.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const input = document.getElementById('cantidad');
    if (!input) return;

    const maximo = parseInt(input.max, 10) || 1;
    const boton  = document.querySelector('[data-agregar]');

    function fijar(valor) {
        const nuevo = Math.max(1, Math.min(valor, maximo));
        input.value = nuevo;
        if (boton) boton.dataset.cantidad = String(nuevo);
    }

    document.getElementById('menos')?.addEventListener('click', () => fijar(parseInt(input.value, 10) - 1));
    document.getElementById('mas')?.addEventListener('click', () => {
        const actual = parseInt(input.value, 10);
        if (actual >= maximo) {
            window.SFESUI?.aviso(`Solo quedan ${maximo} unidad(es) disponibles.`, 'error');
            return;
        }
        fijar(actual + 1);
    });

    input.addEventListener('change', () => fijar(parseInt(input.value, 10) || 1));

    fijar(parseInt(input.value, 10) || 1);
})();
