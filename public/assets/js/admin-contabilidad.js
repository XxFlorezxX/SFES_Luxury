/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Panel: contabilidad
   Gráficas del módulo contable: evolución mensual y reparto de gastos.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const nodo = document.getElementById('datos-contabilidad');
    if (!nodo || !window.SFESGraficas) return;

    let datos;
    try {
        datos = JSON.parse(nodo.textContent);
    } catch (e) {
        console.error('[contabilidad] datos ilegibles', e);
        return;
    }

    function pintar() {
        SFESGraficas.linea('#g-pyg-mensual', {
            titulo: 'Resultado mensual',
            ejeX: 'Mes',
            area: false,
            etiquetas: datos.serieMensual.map((m) => m.etiqueta),
            series: [
                { nombre: 'Ingresos', datos: datos.serieMensual.map((m) => m.ingresos) },
                { nombre: 'Utilidad neta', datos: datos.serieMensual.map((m) => m.utilidad) },
                { nombre: 'Gastos', datos: datos.serieMensual.map((m) => m.gastos) },
            ],
        });

        SFESGraficas.dona('#g-gastos', {
            titulo: 'Gastos por categoría',
            datos: datos.gastos,
            subtitulo: 'Gastos',
        });
    }

    pintar();
    document.addEventListener('sfes:tema', pintar);

    let temporizador;
    window.addEventListener('resize', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(pintar, 260);
    });
})();
