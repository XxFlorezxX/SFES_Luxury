<?php
/**
 * Layout: pie de página público + carga de scripts.
 * Los scripts se cargan al final y con `defer` para no bloquear el
 * pintado de la página.
 */
?>
</main>

<footer class="pie">
    <div class="contenedor">
        <div class="pie__grid">
            <div class="pie__marca">
                <span class="logo"><strong>SFES</strong><span>Luxury</span></span>
                <p>Joyería en oro y plata 925 diseñada y ensamblada en Colombia. Piezas pensadas para durar, no para una temporada.</p>
                <div class="pie__redes">
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                    <a href="https://wa.me/<?= e(TIENDA_WHATSAPP) ?>" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>

            <div>
                <h4>Tienda</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>catalogo">Todo el catálogo</a></li>
                    <li><a href="<?= BASE_URL ?>catalogo?categoria=Anillos">Anillos</a></li>
                    <li><a href="<?= BASE_URL ?>catalogo?categoria=Aretes">Aretes</a></li>
                    <li><a href="<?= BASE_URL ?>catalogo?categoria=Collares">Collares</a></li>
                    <li><a href="<?= BASE_URL ?>catalogo?disponibilidad=oferta">Ofertas</a></li>
                </ul>
            </div>

            <div>
                <h4>Tu cuenta</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>mi-cuenta">Mi cuenta</a></li>
                    <li><a href="<?= BASE_URL ?>mis-pedidos">Mis pedidos</a></li>
                    <li><a href="<?= BASE_URL ?>favoritos">Lista de deseos</a></li>
                    <li><a href="<?= BASE_URL ?>carrito">Mi bolsa</a></li>
                </ul>
            </div>

            <div>
                <h4>Ayuda</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>contacto">Contacto</a></li>
                    <li><a href="<?= BASE_URL ?>sobre-nosotros">Sobre nosotros</a></li>
                    <li><a href="<?= BASE_URL ?>personalizar">Piezas a la medida</a></li>
                    <li><a href="<?= BASE_URL ?>login">Acceso administrador</a></li>
                </ul>
            </div>
        </div>

        <div class="pie__legal">
            <p>&copy; <?= date('Y') ?> <?= e(TIENDA_NOMBRE) ?>. Todos los derechos reservados.</p>
            <p>Envíos a todo Colombia · Pago contraentrega y transferencia</p>
        </div>
    </div>
</footer>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<?php require __DIR__ . '/../shared/chatbot.php'; ?>

<script src="<?= BASE_URL ?>assets/js/tienda.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/chatbot.js" defer></script>
<?php foreach ((array) ($jsPagina ?? []) as $archivo): ?>
    <?php if ($archivo !== 'tienda'): ?>
        <script src="<?= BASE_URL ?>assets/js/<?= e($archivo) ?>.js" defer></script>
    <?php endif; ?>
<?php endforeach; ?>

</body>
</html>
