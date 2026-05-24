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
            $stmt = $pdo->prepare('UPDATE productos SET nombre = :nombre, categoria = :categoria, precio = :precio, activo = :activo WHERE id = :id');

            $stmt->execute([
                'nombre' => $nombre,
                'categoria' => $categoria,
                'precio' => $precio,
                'activo' => $activo,
                'id' => $id,
            ]);

            Flash::set('success', 'Producto actualizado correctamente.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO productos (nombre, categoria, precio, activo) VALUES (:nombre, :categoria, :precio, :activo) ON DUPLICATE KEY UPDATE categoria = VALUES(categoria), precio = VALUES(precio), activo = VALUES(activo)');

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

<section class="hero" style="padding-bottom: 40px;">
    <div class="card" style="margin-bottom: 24px;">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Menú de Productos</h1>
        <p class="muted">
            Catálogo oficial de pasteles, cupcakes, galletas y postres disponibles para venta y producción en la pastelería.
        </p>

        <div class="permission-summary" style="margin-top: 15px;">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('products', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('products', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('products', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('products', 'manage') ? 'sí' : 'no' ?></span>
        </div>
    </div>

    <?php if ($canManageProducts): ?>
        <div class="card highlight" style="margin-bottom: 24px;">
            <h3 style="margin-bottom: 20px;">
                <?= $editingProduct ? '✏️ Actualizar detalles del producto' : '🍰 Agregar nuevo postre al menú' ?>
            </h3>

            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="form_type" value="producto">
                <input type="hidden" name="id" value="<?= h((string) ($editingProduct['id'] ?? '')) ?>">

                <div class="grid four">
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="nombre">Nombre completo del postre</label>
                        <input type="text" id="nombre" name="nombre" required
                            value="<?= h($editingProduct['nombre'] ?? '') ?>"
                            placeholder="Ej. Pastel de Trufa y Frambuesa">
                    </div>

                    <div class="form-group">
                        <label for="categoria">Categoría</label>
                        <?php $categoriaActual = $editingProduct['categoria'] ?? ''; ?>
                        <select id="categoria" name="categoria" required>
                            <option value="">Selecciona una categoría...</option>
                            <option value="pastel" <?= $categoriaActual === 'pastel' ? 'selected' : '' ?>>Pastel</option>
                            <option value="cupcake" <?= $categoriaActual === 'cupcake' ? 'selected' : '' ?>>Cupcake</option>
                            <option value="galleta" <?= $categoriaActual === 'galleta' ? 'selected' : '' ?>>Galleta</option>
                            <option value="postre" <?= $categoriaActual === 'postre' ? 'selected' : '' ?>>Postre Especial</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="precio">Precio de Venta ($)</label>
                        <input type="number" step="0.01" min="0" id="precio" name="precio" required
                            value="<?= h((string) ($editingProduct['precio'] ?? '')) ?>"
                            placeholder="0.00">
                    </div>
                </div>

                <div class="grid two" style="align-items: center; margin-top: 5px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display:flex; align-items:center; gap:8px; cursor: pointer; font-size: 15px; padding: 10px; border-radius: 12px; background: rgba(255,255,255,0.4); border: 1px solid var(--border); width: fit-content;">
                            <input type="checkbox" id="activo" name="activo" value="1"
                                <?= ((int) ($editingProduct['activo'] ?? 1) === 1) ? 'checked' : '' ?>
                                style="width: 20px; height: 20px;">
                            <strong>Producto Activo</strong> (Visible para producción y venta)
                        </label>
                    </div>

                    <div class="actions" style="justify-content: flex-end;">
                        <?php if ($editingProduct): ?>
                            <a class="btn secondary" href="<?= h(url('modules/products.php')) ?>">Cancelar edición</a>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn" style="padding: 12px 24px;">
                            <?= $editingProduct ? 'ACTUALIZAR CATÁLOGO' : 'AGREGAR AL CATÁLOGO' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="alert warning">
            Tu usuario solo puede consultar el menú de productos. Para registrar o editar postres se requiere permiso de Admin.
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>📖 Catálogo Actual</h3>
        <div class="table-wrap" style="margin-top: 15px;">
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
                            <td style="font-weight: bold; color: var(--primary-dark); font-size: 15px;">
                                <?= h($product['nombre']) ?>
                            </td>
                            <td>
                                <span style="text-transform: uppercase; font-size: 11px; letter-spacing: 1px; color: var(--muted);">
                                    <?= h($product['categoria']) ?>
                                </span>
                            </td>
                            <td style="font-weight: bold; color: var(--success); font-size: 15px;">
                                $<?= h(number_format((float) $product['precio'], 2)) ?>
                            </td>
                            <td>
                                <span class="status-pill <?= ((int) $product['activo'] === 1) ? 'online' : 'error' ?>" style="padding: 4px 10px;">
                                    <?= ((int) $product['activo'] === 1) ? '✓ Disponible' : '✗ Pausado' ?>
                                </span>
                            </td>
                            <?php if ($canManageProducts): ?>
                                <td>
                                    <a class="btn secondary" style="padding: 5px 12px; font-size: 12px;" href="<?= h(url('modules/products.php?edit_id=' . $product['id'])) ?>">Editar</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$products): ?>
                        <tr>
                            <td colspan="<?= $canManageProducts ? '5' : '4' ?>" style="text-align: center; padding: 30px;">
                                <span style="font-size: 24px; display: block; margin-bottom: 10px;">🧁</span>
                                El menú está vacío. ¡Empieza a agregar los mejores postres!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            let alertas = document.querySelectorAll('.alert');
            alertas.forEach(function(alerta) {
                alerta.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                alerta.style.opacity = "0";
                alerta.style.transform = "translateY(-10px)";
                setTimeout(() => alerta.remove(), 500);
            });
        }, 3500);
    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>