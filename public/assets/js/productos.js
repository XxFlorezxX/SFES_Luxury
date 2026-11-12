/**
 * productos.js
 * Antes este archivo manejaba TODO el CRUD de productos contra localStorage
 * (agregar, editar, eliminar, listar). Ahora esa lógica vive en el servidor
 * (ver app/Controllers/ProductoController.php y app/Models/Producto.php).
 * Lo único que queda en el navegador es la vista previa de la imagen
 * seleccionada antes de subirla.
 */

document.addEventListener('DOMContentLoaded', () => {
    const inputImagen = document.getElementById('imagenArchivo');
    const vistaPrevia = document.getElementById('vistaPrevia');

    if (!inputImagen || !vistaPrevia) return;

    inputImagen.addEventListener('change', (evento) => {
        const archivo = evento.target.files[0];
        if (!archivo) return;

        const lector = new FileReader();
        lector.onload = (e) => {
            vistaPrevia.src = e.target.result;
        };
        lector.readAsDataURL(archivo);
    });

    // Confirmación antes de eliminar un producto
    document.querySelectorAll('.confirmar-eliminar').forEach((enlace) => {
        enlace.addEventListener('click', (evento) => {
            if (!confirm('¿Desea eliminar este producto?')) {
                evento.preventDefault();
            }
        });
    });
});
