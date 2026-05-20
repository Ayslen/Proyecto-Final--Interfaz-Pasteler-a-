<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requirePermission('production', 'view');
$pdo = Database::pdo();
$records = $pdo->query(<<<SQL
SELECT pd.fecha, pd.cantidad, pd.linea, p.nombre AS producto, u.name AS usuario
FROM produccion_diaria pd
INNER JOIN productos p ON p.id = pd.producto_id
LEFT JOIN users u ON u.id = pd.created_by
ORDER BY pd.fecha DESC, pd.id DESC
LIMIT 30
SQL)->fetchAll();
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Registro de producción</h1>
        <p class="muted">Producción diaria de pasteles, cupcakes, galletas y postres para venta al público o distribución.</p>
        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('production', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('production', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('production', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('production', 'manage') ? 'sí' : 'no' ?></span>
        </div>
        <?php if (Auth::can('production', 'create')): ?>
            <div class="alert success">Tu permiso permite registrar producción. Esta entrega deja preparada la validación de permisos para que el integrante del módulo de producción conecte aquí su formulario final.</div>
        <?php endif; ?>
        <div class="table-wrap" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Línea</th>
                        <th>Registró</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= h($record['fecha']) ?></td>
                            <td><?= h($record['producto']) ?></td>
                            <td><?= h((string) $record['cantidad']) ?></td>
                            <td><?= h($record['linea']) ?></td>
                            <td><?= h($record['usuario'] ?? 'Sistema') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$records): ?>
                        <tr><td colspan="5">Aún no hay registros de producción.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
