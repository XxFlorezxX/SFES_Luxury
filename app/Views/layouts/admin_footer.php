    </main>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
    /* Barra lateral en móvil + interruptor de tema del panel */
    (function () {
        var lateral = document.getElementById('lateral');
        var velo    = document.getElementById('velo');
        var abrir   = document.getElementById('abrir-lateral');

        function alternar(estado) {
            lateral.classList.toggle('abierto', estado);
            velo.classList.toggle('visible', estado);
        }

        abrir && abrir.addEventListener('click', function () { alternar(true); });
        velo && velo.addEventListener('click', function () { alternar(false); });

        var btnTema = document.getElementById('btn-tema-admin');
        if (btnTema) {
            function pintar() {
                var oscuro = document.documentElement.dataset.tema === 'oscuro';
                btnTema.innerHTML = '<i class="fa-solid ' + (oscuro ? 'fa-sun' : 'fa-moon') + '"></i>';
            }
            btnTema.addEventListener('click', function () {
                var nuevo = document.documentElement.dataset.tema === 'oscuro' ? 'claro' : 'oscuro';
                document.documentElement.dataset.tema = nuevo;
                try { localStorage.setItem('sfes-tema-admin', nuevo); } catch (e) {}
                pintar();
                document.dispatchEvent(new CustomEvent('sfes:tema', { detail: nuevo }));
            });
            pintar();
        }
    })();

    /* Aviso flotante reutilizable en el panel */
    window.avisoAdmin = function (mensaje, tipo) {
        var toast = document.getElementById('toast');
        toast.className = 'toast ' + (tipo === 'error' ? 'error' : '');
        toast.innerHTML = '<i class="fa-solid ' + (tipo === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check') + '"></i><span></span>';
        toast.querySelector('span').textContent = mensaje;
        requestAnimationFrame(function () { toast.classList.add('visible'); });
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { toast.classList.remove('visible'); }, 3600);
    };
</script>

<?php foreach ((array) ($jsPagina ?? []) as $archivo): ?>
    <script src="<?= BASE_URL ?>assets/js/<?= e($archivo) ?>.js" defer></script>
<?php endforeach; ?>

</body>
</html>
