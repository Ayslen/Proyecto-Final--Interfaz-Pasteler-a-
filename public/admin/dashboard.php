<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireRole(['admin']);
$user = Auth::user();
$users = User::all();
$totalUsers = count($users);
$totalAdmins = count(array_filter($users, fn($u) => $u['role_name'] === 'admin'));
$totalNormalUsers = count(array_filter($users, fn($u) => $u['role_name'] === 'user'));
$modules = Module::all();
$publicModules = array_filter($modules, fn($m) => (int) $m['admin_only'] === 0);
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Dashboard del Admin</span>
        <h1 style="font-size: 38px; margin-top: 14px;">Bienvenido, <?= h($user['name']) ?></h1>
        <p class="muted">
            Este panel demuestra que el rol <strong>Admin</strong> tiene acceso completo: puede crear usuarios, asignar roles, controlar permisos por módulo y cambiar la apariencia permanente del sistema.
        </p>
        <div class="grid">
            <div class="stat"><strong><?= h((string) $totalUsers) ?></strong><span>Usuarios registrados</span></div>
            <div class="stat"><strong><?= h((string) $totalAdmins) ?></strong><span>Administradores</span></div>
            <div class="stat"><strong><?= h((string) $totalNormalUsers) ?></strong><span>Usuarios User</span></div>
        </div>
        <div class="nav-links" style="justify-content:flex-start;">
            <a href="<?= h(url('admin/users.php')) ?>">Administrar usuarios y permisos</a>
            <a class="secondary" href="<?= h(url('admin/user_create.php')) ?>">Crear usuario</a>
            <a class="secondary" href="<?= h(url('admin/appearance.php')) ?>">Cambiar colores</a>
        </div>
    </div>
</section>
<section class="grid two">
    <div class="card small">
        <h3>Sistema de módulos</h3>
        <p>El Admin puede activar permisos de Ver, Registrar, Editar, Eliminar y Administrar por cada usuario.</p>
        <div class="permission-summary">
            <?php foreach ($publicModules as $module): ?>
                <span class="permission-pill"><?= h($module['icon']) ?> <?= h($module['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card small">
        <h3>Apariencia permanente</h3>
        <p>Los colores primario y secundario se guardan en la base de datos. El sistema calcula automáticamente el color del texto para mantener contraste.</p>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
