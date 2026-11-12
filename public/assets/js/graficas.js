/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Módulo de gráficas (SVG puro)
   ═══════════════════════════════════════════════════════════════
   Dibuja las gráficas del panel sin ninguna librería externa.

   ¿Por qué propio y no Chart.js? Tres razones concretas:
     · el panel no depende de un CDN para funcionar (si el taller
       trabaja sin internet, las gráficas se ven igual),
     · pesa unos pocos KB en vez de ~200,
     · usa los colores de la marca y responde al tema claro/oscuro.

   Reglas de lectura que aplica el módulo:
     · un solo eje Y por gráfica (nunca dos escalas superpuestas),
     · el color identifica siempre a la misma serie, sin importar
       cuántas se muestren,
     · toda gráfica trae leyenda y una tabla equivalente, para que
       la información nunca dependa únicamente del color,
     · separación de 2px entre rellenos contiguos y anillo de 2px en
       los puntos, para que las marcas no se fundan entre sí.
   ═══════════════════════════════════════════════════════════════ */
window.SFESGraficas = (function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';

    /* ── Utilidades ─────────────────────────────────────────── */

    const crear = (tag, attrs = {}) => {
        const el = document.createElementNS(NS, tag);
        for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
        return el;
    };

    const varCss = (nombre) =>
        getComputedStyle(document.documentElement).getPropertyValue(nombre).trim() || '#888';

    /** Colores de serie por índice — orden fijo, jamás cíclico. */
    const colorSerie = (i) => varCss('--serie-' + (Math.min(i, 5) + 1));

    const pesos = (n) => {
        const abs = Math.abs(n);
        if (abs >= 1e9) return '$' + (n / 1e9).toFixed(1).replace('.', ',') + ' MM';
        if (abs >= 1e6) return '$' + (n / 1e6).toFixed(1).replace('.', ',') + ' M';
        if (abs >= 1e3) return '$' + Math.round(n / 1e3) + ' K';
        return '$' + Math.round(n);
    };

    const pesosCompleto = (n) =>
        '$' + Math.round(n).toLocaleString('es-CO');

    const numero = (n) => Math.round(n).toLocaleString('es-CO');

    /**
     * Escala "bonita": techo redondeado a 1/2/5 × 10ⁿ.
     * Con valores muy pequeños devuelve 4 para que las cuatro marcas del
     * eje sean 1, 2, 3 y 4 en vez de repetir el mismo número redondeado.
     */
    function techo(max) {
        if (max <= 4) return 4;
        const exp = Math.floor(Math.log10(max));
        const base = Math.pow(10, exp);
        const norm = max / base;
        const paso = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10;
        return paso * base;
    }

    /* ── Capa de interacción (tooltip) ──────────────────────── */

    function prepararTip(contenedor) {
        let tip = contenedor.querySelector('.grafica-tip');
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'grafica-tip';
            contenedor.appendChild(tip);
        }
        return tip;
    }

    function mostrarTip(tip, contenedor, x, y, html) {
        tip.innerHTML = html;
        tip.classList.add('visible');
        const ancho = contenedor.clientWidth;
        const margen = 12;
        const mitad = tip.offsetWidth / 2;
        tip.style.left = Math.max(mitad + margen, Math.min(ancho - mitad - margen, x)) + 'px';
        tip.style.top = Math.max(tip.offsetHeight + 6, y - 10) + 'px';
    }

    const ocultarTip = (tip) => tip.classList.remove('visible');

    /* ── Tabla equivalente (accesibilidad) ──────────────────── */

    function tablaAlterna(contenedor, encabezados, filas) {
        const id = 'tabla-' + Math.random().toString(36).slice(2, 8);

        const enlace = document.createElement('a');
        enlace.href = '#';
        enlace.className = 'ver-datos';
        enlace.textContent = 'Ver los datos en tabla';
        enlace.setAttribute('aria-controls', id);
        enlace.setAttribute('aria-expanded', 'false');

        const caja = document.createElement('div');
        caja.className = 'tabla-alterna tabla-scroll';
        caja.id = id;
        caja.innerHTML =
            '<table class="datos"><thead><tr>' +
            encabezados.map((h, i) => `<th${i ? ' class="num"' : ''}>${h}</th>`).join('') +
            '</tr></thead><tbody>' +
            filas.map((f) => '<tr>' + f.map((c, i) => `<td${i ? ' class="num"' : ''}>${c}</td>`).join('') + '</tr>').join('') +
            '</tbody></table>';

        enlace.addEventListener('click', (e) => {
            e.preventDefault();
            const abierto = caja.classList.toggle('visible');
            enlace.setAttribute('aria-expanded', String(abierto));
            enlace.textContent = abierto ? 'Ocultar la tabla' : 'Ver los datos en tabla';
        });

        contenedor.parentElement.appendChild(enlace);
        contenedor.parentElement.appendChild(caja);
    }

    /* ── Leyenda ────────────────────────────────────────────── */

    function leyenda(contenedor, series) {
        if (series.length < 2) return;   // una sola serie: el título ya la nombra

        const caja = document.createElement('div');
        caja.className = 'leyenda';
        caja.innerHTML = series.map((s, i) =>
            `<span class="leyenda__item"><span class="leyenda__punto" style="background:${colorSerie(i)}"></span>${s.nombre}</span>`
        ).join('');
        contenedor.parentElement.insertBefore(caja, contenedor);
    }

    /* ═══════════════════════════════════════════════════════════
       GRÁFICA DE LÍNEA / ÁREA — evolución en el tiempo
       ═══════════════════════════════════════════════════════════ */
    function linea(selector, config) {
        const contenedor = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!contenedor) return;

        contenedor.innerHTML = '';
        contenedor.parentElement.querySelectorAll('.leyenda, .ver-datos, .tabla-alterna')
            .forEach((el) => el.remove());

        const etiquetas = config.etiquetas || [];
        const series    = config.series || [];
        const formato   = config.formato === 'numero' ? numero : pesosCompleto;
        const formatoEje= config.formato === 'numero' ? numero : pesos;

        if (!etiquetas.length) {
            contenedor.innerHTML = '<p class="texto-tenue" style="padding:32px 0;text-align:center">Sin datos en este periodo.</p>';
            return;
        }

        leyenda(contenedor, series);

        const W = 800, H = config.alto || 280;
        const M = { top: 16, right: 16, bottom: 30, left: 58 };
        const ancho = W - M.left - M.right;
        const alto  = H - M.top - M.bottom;

        const maximo = techo(Math.max(1, ...series.flatMap((s) => s.datos)));
        const x = (i) => M.left + (etiquetas.length === 1 ? ancho / 2 : (i / (etiquetas.length - 1)) * ancho);
        const y = (v) => M.top + alto - (v / maximo) * alto;

        const svg = crear('svg', {
            viewBox: `0 0 ${W} ${H}`,
            role: 'img',
            'aria-label': config.titulo || 'Gráfica de evolución',
            preserveAspectRatio: 'none',
        });
        svg.style.height = H + 'px';

        // ── Rejilla horizontal + eje Y (recesivos) ──
        for (let i = 0; i <= 4; i++) {
            const valor = (maximo / 4) * i;
            const py = y(valor);
            svg.appendChild(crear('line', { x1: M.left, y1: py, x2: W - M.right, y2: py, class: 'grid-linea' }));

            const t = crear('text', { x: M.left - 10, y: py + 4, 'text-anchor': 'end' });
            t.textContent = formatoEje(valor);
            svg.appendChild(t);
        }

        // ── Eje X: se muestran solo las etiquetas que caben ──
        const salto = Math.max(1, Math.ceil(etiquetas.length / 8));
        const ultima = etiquetas.length - 1;
        etiquetas.forEach((etq, i) => {
            const esUltima = i === ultima;
            // La última etiqueta solo se dibuja si no queda pegada a la anterior.
            if (esUltima && ultima % salto !== 0 && (ultima % salto) < salto / 2) return;
            if (!esUltima && i % salto !== 0) return;
            const t = crear('text', { x: x(i), y: H - 8, 'text-anchor': 'middle' });
            t.textContent = etq;
            svg.appendChild(t);
        });

        svg.appendChild(crear('line', {
            x1: M.left, y1: M.top + alto, x2: W - M.right, y2: M.top + alto, class: 'eje-linea',
        }));

        // ── Series ──
        series.forEach((serie, si) => {
            const color = colorSerie(si);
            const puntos = serie.datos.map((v, i) => `${x(i)},${y(v)}`).join(' ');

            // Relleno degradado solo para la primera serie (evita el "puré" de áreas apiladas)
            if (si === 0 && series.length <= 2 && config.area !== false) {
                const idGrad = 'grad-' + Math.random().toString(36).slice(2, 8);
                const defs = crear('defs');
                const grad = crear('linearGradient', { id: idGrad, x1: '0', y1: '0', x2: '0', y2: '1' });
                grad.appendChild(crear('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': '.26' }));
                grad.appendChild(crear('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': '0' }));
                defs.appendChild(grad);
                svg.appendChild(defs);

                svg.appendChild(crear('polygon', {
                    points: `${M.left},${M.top + alto} ${puntos} ${x(etiquetas.length - 1)},${M.top + alto}`,
                    fill: `url(#${idGrad})`,
                }));
            }

            svg.appendChild(crear('polyline', {
                points: puntos,
                fill: 'none',
                stroke: color,
                'stroke-width': '2',
                'stroke-linejoin': 'round',
                'stroke-linecap': 'round',
            }));

            // Puntos: solo si son pocos, con anillo de 2px del color de la superficie
            if (etiquetas.length <= 32) {
                serie.datos.forEach((v, i) => {
                    svg.appendChild(crear('circle', {
                        cx: x(i), cy: y(v), r: 4, fill: color, class: 'punto',
                    }));
                });
            }
        });

        // ── Capa de interacción: línea guía + tooltip ──
        const guia = crear('line', {
            x1: 0, y1: M.top, x2: 0, y2: M.top + alto, class: 'cursor-linea',
        });
        svg.appendChild(guia);

        const tip = prepararTip(contenedor);

        etiquetas.forEach((etq, i) => {
            const paso = ancho / Math.max(1, etiquetas.length - 1);
            const zona = crear('rect', {
                x: x(i) - paso / 2, y: M.top,
                width: paso, height: alto,
                class: 'marca-hover',
            });

            const activar = () => {
                guia.setAttribute('x1', x(i));
                guia.setAttribute('x2', x(i));
                guia.style.opacity = '1';

                const filas = series.map((s, si) =>
                    `<div class="fila"><span style="background:${colorSerie(si)}"></span>${s.nombre}<b>${formato(s.datos[i])}</b></div>`
                ).join('');

                const caja = svg.getBoundingClientRect();
                const px = (x(i) / W) * caja.width;
                const py = (y(series[0].datos[i]) / H) * caja.height;

                mostrarTip(tip, contenedor, px, py, `<strong>${etq}</strong>${filas}`);
            };

            zona.addEventListener('mouseenter', activar);
            zona.addEventListener('focus', activar);
            zona.setAttribute('tabindex', '0');
            zona.addEventListener('mouseleave', () => { ocultarTip(tip); guia.style.opacity = '0'; });
            zona.addEventListener('blur', () => { ocultarTip(tip); guia.style.opacity = '0'; });

            svg.appendChild(zona);
        });

        contenedor.appendChild(svg);

        tablaAlterna(
            contenedor,
            [config.ejeX || 'Periodo', ...series.map((s) => s.nombre)],
            etiquetas.map((etq, i) => [etq, ...series.map((s) => formato(s.datos[i]))])
        );
    }

    /* ═══════════════════════════════════════════════════════════
       BARRAS VERTICALES — comparación por periodo
       ═══════════════════════════════════════════════════════════ */
    function barras(selector, config) {
        const contenedor = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!contenedor) return;

        contenedor.innerHTML = '';
        contenedor.parentElement.querySelectorAll('.leyenda, .ver-datos, .tabla-alterna')
            .forEach((el) => el.remove());

        const etiquetas = config.etiquetas || [];
        const series    = config.series || [];
        const formato   = config.formato === 'numero' ? numero : pesosCompleto;
        const formatoEje= config.formato === 'numero' ? numero : pesos;

        if (!etiquetas.length) {
            contenedor.innerHTML = '<p class="texto-tenue" style="padding:32px 0;text-align:center">Sin datos en este periodo.</p>';
            return;
        }

        leyenda(contenedor, series);

        const W = 800, H = config.alto || 280;
        const M = { top: 16, right: 16, bottom: 30, left: 58 };
        const ancho = W - M.left - M.right;
        const alto  = H - M.top - M.bottom;

        const maximo = techo(Math.max(1, ...series.flatMap((s) => s.datos)));
        const y = (v) => M.top + alto - (v / maximo) * alto;

        const anchoGrupo = ancho / etiquetas.length;
        const separacion = 2;                                   // 2px de superficie entre barras
        const anchoBarra = Math.max(4, (anchoGrupo * 0.62) / series.length - separacion);

        const svg = crear('svg', {
            viewBox: `0 0 ${W} ${H}`,
            role: 'img',
            'aria-label': config.titulo || 'Gráfica de barras',
            preserveAspectRatio: 'none',
        });
        svg.style.height = H + 'px';

        for (let i = 0; i <= 4; i++) {
            const valor = (maximo / 4) * i;
            const py = y(valor);
            svg.appendChild(crear('line', { x1: M.left, y1: py, x2: W - M.right, y2: py, class: 'grid-linea' }));
            const t = crear('text', { x: M.left - 10, y: py + 4, 'text-anchor': 'end' });
            t.textContent = formatoEje(valor);
            svg.appendChild(t);
        }

        const tip = prepararTip(contenedor);
        const salto = Math.max(1, Math.ceil(etiquetas.length / 12));

        etiquetas.forEach((etq, i) => {
            const baseX = M.left + i * anchoGrupo + (anchoGrupo - (anchoBarra + separacion) * series.length) / 2;

            series.forEach((serie, si) => {
                const valor = serie.datos[i];
                const py = y(valor);
                const altoBarra = Math.max(0, M.top + alto - py);
                const px = baseX + si * (anchoBarra + separacion);

                // Extremo redondeado 4px, anclado a la línea base
                const barra = crear('rect', {
                    x: px, y: py,
                    width: anchoBarra,
                    height: altoBarra,
                    rx: Math.min(4, anchoBarra / 2),
                    fill: colorSerie(si),
                });
                barra.style.cursor = 'pointer';
                svg.appendChild(barra);

                if (altoBarra > 4) {
                    const tapa = crear('rect', {
                        x: px, y: M.top + alto - Math.min(4, altoBarra),
                        width: anchoBarra, height: Math.min(4, altoBarra),
                        fill: colorSerie(si),
                    });
                    svg.appendChild(tapa);
                }

                const activar = () => {
                    const filas = series.map((s, k) =>
                        `<div class="fila"><span style="background:${colorSerie(k)}"></span>${s.nombre}<b>${formato(s.datos[i])}</b></div>`
                    ).join('');
                    const caja = svg.getBoundingClientRect();
                    mostrarTip(tip, contenedor,
                        ((px + anchoBarra / 2) / W) * caja.width,
                        (py / H) * caja.height,
                        `<strong>${etq}</strong>${filas}`);
                };

                barra.addEventListener('mouseenter', activar);
                barra.addEventListener('mouseleave', () => ocultarTip(tip));
            });

            if (i % salto === 0 || i === etiquetas.length - 1) {
                const t = crear('text', {
                    x: M.left + i * anchoGrupo + anchoGrupo / 2,
                    y: H - 8, 'text-anchor': 'middle',
                });
                t.textContent = etq;
                svg.appendChild(t);
            }
        });

        svg.appendChild(crear('line', {
            x1: M.left, y1: M.top + alto, x2: W - M.right, y2: M.top + alto, class: 'eje-linea',
        }));

        contenedor.appendChild(svg);

        tablaAlterna(
            contenedor,
            [config.ejeX || 'Periodo', ...series.map((s) => s.nombre)],
            etiquetas.map((etq, i) => [etq, ...series.map((s) => formato(s.datos[i]))])
        );
    }

    /* ═══════════════════════════════════════════════════════════
       DONA — reparto sobre un total
       ═══════════════════════════════════════════════════════════ */
    function dona(selector, config) {
        const contenedor = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!contenedor) return;

        contenedor.innerHTML = '';
        contenedor.parentElement.querySelectorAll('.leyenda, .ver-datos, .tabla-alterna')
            .forEach((el) => el.remove());

        const datos = (config.datos || []).filter((d) => Number(d.valor) > 0);
        const total = datos.reduce((s, d) => s + Number(d.valor), 0);
        const formato = config.formato === 'numero' ? numero : pesosCompleto;

        // Cuando los segmentos representan ESTADOS (no categorías sueltas)
        // se pasa una paleta con significado, para que el color coincida
        // con el de las insignias del resto del panel.
        const tono = (i) => (config.colores && config.colores[i]) || colorSerie(i);

        if (!total) {
            contenedor.innerHTML = '<p class="texto-tenue" style="padding:32px 0;text-align:center">Sin datos en este periodo.</p>';
            return;
        }

        const W = 280, H = 280, R = 108, r = 68, cx = W / 2, cy = H / 2;
        const svg = crear('svg', {
            viewBox: `0 0 ${W} ${H}`,
            role: 'img',
            'aria-label': config.titulo || 'Reparto por categoría',
        });
        svg.style.maxWidth = '280px';
        svg.style.margin = '0 auto';

        const tip = prepararTip(contenedor);
        let angulo = -Math.PI / 2;
        const hueco = 0.018;   // separación entre segmentos ≈ 2px de superficie

        datos.forEach((d, i) => {
            const porcion = (Number(d.valor) / total) * Math.PI * 2;
            const a0 = angulo + hueco / 2;
            const a1 = angulo + porcion - hueco / 2;
            angulo += porcion;

            if (a1 <= a0) return;

            const grande = porcion > Math.PI ? 1 : 0;
            const camino = [
                `M ${cx + R * Math.cos(a0)} ${cy + R * Math.sin(a0)}`,
                `A ${R} ${R} 0 ${grande} 1 ${cx + R * Math.cos(a1)} ${cy + R * Math.sin(a1)}`,
                `L ${cx + r * Math.cos(a1)} ${cy + r * Math.sin(a1)}`,
                `A ${r} ${r} 0 ${grande} 0 ${cx + r * Math.cos(a0)} ${cy + r * Math.sin(a0)}`,
                'Z',
            ].join(' ');

            const seg = crear('path', { d: camino, fill: tono(i) });
            seg.style.cursor = 'pointer';
            seg.style.transition = 'opacity 140ms';

            seg.addEventListener('mouseenter', () => {
                seg.style.opacity = '.82';
                const medio = (a0 + a1) / 2;
                const caja = svg.getBoundingClientRect();
                mostrarTip(tip, contenedor,
                    ((cx + (R + 6) * Math.cos(medio)) / W) * caja.width,
                    ((cy + (R + 6) * Math.sin(medio)) / H) * caja.height,
                    `<strong>${d.etiqueta}</strong><div class="fila"><span style="background:${tono(i)}"></span>${formato(d.valor)}<b>${Math.round((d.valor / total) * 100)}%</b></div>`);
            });
            seg.addEventListener('mouseleave', () => { seg.style.opacity = '1'; ocultarTip(tip); });

            svg.appendChild(seg);
        });

        // Cifra central
        const centro = crear('text', {
            x: cx, y: cy - 4, 'text-anchor': 'middle',
            style: 'font-size:22px;font-weight:600;fill:var(--tinta);font-family:var(--fuente-display)',
        });
        centro.textContent = config.formato === 'numero' ? numero(total) : pesos(total);
        svg.appendChild(centro);

        const sub = crear('text', { x: cx, y: cy + 16, 'text-anchor': 'middle', style: 'font-size:11px' });
        sub.textContent = config.subtitulo || 'Total';
        svg.appendChild(sub);

        contenedor.appendChild(svg);

        // Leyenda con valor y porcentaje: la identidad nunca depende solo del color
        const caja = document.createElement('div');
        caja.className = 'lista-media';
        caja.style.marginTop = '16px';
        caja.innerHTML = datos.map((d, i) => `
            <div class="lista-media__item">
                <span class="leyenda__punto" style="background:${tono(i)};width:12px;height:12px"></span>
                <div class="lista-media__txt"><strong>${d.etiqueta}</strong></div>
                <div class="lista-media__valor">${formato(d.valor)}
                    <span class="texto-tenue" style="font-weight:400"> · ${Math.round((d.valor / total) * 100)}%</span>
                </div>
            </div>`).join('');
        contenedor.appendChild(caja);
    }

    /* ═══════════════════════════════════════════════════════════
       BARRAS HORIZONTALES — ranking
       ═══════════════════════════════════════════════════════════ */
    function ranking(selector, config) {
        const contenedor = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!contenedor) return;

        const datos = config.datos || [];
        const formato = config.formato === 'numero' ? numero : pesosCompleto;

        if (!datos.length) {
            contenedor.innerHTML = '<p class="texto-tenue" style="padding:24px 0;text-align:center">Sin datos en este periodo.</p>';
            return;
        }

        const maximo = Math.max(...datos.map((d) => Number(d.valor)), 1);
        const color = config.color || colorSerie(0);

        contenedor.className = 'ranking';
        contenedor.innerHTML = datos.map((d) => `
            <div class="ranking__fila">
                <span class="ranking__nombre" title="${d.etiqueta}">${d.etiqueta}</span>
                <span class="ranking__valor">${formato(d.valor)}</span>
                <span class="ranking__pista">
                    <span class="ranking__barra" style="width:${(Number(d.valor) / maximo) * 100}%;background:${color}"></span>
                </span>
            </div>`).join('');
    }

    return { linea, barras, dona, ranking, pesos, pesosCompleto, numero, colorSerie, varCss };
})();
