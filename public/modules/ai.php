<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requirePermission('ai', 'view');
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Consultas IA</h1>
        <p class="muted">Esta pantalla deja preparada la sección donde se conectará la API de IA para responder preguntas sobre producción, inventario y productos.</p>
        <div class="grid two">
            <div class="stat"><strong>Ejemplo User</strong><span>¿Cuántas unidades se produjeron hoy?</span></div>
            <div class="stat"><strong>Ejemplo Admin</strong><span>¿Qué materia prima se está consumiendo más rápido?</span></div>
        </div>
        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('ai', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('ai', 'manage') ? 'sí' : 'no' ?></span>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
