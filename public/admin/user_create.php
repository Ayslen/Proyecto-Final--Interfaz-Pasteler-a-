<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireRole(['admin']);

$errors = [];
$old = [
    'name' => '',
    'username' => '',
    'email' => '',
    'role' => 'user',
];
$modules = Module::all();
$permissions = Module::defaultPermissionsForRole('user');

if (is_post()) {
    $old['name'] = trim((string) post('name'));
    $old['username'] = trim((string) post('username'));
    $old['email'] = trim((string) post('email'));
    $old['role'] = trim((string) post('role', 'user'));
    $permissions = Module::permissionsFromPost($_POST['permissions'] ?? []);

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
            'role' => $old['role'],
            'permissions' => $permissions,
        ]);

        if ($result['ok']) {
            Flash::set('success', 'Usuario creado correctamente con su rol y permisos.');
            redirect('admin/users.php');
        }

        $errors = $result['errors'];
    }
}

$roles = User::roles();
require_once __DIR__ . '/../partials/header.php';
?>
<section class="form-page">
    <div class="card wide-card">
        <span class="badge">Admin</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Crear usuario</h1>
        <p class="muted">Desde aquí el administrador puede crear cuentas, asignar rol y marcar qué módulos puede ver o modificar cada usuario.</p>

        <?php if (isset($errors['general'])): ?>
            <div class="alert danger"><?= h($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <?= Csrf::field() ?>
            <section class="grid two" style="margin-top: 0;">
                <div>
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
                </div>
                <div>
                    <div class="form-group">
                        <label for="role">Rol</label>
                        <select id="role" name="role" data-role-select required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= h($role['name']) ?>" <?= $old['role'] === $role['name'] ? 'selected' : '' ?>>
                                    <?= h($role['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['role'])): ?><div class="error-text"><?= h($errors['role']) ?></div><?php endif; ?>
                        <p class="muted">Si eliges Admin, el sistema le dará acceso completo automáticamente.</p>
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
                </div>
            </section>

            <h2>Permisos por módulos</h2>
            <?php require __DIR__ . '/../partials/permission_matrix.php'; ?>

            <div class="actions" style="margin-top: 18px;">
                <button class="btn" type="submit">Guardar usuario</button>
                <a class="btn secondary" href="<?= h(url('admin/users.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
