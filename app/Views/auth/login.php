<?php
/**
 * Acceso al panel de administración.
 * Separado del acceso de clientes: son dos sesiones distintas.
 */
$lateralTitulo = 'Panel de administración';
$lateralTexto  = 'Gestiona el catálogo, el inventario, los pedidos y la contabilidad de la tienda.';
$lateralPuntos = ['Ventas, inventario y utilidad en un solo lugar', 'Gestión de pedidos y clientes', 'Estado de resultados actualizado'];
require __DIR__ . '/../shared/auth-cabecera.php';
?>

<span class="eyebrow"><i class="fa-solid fa-shield-halved"></i> Acceso restringido</span>
<h1>Administración</h1>
<p>Ingresa con tus credenciales de administrador.</p>

<?php if (!empty($error)): ?>
    <div class="aviso aviso-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>login" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="campo">
        <label for="usuario">Correo del administrador</label>
        <input type="email" id="usuario" name="usuario" required autocomplete="username"
               placeholder="admin@sfesluxury.com" autofocus>
    </div>

    <div class="campo">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" required autocomplete="current-password" placeholder="••••••••">
    </div>

    <button class="btn btn-primario btn-bloque" type="submit">
        <i class="fa-solid fa-lock"></i> Entrar al panel
    </button>
</form>

<p class="auth__pie" style="margin-top:24px">
    <a href="<?= BASE_URL ?>" style="color:var(--tinta-tenue)"><i class="fa-solid fa-arrow-left"></i> Volver a la tienda</a>
</p>

<?php require __DIR__ . '/../shared/auth-pie.php'; ?>
