<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requirePermission('products', 'view');
$pdo = Database::pdo();
$products = $pdo->query('SELECT * FROM productos ORDER BY categoria ASC, nombre ASC')->fetchAll();
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Productos</h1>
        <p class="muted">Catálogo base de pasteles y postres empaquetados.</p>
        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('products', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('products', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('products', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('products', 'manage') ? 'sí' : 'no' ?></span>
        </div>
        <div class="table-wrap" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= h($product['nombre']) ?></td>
                            <td><?= h($product['categoria']) ?></td>
                            <td>$<?= h(number_format((float) $product['precio'], 2)) ?></td>
                            <td><?= ((int) $product['activo'] === 1) ? 'Activo' : 'Inactivo' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
