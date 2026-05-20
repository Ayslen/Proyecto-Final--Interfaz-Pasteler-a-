<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <div class="hero-grid">
        <div>
            <span class="badge">Pastelería industrial</span>
            <h1>Manager inteligente para administración de producción</h1>
            <p>
                Sistema web base para administrar información de producción, usuarios y roles de una empresa de pasteles y postres empaquetados.
            </p>
            <p class="muted">
                Esta entrega cubre la estructura inicial, conexión a base de datos, login, registro, roles y protección de páginas.
            </p>
            <div class="nav-links" style="justify-content:flex-start; margin-top: 18px;">
                <?php if (Auth::check()): ?>
                    <a href="<?= h(url('dashboard.php')) ?>">Ir a mi dashboard</a>
                <?php else: ?>
                    <a href="<?= h(url('login.php')) ?>">Iniciar sesión</a>
                    <a class="secondary" href="<?= h(url('register.php')) ?>">Crear cuenta</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <h2>Materia prima principal</h2>
            <p class="muted">El sistema queda preparado para que otros módulos usen datos de producción e inventario.</p>
            <div class="grid" style="grid-template-columns: 1fr 1fr;">
                <div class="stat"><strong>Harina</strong><span>kg</span></div>
                <div class="stat"><strong>Azúcar</strong><span>kg</span></div>
                <div class="stat"><strong>Huevos</strong><span>piezas</span></div>
                <div class="stat"><strong>Empaques</strong><span>piezas</span></div>
            </div>
        </div>
    </div>
</section>
<section class="grid">
    <div class="card small">
        <h3>Login seguro</h3>
        <p>Valida usuario o correo y verifica la contraseña con hash.</p>
    </div>
    <div class="card small">
        <h3>Roles</h3>
        <p>Admin tiene acceso completo y User tiene acceso limitado.</p>
    </div>
    <div class="card small">
        <h3>Base de datos</h3>
        <p>MySQL con creación automática de tablas iniciales.</p>
    </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
