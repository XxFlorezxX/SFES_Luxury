/**
 * toast.js
 * Aviso flotante reutilizable en todo el sitio. Sustituye al antiguo
 * alert()/console.log disperso, con la misma idea que showToast() de
 * Cerámica Illusions pero como utilidad compartida vía footer.php.
 */
(function () {
    let temporizador;

    window.mostrarToast = function (mensaje, duracionMs) {
        const el = document.getElementById('toast-global');
        if (!el) return;

        el.textContent = mensaje;
        el.classList.add('show');

        clearTimeout(temporizador);
        temporizador = setTimeout(() => el.classList.remove('show'), duracionMs || 3000);
    };
})();
