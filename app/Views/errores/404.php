<section class="contenedor">
    <div class="error-pagina">
        <strong><?= (int) ($codigoError ?? 404) ?></strong>
        <h1><?= e($tituloError ?? 'Página no encontrada') ?></h1>
        <p><?= e($mensajeError ?? 'La dirección que buscas no existe o fue movida.') ?></p>

        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>" class="btn btn-primario"><i class="fa-solid fa-house"></i> Ir al inicio</a>
            <a href="<?= BASE_URL ?>catalogo" class="btn btn-contorno">Ver el catálogo</a>
        </div>
    </div>
</section>
