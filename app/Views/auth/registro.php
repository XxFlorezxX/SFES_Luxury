<?php
$lateralTitulo = 'Crea tu cuenta';
$lateralTexto  = 'Guarda tus piezas favoritas, sigue tus pedidos y compra más rápido la próxima vez.';
$lateralPuntos = ['Historial completo de tus compras', 'Tu dirección guardada para el próximo pedido', 'Cupones y novedades antes que nadie'];
require __DIR__ . '/../shared/auth-cabecera.php';
?>

<h1>Crear cuenta</h1>
<p>Solo toma un minuto.</p>

<?php if (!empty($errores)): ?>
    <div class="aviso aviso-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <ul>
            <?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>registro" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="campo">
        <label for="nombre">Nombre completo <span class="req">*</span></label>
        <input type="text" id="nombre" name="nombre" required maxlength="120" autocomplete="name"
               value="<?= e($valores['nombre'] ?? '') ?>" autofocus>
    </div>

    <div class="campo">
        <label for="email">Correo electrónico <span class="req">*</span></label>
        <input type="email" id="email" name="email" required maxlength="150" autocomplete="email"
               value="<?= e($valores['email'] ?? '') ?>">
    </div>

    <div class="campo">
        <label for="telefono">Teléfono / WhatsApp</label>
        <input type="tel" id="telefono" name="telefono" maxlength="30" autocomplete="tel"
               value="<?= e($valores['telefono'] ?? '') ?>" placeholder="300 000 0000">
    </div>

    <div class="campos-2">
        <div class="campo">
            <label for="password">Contraseña <span class="req">*</span></label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            <span class="ayuda">Mínimo 8 caracteres, con letras y números.</span>
        </div>

        <div class="campo">
            <label for="password_confirmar">Confirmar <span class="req">*</span></label>
            <input type="password" id="password_confirmar" name="password_confirmar" required minlength="8" autocomplete="new-password">
        </div>
    </div>

    <button class="btn btn-primario btn-bloque" type="submit">Crear mi cuenta</button>
</form>

<p class="auth__pie">
    ¿Ya tienes cuenta? <a href="<?= BASE_URL ?>ingresar">Inicia sesión</a>
</p>

<p class="auth__pie" style="margin-top:8px">
    <a href="<?= BASE_URL ?>" style="color:var(--tinta-tenue)"><i class="fa-solid fa-arrow-left"></i> Volver a la tienda</a>
</p>

<?php require __DIR__ . '/../shared/auth-pie.php'; ?>
