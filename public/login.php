<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (Auth::check()) {
    Auth::redirectByRole();
}

$errors = [];
$identifier = '';

if (is_post()) {
    $identifier = trim((string) post('identifier'));
    $password = (string) post('password');

    if (!Csrf::validate(post('_csrf_token'))) {
        $errors['general'] = 'La sesión expiró. Recarga la página e intenta otra vez.';
    } elseif ($identifier === '' || $password === '') {
        $errors['general'] = 'Escribe tu usuario/correo y contraseña.';
    } elseif (Auth::attempt($identifier, $password)) {
        Flash::set('success', 'Inicio de sesión correcto.');
        Auth::redirectByRole();
    } else {
        $errors['general'] = 'Datos incorrectos o usuario inactivo.';
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="form-page">
    <div class="card form-card">
        <h1 style="font-size: 34px;">Iniciar sesión</h1>
        <p class="muted">Ingresa con tu usuario o correo para entrar al sistema.</p>

        <?php if (isset($errors['general'])): ?>
            <div class="alert danger"><?= h($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label for="identifier">Usuario o correo</label>
                <input id="identifier" name="identifier" value="<?= h($identifier) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn" type="submit">Entrar</button>
        </form>

        <p class="muted" style="margin-top:18px;">
            Usuario de prueba Admin: <strong>admin@pasteleria.local</strong> / <strong>Admin123*</strong><br>
            Usuario de prueba User: <strong>user@pasteleria.local</strong> / <strong>User123*</strong>
        </p>
    </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
