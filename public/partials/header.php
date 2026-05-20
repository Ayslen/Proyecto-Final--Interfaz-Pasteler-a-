<?php
$user = Auth::user();
$themeCss = Theme::cssVariables();
$navModules = [];
if ($user) {
    $navModules = Module::visibleNavigationForUser((int) $user['id'], (string) $user['role']);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(app_config('name')) ?> | <?= h(app_config('company')) ?></title>
    <link rel="stylesheet" href="<?= h(url('assets/css/styles.css')) ?>">
    <style><?= h($themeCss) ?></style>
</head>
<body>
<nav class="navbar">
    <div class="container navbar-inner">
        <a class="brand" href="<?= h(url('index.php')) ?>">
            <span class="brand-icon">🍰</span>
            <span><?= h(app_config('name')) ?></span>
        </a>
        <div class="nav-links">
            <?php if ($user): ?>
                <span class="badge user-badge"><?= h($user['name']) ?> · <?= h($user['role_display']) ?></span>
                <a class="secondary" href="<?= h(url('dashboard.php')) ?>">Dashboard</a>

                <?php foreach ($navModules as $module): ?>
                    <a class="secondary" href="<?= h(url($module['route'])) ?>"><?= h($module['icon']) ?> <?= h($module['name']) ?></a>
                <?php endforeach; ?>

                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <a class="secondary" href="<?= h(url('admin/users.php')) ?>">👥 Usuarios</a>
                    <a class="secondary" href="<?= h(url('admin/appearance.php')) ?>">🎨 Apariencia</a>
                <?php endif; ?>
                <a href="<?= h(url('logout.php')) ?>">Cerrar sesión</a>
            <?php else: ?>
                <a class="secondary" href="<?= h(url('login.php')) ?>">Login</a>
                <a href="<?= h(url('register.php')) ?>">Registrarse</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main>
    <div class="container">
        <?php foreach (Flash::get() as $flash): ?>
            <div class="alert <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
