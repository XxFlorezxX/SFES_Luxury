<section class="seccion-sm">
    <div class="contenedor">
        <div class="hero__grid" style="padding-block:24px">
            <div>
                <span class="eyebrow">Nuestra historia</span>
                <h1 style="font-size:var(--txt-2xl);margin-bottom:16px">Joyería que se hereda,<br>no que se reemplaza</h1>
                <p class="texto-suave">
                    SFES Luxury nació de una idea simple: que una joya bonita no tenga que costar una fortuna
                    ni durar una temporada. Trabajamos con plata 925 y baño de oro de 18 quilates, con
                    proveedores verificados y un taller propio donde cada pieza se revisa antes de salir.
                </p>
            </div>

            <div class="hero__figura hero__figura--logo">
                <img src="<?= BASE_URL ?>assets/img/sitio/logo-sfes.png" alt="<?= e(TIENDA_NOMBRE) ?>" width="500" height="500">
            </div>
        </div>
    </div>
</section>

<section class="seccion-sm">
    <div class="contenedor">
        <div class="metricas">
            <div class="metrica">
                <span>Piezas en catálogo</span>
                <strong><?= (int) $cifras['piezas'] ?></strong>
            </div>
            <div class="metrica">
                <span>Líneas de producto</span>
                <strong><?= (int) $cifras['categorias'] ?></strong>
            </div>
            <div class="metrica">
                <span>Clientas registradas</span>
                <strong><?= (int) $cifras['clientes'] ?></strong>
            </div>
            <div class="metrica">
                <span>Calificación promedio</span>
                <strong><?= $cifras['promedio'] > 0 ? number_format($cifras['promedio'], 1, ',', '.') . ' / 5' : '—' ?></strong>
            </div>
        </div>
    </div>
</section>

<section class="seccion-sm">
    <div class="contenedor">
        <div class="editorial">
            <div>
                <span class="eyebrow">Cómo trabajamos</span>
                <h2>Proveedores verificados, variedad real</h2>
                <p>
                    Construimos relaciones estables con proveedores confiables, lo que nos permite garantizar
                    disponibilidad y ampliar el catálogo sin sacrificar calidad. Esa base es la que sostiene
                    la variedad que ves hoy en la tienda.
                </p>
                <ul class="editorial__lista">
                    <li><i class="fa-solid fa-check"></i> Control de calidad pieza por pieza antes del envío</li>
                    <li><i class="fa-solid fa-check"></i> Reposición constante de las referencias más pedidas</li>
                    <li><i class="fa-solid fa-check"></i> Garantía de 6 meses sobre el baño y las monturas</li>
                    <li><i class="fa-solid fa-check"></i> Cambios dentro de los primeros 8 días</li>
                </ul>
                <a href="<?= BASE_URL ?>catalogo" class="btn btn-primario">Ver el catálogo</a>
            </div>

            <div>
                <div class="grid-categorias" style="grid-template-columns:1fr 1fr">
                    <div class="categoria-card">
                        <i class="fa-solid fa-gem"></i>
                        <strong>Plata 925</strong>
                        <span>Ley certificada</span>
                    </div>
                    <div class="categoria-card">
                        <i class="fa-solid fa-award"></i>
                        <strong>Oro 18k</strong>
                        <span>Baño de larga duración</span>
                    </div>
                    <div class="categoria-card">
                        <i class="fa-solid fa-hand-sparkles"></i>
                        <strong>Hecho a mano</strong>
                        <span>Ensamble artesanal</span>
                    </div>
                    <div class="categoria-card">
                        <i class="fa-solid fa-box-open"></i>
                        <strong>Listo para regalar</strong>
                        <span>Estuche incluido</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="seccion-sm">
    <div class="contenedor texto-centro">
        <span class="eyebrow">¿Buscas algo único?</span>
        <h2 style="font-size:var(--txt-2xl);margin-bottom:16px">Diseñamos la pieza que tienes en mente</h2>
        <p class="texto-suave" style="max-width:56ch;margin:0 auto 32px">
            Cuéntanos qué imaginas y nuestro taller lo cotiza. Grabados, tallas especiales y combinaciones
            de metal que no encuentras en el catálogo.
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>personalizar" class="btn btn-primario">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Diseñar mi pieza
            </a>
            <a href="<?= BASE_URL ?>contacto" class="btn btn-contorno">Escribirnos</a>
        </div>
    </div>
</section>
