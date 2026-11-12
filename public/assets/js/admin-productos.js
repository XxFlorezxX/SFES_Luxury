/* ═══════════════════════════════════════════════════════════════
   SFES Luxury — Panel: productos
   Abre el modal en modo "nuevo" o "editar" (los datos de la pieza se
   piden al servidor), calcula el margen en vivo y confirma el borrado.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const BASE = window.SFES?.base || '/';

    const modalProducto = document.getElementById('modal-producto');
    const modalEliminar = document.getElementById('modal-eliminar');
    const formulario    = document.getElementById('form-producto');
    const titulo        = document.getElementById('modal-titulo');

    const abrir  = (modal) => modal.classList.add('abierto');
    const cerrar = (modal) => modal.classList.remove('abierto');

    /* ── Cerrar modales ── */
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-modal]')) {
            cerrar(modalProducto);
            cerrar(modalEliminar);
        }
        if (e.target === modalProducto) cerrar(modalProducto);
        if (e.target === modalEliminar) cerrar(modalEliminar);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { cerrar(modalProducto); cerrar(modalEliminar); }
    });

    /* ── Nuevo producto ── */
    function nuevoProducto() {
        formulario.reset();
        document.getElementById('f-id').value = '0';
        document.getElementById('f-material').value = 'Plata 925';
        document.getElementById('f-stock-minimo').value = '3';
        document.getElementById('f-activo').checked = true;
        document.getElementById('f-destacado').checked = false;
        document.getElementById('f-imagen-actual').style.display = 'none';
        titulo.textContent = 'Nuevo producto';
        calcularMargen();
        abrir(modalProducto);
        document.getElementById('f-nombre').focus();
    }

    document.getElementById('btn-nuevo')?.addEventListener('click', nuevoProducto);
    document.getElementById('btn-nuevo-vacio')?.addEventListener('click', nuevoProducto);

    /* ── Editar ── */
    document.addEventListener('click', async (e) => {
        const boton = e.target.closest('[data-editar]');
        if (!boton) return;

        try {
            const r = await fetch(`${BASE}admin/productos/api/${boton.dataset.editar}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const datos = await r.json();

            if (!datos.ok) {
                window.avisoAdmin(datos.mensaje || 'No se pudo cargar el producto.', 'error');
                return;
            }

            const p = datos.producto;
            document.getElementById('f-id').value           = p.id;
            document.getElementById('f-nombre').value        = p.nombre;
            document.getElementById('f-sku').value           = p.sku || '';
            document.getElementById('f-descripcion').value   = p.descripcion;
            document.getElementById('f-categoria').value     = p.categoria;
            document.getElementById('f-material').value      = p.material || '';
            document.getElementById('f-costo').value         = Math.round(p.costo);
            document.getElementById('f-precio').value        = Math.round(p.precio);
            document.getElementById('f-precio-antes').value  = p.precio_antes ? Math.round(p.precio_antes) : '';
            document.getElementById('f-peso').value          = p.peso_gramos || '';
            document.getElementById('f-stock').value         = p.stock;
            document.getElementById('f-stock-minimo').value  = p.stock_minimo;
            document.getElementById('f-activo').checked      = Number(p.activo) === 1;
            document.getElementById('f-destacado').checked   = Number(p.destacado) === 1;

            const vista = document.getElementById('f-imagen-actual');
            vista.src = p.imagen_url;
            vista.style.display = 'block';

            titulo.textContent = 'Editar: ' + p.nombre;
            calcularMargen();
            abrir(modalProducto);
        } catch (err) {
            window.avisoAdmin('No pudimos conectar con el servidor.', 'error');
        }
    });

    /* ── Eliminar ── */
    document.addEventListener('click', (e) => {
        const boton = e.target.closest('[data-eliminar]');
        if (!boton) return;

        document.getElementById('eliminar-id').value = boton.dataset.eliminar;
        document.getElementById('eliminar-nombre').textContent = boton.dataset.nombre;
        abrir(modalEliminar);
    });

    /* ── Margen en vivo ── */
    function calcularMargen() {
        const costo  = parseFloat(document.getElementById('f-costo').value) || 0;
        const precio = parseFloat(document.getElementById('f-precio').value) || 0;
        const ayuda  = document.getElementById('ayuda-margen');
        if (!ayuda) return;

        if (precio <= 0) {
            ayuda.textContent = 'Margen: —';
            ayuda.style.color = '';
            return;
        }

        const margen   = ((precio - costo) / precio) * 100;
        const utilidad = precio - costo;

        ayuda.textContent = `Margen: ${margen.toFixed(1)}% · utilidad de $${Math.round(utilidad).toLocaleString('es-CO')} por unidad`;
        ayuda.style.color = margen < 0 ? 'var(--error)' : (margen < 25 ? 'var(--aviso)' : 'var(--exito)');
    }

    document.getElementById('f-costo')?.addEventListener('input', calcularMargen);
    document.getElementById('f-precio')?.addEventListener('input', calcularMargen);
})();
