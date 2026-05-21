<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requirePermission('products', 'view');

$pdo = Database::pdo();
$canManageProducts = Auth::can('products', 'create') || Auth::can('products', 'edit') || Auth::can('products', 'manage');

$editingProduct = null;

if (isset($_GET['edit_id']) && $canManageProducts) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_GET['edit_id']]);
    $editingProduct = $stmt->fetch() ?: null;
}

if (is_post() && ($_POST['form_type'] ?? '') === 'producto') {
    if (!$canManageProducts) {
        Flash::set('danger', 'No tienes permiso para registrar o editar productos.');
        redirect('modules/products.php');
    }

    if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
        Flash::set('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('modules/products.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $categoria = trim((string) ($_POST['categoria'] ?? ''));
    $precio = (float) ($_POST['precio'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    $categoriasPermitidas = ['pastel', 'cupcake', 'galleta', 'postre'];

    if ($nombre === '') {
        Flash::set('danger', 'El nombre del producto es obligatorio.');
        redirect('modules/products.php');
    }

    if (!in_array($categoria, $categoriasPermitidas, true)) {
        Flash::set('danger', 'La categoría seleccionada no es válida.');
        redirect('modules/products.php');
    }

    if ($precio < 0) {
        Flash::set('danger', 'El precio no puede ser negativo.');
        redirect('modules/products.php');
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare(<<<SQL
UPDATE productos
SET nombre = :nombre,
    categoria = :categoria,
    precio = :precio,
    activo = :activo
WHERE id = :id
SQL);

            $stmt->execute([
                'nombre' => $nombre,
                'categoria' => $categoria,
                'precio' => $precio,
                'activo' => $activo,
                'id' => $id,
            ]);

            Flash::set('success', 'Producto actualizado correctamente.');
        } else {
            $stmt = $pdo->prepare(<<<SQL
INSERT INTO productos (nombre, categoria, precio, activo)
VALUES (:nombre, :categoria, :precio, :activo)
ON DUPLICATE KEY UPDATE
    categoria = VALUES(categoria),
    precio = VALUES(precio),
    activo = VALUES(activo)
SQL);

            $stmt->execute([
                'nombre' => $nombre,
                'categoria' => $categoria,
                'precio' => $precio,
                'activo' => $activo,
            ]);

            Flash::set('success', 'Producto registrado correctamente.');
        }
    } catch (Throwable $e) {
        Flash::set('danger', 'Error al guardar producto: ' . $e->getMessage());
    }

    redirect('modules/products.php');
}

$products = $pdo->query('SELECT * FROM productos ORDER BY categoria ASC, nombre ASC')->fetchAll();

require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Productos</h1>
        <p class="muted">
            Catálogo de pasteles, cupcakes, galletas y postres que produce la pastelería industrial.
        </p>

        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('products', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('products', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('products', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('products', 'manage') ? 'sí' : 'no' ?></span>
        </div>

        <?php if ($canManageProducts): ?>
            <div class="card small" style="margin-top: 22px;">
                <h3><?= $editingProduct ? 'Actualizar producto' : 'Registrar producto' ?></h3>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="form_type" value="producto">
                    <input type="hidden" name="id" value="<?= h((string) ($editingProduct['id'] ?? '')) ?>">

                    <div class="grid two">
                        <div class="form-group">
                            <label for="nombre">Nombre del producto</label>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                required
                                value="<?= h($editingProduct['nombre'] ?? '') ?>"
                                placeholder="Ej. Pastel de chocolate"
                            >
                        </div>

                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <?php $categoriaActual = $editingProduct['categoria'] ?? ''; ?>
                            <select id="categoria" name="categoria" required>
                                <option value="">Selecciona una categoría</option>
                                <option value="pastel" <?= $categoriaActual === 'pastel' ? 'selected' : '' ?>>Pastel</option>
                                <option value="cupcake" <?= $categoriaActual === 'cupcake' ? 'selected' : '' ?>>Cupcake</option>
                                <option value="galleta" <?= $categoriaActual === 'galleta' ? 'selected' : '' ?>>Galleta</option>
                                <option value="postre" <?= $categoriaActual === 'postre' ? 'selected' : '' ?>>Postre</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="precio">Precio</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="precio"
                                name="precio"
                                required
                                value="<?= h((string) ($editingProduct['precio'] ?? '')) ?>"
                                placeholder="Ej. 180"
                            >
                        </div>

                        <div class="form-group">
                            <label for="activo">Estado del producto</label>
                            <label style="display:flex; align-items:center; gap:8px; margin-top:14px;">
                                <input
                                    type="checkbox"
                                    id="activo"
                                    name="activo"
                                    value="1"
                                    <?= ((int) ($editingProduct['activo'] ?? 1) === 1) ? 'checked' : '' ?>
                                >
                                Producto activo
                            </label>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">
                            <?= $editingProduct ? 'Actualizar producto' : 'Registrar producto' ?>
                        </button>

                        <?php if ($editingProduct): ?>
                            <a class="btn secondary" href="<?= h(url('modules/products.php')) ?>">Cancelar edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert warning">
                Tu usuario solo puede consultar productos. Para registrar o editar productos se requiere permiso de Admin o permisos de edición.
            </div>
        <?php endif; ?>

        <div class="table-wrap" style="margin-top: 24px;">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <?php if ($canManageProducts): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= h($product['nombre']) ?></td>
                            <td><?= h($product['categoria']) ?></td>
                            <td>$<?= h(number_format((float) $product['precio'], 2)) ?></td>
                            <td>
                                <span class="status-pill <?= ((int) $product['activo'] === 1) ? 'online' : 'offline' ?>">
                                    <?= ((int) $product['activo'] === 1) ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <?php if ($canManageProducts): ?>
                                <td>
                                    <a href="<?= h(url('modules/products.php?edit_id=' . $product['id'])) ?>">Editar</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$products): ?>
                        <tr>
                            <td colspan="<?= $canManageProducts ? '5' : '4' ?>">Aún no hay productos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>