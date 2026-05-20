<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/partials/header.php';
?>
<section class="form-page">
    <div class="card form-card">
        <span class="badge">403</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Acceso denegado</h1>
        <p class="muted">Tu rol no tiene permiso para entrar a esta sección.</p>
        <a class="btn" href="<?= h(url('dashboard.php')) ?>">Volver a mi dashboard</a>
    </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
