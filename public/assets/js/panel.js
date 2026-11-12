/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Dashboard del panel
   Toma los datos que el servidor dejó embebidos en la página y los
   entrega al módulo de gráficas. Se redibuja al cambiar de tema para
   que los colores de serie correspondan al fondo activo.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const nodo = document.getElementById('datos-panel');
    if (!nodo || !window.SFESGraficas) return;

    let datos;
    try {
        datos = JSON.parse(nodo.textContent);
    } catch (e) {
        console.error('[panel] No se pudieron leer los datos de las gráficas', e);
        return;
    }

    function pintar() {
        // ── Ventas del periodo: ingresos y utilidad ──
        SFESGraficas.linea('#g-ventas-dia', {
            titulo: 'Ventas del periodo',
            ejeX: 'Día',
            etiquetas: datos.serieDiaria.map((d) => d.etiqueta),
            series: [
                { nombre: 'Ingresos', datos: datos.serieDiaria.map((d) => d.ingresos) },
                { nombre: 'Utilidad bruta', datos: datos.serieDiaria.map((d) => d.utilidad) },
            ],
        });

        // ── Pedidos por estado ──
        // Usa los colores de estado (los mismos de las insignias) en vez de
        // la paleta de series: así "cancelado" es rojo en toda la aplicación
        // y no verde por casualidad del orden.
        SFESGraficas.dona('#g-estados', {
            titulo: 'Pedidos por estado',
            datos: datos.porEstado,
            formato: 'numero',
            subtitulo: 'Pedidos',
            colores: ['pendiente', 'confirmado', 'procesando', 'enviado', 'entregado', 'cancelado']
                .map((e) => SFESGraficas.varCss('--estado-' + e)),
        });

        // ── Doce meses: ingresos contra gastos ──
        SFESGraficas.barras('#g-mensual', {
            titulo: 'Últimos 12 meses',
            ejeX: 'Mes',
            etiquetas: datos.serieMensual.map((d) => d.etiqueta),
            series: [
                { nombre: 'Ingresos', datos: datos.serieMensual.map((d) => d.ingresos) },
                { nombre: 'Utilidad', datos: datos.serieMensual.map((d) => d.utilidad) },
            ],
        });

        // ── Ventas por categoría ──
        SFESGraficas.ranking('#g-categorias', {
            datos: datos.porCategoria,
            color: SFESGraficas.colorSerie(0),
        });

        // ── Piezas más vendidas ──
        SFESGraficas.ranking('#g-top-productos', {
            datos: datos.topProductos,
            formato: 'numero',
            color: SFESGraficas.colorSerie(1),
        });
    }

    pintar();

    // Al cambiar el tema, los colores de serie cambian: hay que repintar.
    document.addEventListener('sfes:tema', pintar);

    // Redibuja al cambiar el tamaño (las etiquetas del eje se recalculan).
    let temporizador;
    window.addEventListener('resize', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(pintar, 260);
    });
})();
