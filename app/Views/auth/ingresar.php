<?php
$lateralTitulo = 'Bienvenida de vuelta';
$lateralTexto  = 'Entra a tu cuenta para seguir tus pedidos, revisar tu lista de deseos y comprar más rápido.';
require __DIR__ . '/../shared/auth-cabecera.php';
?>

<h1>Iniciar sesión</h1>
<p>Entra con el correo con el que creaste tu cuenta.</p>

<?php if (!empty($error)): ?>
    <div class="aviso aviso-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>ingresar" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="campo">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" required autocomplete="email"
               value="<?= e($email ?? '') ?>" placeholder="tucorreo@ejemplo.com" autofocus>
    </div>

    <div class="campo">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    </div>

    <button class="btn btn-primario btn-bloque" type="submit">Entrar</button>
</form>

<p class="auth__pie">
    ¿Todavía no tienes cuenta? <a href="<?= BASE_URL ?>registro">Créala aquí</a>
</p>

<p class="auth__pie" style="margin-top:8px">
    <a href="<?= BASE_URL ?>" style="color:var(--tinta-tenue)"><i class="fa-solid fa-arrow-left"></i> Volver a la tienda</a>
</p>

<?php require __DIR__ . '/../shared/auth-pie.php'; ?>
