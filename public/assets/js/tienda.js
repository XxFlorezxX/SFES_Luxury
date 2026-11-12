/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Núcleo de la tienda
   ═══════════════════════════════════════════════════════════════
   Se carga en todas las páginas públicas. Contiene lo compartido:
   tema claro/oscuro, menú móvil, buscador con sugerencias, avisos
   flotantes, favoritos y agregar al carrito.

   Todo es JavaScript sin librerías: no hay jQuery ni frameworks que
   descargar, y la página funciona igual (con recarga) si el
   JavaScript falla.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const BASE = window.SFES?.base || '/';
    const CSRF = window.SFES?.csrf || '';

    /* ── Utilidades ─────────────────────────────────────────── */

    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    /** Aviso flotante. Reemplaza los alert() del prototipo original. */
    function aviso(mensaje, tipo = 'exito') {
        let toast = $('#toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast';
            document.body.appendChild(toast);
        }
        const icono = tipo === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
        toast.className = 'toast ' + (tipo === 'error' ? 'error' : '');
        toast.innerHTML = `<i class="fa-solid ${icono}"></i><span></span>`;
        toast.querySelector('span').textContent = mensaje;

        requestAnimationFrame(() => toast.classList.add('visible'));
        clearTimeout(toast._t);
        toast._t = setTimeout(() => toast.classList.remove('visible'), 3600);
    }

    /** POST con JSON + token CSRF. */
    async function enviar(ruta, datos = {}) {
        const respuesta = await fetch(BASE + ruta, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ ...datos, csrf_token: CSRF }),
        });
        return respuesta.json();
    }

    const debounce = (fn, ms = 260) => {
        let id;
        return (...args) => { clearTimeout(id); id = setTimeout(() => fn(...args), ms); };
    };

    window.SFESUI = { aviso, enviar, $, $$, debounce, BASE, CSRF };

    /* ── Tema claro / oscuro ────────────────────────────────── */

    const btnTema = $('#btn-tema');
    if (btnTema) {
        const pintarIcono = () => {
            const oscuro = document.documentElement.dataset.tema !== 'claro';
            btnTema.innerHTML = `<i class="fa-solid ${oscuro ? 'fa-sun' : 'fa-moon'}"></i>`;
            btnTema.setAttribute('aria-label', oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
        };

        btnTema.addEventListener('click', () => {
            const nuevo = document.documentElement.dataset.tema === 'claro' ? 'oscuro' : 'claro';
            document.documentElement.dataset.tema = nuevo;
            try { localStorage.setItem('sfes-tema', nuevo); } catch (e) { /* modo privado */ }
            pintarIcono();
        });

        pintarIcono();
    }

    /* ── Menú móvil ─────────────────────────────────────────── */

    const menu = $('#menu-movil');
    const abrirMenu = $('#abrir-menu');
    const cerrarMenu = $('#cerrar-menu');

    if (menu && abrirMenu) {
        const alternar = (abrir) => {
            menu.classList.toggle('abierto', abrir);
            document.body.style.overflow = abrir ? 'hidden' : '';
        };
        abrirMenu.addEventListener('click', () => alternar(true));
        cerrarMenu?.addEventListener('click', () => alternar(false));
        menu.addEventListener('click', (e) => { if (e.target.tagName === 'A') alternar(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') alternar(false); });
    }

    /* ── Buscador con sugerencias ───────────────────────────── */

    const campoBusqueda = $('#buscador-input');
    const listaSugerencias = $('#sugerencias');

    if (campoBusqueda && listaSugerencias) {
        let indiceActivo = -1;

        const cerrar = () => {
            listaSugerencias.classList.remove('visible');
            listaSugerencias.innerHTML = '';
            indiceActivo = -1;
        };

        const buscar = debounce(async (termino) => {
            if (termino.trim().length < 2) { cerrar(); return; }

            try {
                const r = await fetch(`${BASE}buscar/sugerencias?q=${encodeURIComponent(termino)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const datos = await r.json();

                if (!datos.sugerencias?.length) {
                    listaSugerencias.innerHTML =
                        '<div class="sugerencia"><div class="sugerencia__txt"><strong>Sin resultados</strong>' +
                        '<span>Prueba con otra palabra</span></div></div>';
                    listaSugerencias.classList.add('visible');
                    return;
                }

                listaSugerencias.innerHTML = datos.sugerencias.map((s) => `
                    <a class="sugerencia" href="${s.url}">
                        <img src="${s.imagen}" alt="" loading="lazy">
                        <div class="sugerencia__txt">
                            <strong>${escapar(s.nombre)}</strong>
                            <span>${escapar(s.categoria)}</span>
                        </div>
                        <b>${escapar(s.precio)}</b>
                    </a>`).join('');
                listaSugerencias.classList.add('visible');
                indiceActivo = -1;
            } catch (e) {
                cerrar();
            }
        });

        campoBusqueda.addEventListener('input', (e) => buscar(e.target.value));
        campoBusqueda.addEventListener('focus', (e) => { if (e.target.value.length >= 2) buscar(e.target.value); });

        campoBusqueda.addEventListener('keydown', (e) => {
            const items = $$('.sugerencia', listaSugerencias);
            if (!items.length) return;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                indiceActivo += e.key === 'ArrowDown' ? 1 : -1;
                if (indiceActivo < 0) indiceActivo = items.length - 1;
                if (indiceActivo >= items.length) indiceActivo = 0;
                items.forEach((el, i) => el.classList.toggle('resaltada', i === indiceActivo));
            } else if (e.key === 'Enter' && indiceActivo >= 0) {
                e.preventDefault();
                items[indiceActivo].click();
            } else if (e.key === 'Escape') {
                cerrar();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.buscador')) cerrar();
        });
    }

    function escapar(texto) {
        const d = document.createElement('div');
        d.textContent = texto ?? '';
        return d.innerHTML;
    }
    window.SFESUI.escapar = escapar;

    /* ── Contador del carrito ───────────────────────────────── */

    function pintarContador(total) {
        const contador = $('#contador-carrito');
        if (!contador) return;
        contador.textContent = total > 0 ? total : '';
        contador.dataset.cero = total > 0 ? '0' : '1';
    }
    window.SFESUI.pintarContador = pintarContador;

    /* ── Agregar al carrito ─────────────────────────────────── */

    document.addEventListener('click', async (e) => {
        const boton = e.target.closest('[data-agregar]');
        if (!boton) return;

        e.preventDefault();
        const id = boton.dataset.agregar;
        const cantidad = parseInt(boton.dataset.cantidad || $('#cantidad')?.value || '1', 10);

        const textoOriginal = boton.innerHTML;
        boton.setAttribute('aria-disabled', 'true');
        boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        try {
            const r = await fetch(`${BASE}carrito/agregar/${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'cantidad=' + cantidad,
            });
            const datos = await r.json();

            aviso(datos.mensaje, datos.ok ? 'exito' : 'error');
            if (datos.ok) pintarContador(datos.totalCarrito);
        } catch (err) {
            aviso('No pudimos conectar con el servidor.', 'error');
        } finally {
            boton.removeAttribute('aria-disabled');
            boton.innerHTML = textoOriginal;
        }
    });

    /* ── Lista de deseos ────────────────────────────────────── */

    document.addEventListener('click', async (e) => {
        const boton = e.target.closest('[data-favorito]');
        if (!boton) return;

        e.preventDefault();

        if (!window.SFES?.sesion) {
            aviso('Inicia sesión para guardar piezas en tu lista.', 'error');
            setTimeout(() => { window.location.href = BASE + 'ingresar'; }, 1200);
            return;
        }

        try {
            const datos = await enviar('favoritos/alternar', { producto_id: boton.dataset.favorito });

            if (datos.requiere_login) {
                window.location.href = BASE + 'ingresar';
                return;
            }

            if (datos.ok) {
                boton.classList.toggle('activo', datos.favorito);
                boton.querySelector('i').className = datos.favorito ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
                const contador = $('#contador-favoritos');
                if (contador) {
                    contador.textContent = datos.total > 0 ? datos.total : '';
                    contador.dataset.cero = datos.total > 0 ? '0' : '1';
                }
                aviso(datos.mensaje);
            } else {
                aviso(datos.mensaje || 'No pudimos guardar tu selección.', 'error');
            }
        } catch (err) {
            aviso('No pudimos conectar con el servidor.', 'error');
        }
    });

    /* ── Mensaje flash del servidor ─────────────────────────── */

    const flash = window.SFES?.flash;
    if (flash?.mensaje) {
        setTimeout(() => aviso(flash.mensaje, flash.tipo === 'error' ? 'error' : 'exito'), 220);
    }
})();
