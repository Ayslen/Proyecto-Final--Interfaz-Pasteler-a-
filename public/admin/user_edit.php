<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireRole(['admin']);

$id = (int) ($_GET['id'] ?? 0);
$targetUser = User::findById($id);

if (!$targetUser) {
    Flash::set('danger', 'El usuario solicitado no existe.');
    redirect('admin/users.php');
}

$isSelf = Auth::id() === (int) $targetUser['id'];
$errors = [];
$old = [
    'name' => $targetUser['name'],
    'username' => $targetUser['username'],
    'email' => $targetUser['email'],
    'role' => $targetUser['role_name'],
    'is_active' => (int) $targetUser['is_active'],
];
$modules = Module::all();
$permissions = Module::permissionsForUser((int) $targetUser['id']);

if (is_post()) {
    $old['name'] = trim((string) post('name'));
    $old['username'] = trim((string) post('username'));
    $old['email'] = trim((string) post('email'));
    $old['role'] = $isSelf ? 'admin' : trim((string) post('role', 'user'));
    $old['is_active'] = $isSelf ? 1 : (!empty($_POST['is_active']) ? 1 : 0);
    $permissions = Module::permissionsFromPost($_POST['permissions'] ?? []);

    if (!Csrf::validate(post('_csrf_token'))) {
        $errors['general'] = 'La sesión expiró. Recarga la página e intenta otra vez.';
    } elseif ((string) post('password') !== (string) post('password_confirmation')) {
        $errors['password_confirmation'] = 'Las contraseñas no coinciden.';
    } else {
        $result = User::update((int) $targetUser['id'], [
            'name' => $old['name'],
            'username' => $old['username'],
            'email' => $old['email'],
            'password' => post('password'),
            'role' => $old['role'],
            'is_active' => $old['is_active'],
            'protect_self' => $isSelf,
            'permissions' => $permissions,
        ]);

        if ($result['ok']) {
            if ($isSelf) {
                Auth::refreshSessionUser((int) $targetUser['id']);
            }
            Flash::set('success', 'Usuario actualizado correctamente.');
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
        <h1 style="font-size: 34px; margin-top: 14px;">Editar usuario</h1>
        <p class="muted">Modifica la cuenta, el rol y los permisos por módulo. Los cambios quedan guardados en la base de datos.</p>

        <?php if ($isSelf): ?>
            <div class="alert warning">Estás editando tu propia cuenta. Por seguridad no puedes quitarte el rol Admin ni desactivar tu usuario.</div>
        <?php endif; ?>

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
                        <select id="role" name="role" data-role-select required <?= $isSelf ? 'disabled' : '' ?>>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= h($role['name']) ?>" <?= $old['role'] === $role['name'] ? 'selected' : '' ?>>
                                    <?= h($role['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isSelf): ?><input type="hidden" name="role" value="admin"><?php endif; ?>
                        <?php if (isset($errors['role'])): ?><div class="error-text"><?= h($errors['role']) ?></div><?php endif; ?>
                        <p class="muted">Si eliges Admin, el usuario tendrá acceso completo aunque la matriz se bloquee visualmente.</p>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?= $old['is_active'] ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
                            Usuario activo
                        </label>
                        <?php if ($isSelf): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="password">Nueva contraseña</label>
                        <input id="password" name="password" type="password" minlength="8" placeholder="Déjalo vacío para no cambiarla">
                        <?php if (isset($errors['password'])): ?><div class="error-text"><?= h($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirmar nueva contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" data-password-confirm="#password">
                        <?php if (isset($errors['password_confirmation'])): ?><div class="error-text"><?= h($errors['password_confirmation']) ?></div><?php endif; ?>
                    </div>
                </div>
            </section>

            <h2>Permisos por módulos</h2>
            <?php require __DIR__ . '/../partials/permission_matrix.php'; ?>

            <div class="actions" style="margin-top: 18px;">
                <button class="btn" type="submit">Guardar cambios</button>
                <a class="btn secondary" href="<?= h(url('admin/users.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
