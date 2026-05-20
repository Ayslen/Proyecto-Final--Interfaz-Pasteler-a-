<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (Auth::check()) {
    Auth::redirectByRole();
}

$errors = [];
$old = [
    'name' => '',
    'username' => '',
    'email' => '',
];

if (is_post()) {
    $old['name'] = trim((string) post('name'));
    $old['username'] = trim((string) post('username'));
    $old['email'] = trim((string) post('email'));

    if (!Csrf::validate(post('_csrf_token'))) {
        $errors['general'] = 'La sesión expiró. Recarga la página e intenta otra vez.';
    } elseif ((string) post('password') !== (string) post('password_confirmation')) {
        $errors['password_confirmation'] = 'Las contraseñas no coinciden.';
    } else {
        $result = User::create([
            'name' => $old['name'],
            'username' => $old['username'],
            'email' => $old['email'],
            'password' => post('password'),
            'role' => 'user',
        ]);

        if ($result['ok']) {
            Flash::set('success', 'Cuenta creada correctamente. Ya puedes iniciar sesión.');
            redirect('login.php');
        }

        $errors = $result['errors'];
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="form-page">
    <div class="card form-card">
        <h1 style="font-size: 34px;">Registro de usuario</h1>
        <p class="muted">El registro público crea cuentas con rol <strong>User</strong>. Los administradores se crean desde el panel Admin.</p>

        <?php if (isset($errors['general'])): ?>
            <div class="alert danger"><?= h($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label for="name">Nombre completo</label>
                <input id="name" name="name" value="<?= h($old['name']) ?>" required>
                <?php if (isset($errors['name'])): ?><div class="error-text"><?= h($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="username">Usuario</label>
                <input id="username" name="username" value="<?= h($old['username']) ?>" required>
                <?php if (isset($errors['username'])): ?><div class="error-text"><?= h($errors['username']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email" value="<?= h($old['email']) ?>" required>
                <?php if (isset($errors['email'])): ?><div class="error-text"><?= h($errors['email']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" minlength="8" required>
                <?php if (isset($errors['password'])): ?><div class="error-text"><?= h($errors['password']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" data-password-confirm="#password" required>
                <?php if (isset($errors['password_confirmation'])): ?><div class="error-text"><?= h($errors['password_confirmation']) ?></div><?php endif; ?>
            </div>
            <button class="btn" type="submit">Crear cuenta</button>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
