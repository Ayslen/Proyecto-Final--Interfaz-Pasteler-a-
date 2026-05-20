<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requirePermission('inventory', 'view');
$pdo = Database::pdo();
$items = $pdo->query('SELECT * FROM materias_primas ORDER BY nombre ASC')->fetchAll();
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Inventario de materia prima</h1>
        <p class="muted">Consulta de insumos de la pastelería industrial: harina, azúcar, huevos, leche, mantequilla, crema, chocolate, frutas, levadura, colorantes y empaques.</p>
        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('inventory', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('inventory', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('inventory', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('inventory', 'manage') ? 'sí' : 'no' ?></span>
        </div>
        <div class="table-wrap" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Materia prima</th>
                        <th>Unidad</th>
                        <th>Stock actual</th>
                        <th>Stock mínimo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h($item['nombre']) ?></td>
                            <td><?= h($item['unidad']) ?></td>
                            <td><?= h((string) $item['stock_actual']) ?></td>
                            <td><?= h((string) $item['stock_minimo']) ?></td>
                            <td><?= ((float) $item['stock_actual'] <= (float) $item['stock_minimo']) ? 'Bajo stock' : 'Disponible' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
