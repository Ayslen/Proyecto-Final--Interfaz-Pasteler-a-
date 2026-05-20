<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireRole(['user']);
$user = Auth::user();
$modules = Module::visibleNavigationForUser((int) $user['id'], (string) $user['role']);
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Dashboard del User</span>
        <h1 style="font-size: 38px; margin-top: 14px;">Bienvenido, <?= h($user['name']) ?></h1>
        <p class="muted">
            Este panel demuestra que el rol <strong>User</strong> tiene acceso limitado. Lo que aparece abajo depende de los permisos que el Admin haya marcado para tu cuenta.
        </p>
        <?php if ($modules): ?>
            <div class="grid">
                <?php foreach ($modules as $module): ?>
                    <div class="stat">
                        <strong><?= h($module['icon']) ?> <?= h($module['name']) ?></strong>
                        <span><?= h($module['description']) ?></span><br><br>
                        <a href="<?= h(url($module['route'])) ?>">Entrar</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert warning">Tu cuenta no tiene módulos visibles todavía. Pide al administrador que active permisos para tu usuario.</div>
        <?php endif; ?>
    </div>
</section>
<section class="grid two">
    <div class="card small">
        <h3>Acceso permitido</h3>
        <p>Solo puedes abrir los módulos que el administrador habilitó desde Usuarios y permisos.</p>
    </div>
    <div class="card small">
        <h3>Acceso restringido</h3>
        <p>El usuario User no puede entrar al panel de administración, cambiar apariencia global ni crear usuarios.</p>
        <p><a href="<?= h(url('admin/dashboard.php')) ?>">Probar acceso Admin</a></p>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
