<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requirePermission('reports', 'view');
$pdo = Database::pdo();
$totalProductos = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
$totalMaterias = (int) $pdo->query('SELECT COUNT(*) FROM materias_primas')->fetchColumn();
$totalProduccion = (int) $pdo->query('SELECT COALESCE(SUM(cantidad), 0) FROM produccion_diaria')->fetchColumn();
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Dashboards y reportes</h1>
        <p class="muted">Base para mostrar información estratégica de producción e inventario.</p>
        <div class="grid">
            <div class="stat"><strong><?= h((string) $totalProductos) ?></strong><span>Productos registrados</span></div>
            <div class="stat"><strong><?= h((string) $totalMaterias) ?></strong><span>Materias primas</span></div>
            <div class="stat"><strong><?= h((string) $totalProduccion) ?></strong><span>Unidades producidas</span></div>
        </div>
        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Administrar: <?= Auth::can('reports', 'manage') ? 'sí' : 'no' ?></span>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
