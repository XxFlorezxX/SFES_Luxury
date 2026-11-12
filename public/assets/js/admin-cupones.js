/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Panel: cupones
   Abre el modal en modo nuevo o edición y valida el porcentaje antes
   de enviarlo (el servidor lo vuelve a validar de todos modos).
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const modal  = document.getElementById('modal-cupon');
    const form   = document.getElementById('form-cupon');
    const titulo = document.getElementById('cupon-titulo');

    const abrir  = () => modal.classList.add('abierto');
    const cerrar = () => modal.classList.remove('abierto');

    document.getElementById('btn-nuevo-cupon')?.addEventListener('click', () => {
        form.reset();
        document.getElementById('c-id').value = '0';
        document.getElementById('c-activo').checked = true;
        document.getElementById('c-minimo').value = '0';
        titulo.textContent = 'Nuevo cupón';
        abrir();
        document.getElementById('c-codigo').focus();
    });

    document.addEventListener('click', (e) => {
        const boton = e.target.closest('[data-editar-cupon]');
        if (!boton) return;

        let c;
        try { c = JSON.parse(boton.dataset.editarCupon); } catch (err) { return; }

        document.getElementById('c-id').value          = c.id;
        document.getElementById('c-codigo').value       = c.codigo;
        document.getElementById('c-descripcion').value  = c.descripcion || '';
        document.getElementById('c-tipo').value         = c.tipo;
        document.getElementById('c-valor').value        = Math.round(c.valor);
        document.getElementById('c-minimo').value       = Math.round(c.minimo_compra);
        document.getElementById('c-usos').value         = c.usos_maximos ?? '';
        document.getElementById('c-inicia').value       = c.inicia_en || '';
        document.getElementById('c-vence').value        = c.vence_en || '';
        document.getElementById('c-activo').checked     = c.activo === 1;

        titulo.textContent = 'Editar cupón ' + c.codigo;
        abrir();
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-modal]') || e.target === modal) cerrar();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); });

    /* Aviso temprano si el porcentaje no tiene sentido */
    form?.addEventListener('submit', (e) => {
        const tipo  = document.getElementById('c-tipo').value;
        const valor = parseFloat(document.getElementById('c-valor').value) || 0;

        if (tipo === 'porcentaje' && valor > 90) {
            e.preventDefault();
            window.avisoAdmin('Un descuento mayor a 90% no es razonable. Revisa el valor.', 'error');
        }
    });
})();
