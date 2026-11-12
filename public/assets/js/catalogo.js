/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Catálogo
   Panel de filtros en móvil. El filtrado real lo hace el servidor
   (los filtros viajan en la URL), así la paginación es correcta y los
   resultados se pueden compartir por enlace.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const panel  = document.getElementById('filtros');
    const abrir  = document.getElementById('abrir-filtros');
    const cerrar = document.getElementById('cerrar-filtros');
    if (!panel) return;

    let velo = document.getElementById('velo-filtros');
    if (!velo) {
        velo = document.createElement('div');
        velo.id = 'velo-filtros';
        velo.className = 'velo';
        document.body.appendChild(velo);
    }

    function alternar(estado) {
        panel.classList.toggle('abierto', estado);
        velo.classList.toggle('visible', estado);
        document.body.style.overflow = estado ? 'hidden' : '';
    }

    abrir?.addEventListener('click', () => alternar(true));
    cerrar?.addEventListener('click', () => alternar(false));
    velo.addEventListener('click', () => alternar(false));

    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') alternar(false); });

    // Al elegir un filtro en móvil, se cierra el panel y navega.
    panel.addEventListener('click', (e) => {
        if (e.target.closest('a.filtros__opcion')) alternar(false);
    });
})();
