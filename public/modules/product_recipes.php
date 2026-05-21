<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requirePermission('product_recipes', 'view');

$pdo = Database::pdo();

$canManageRecipes =
    Auth::can('product_recipes', 'create') ||
    Auth::can('product_recipes', 'edit') ||
    Auth::can('product_recipes', 'delete') ||
    Auth::can('product_recipes', 'manage');

if (is_post() && ($_POST['form_type'] ?? '') === 'receta_producto') {
    if (!$canManageRecipes) {
        Flash::set('danger', 'No tienes permiso para registrar o editar recetas de productos.');
        redirect('modules/product_recipes.php');
    }

    if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
        Flash::set('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('modules/product_recipes.php');
    }

    $productoId = (int) ($_POST['producto_id'] ?? 0);
    $materiaPrimaId = (int) ($_POST['materia_prima_id'] ?? 0);
    $cantidadPorUnidad = (float) ($_POST['cantidad_por_unidad'] ?? 0);

    if ($productoId <= 0 || $materiaPrimaId <= 0 || $cantidadPorUnidad <= 0) {
        Flash::set('danger', 'Selecciona producto, materia prima y una cantidad mayor a 0.');
        redirect('modules/product_recipes.php');
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO producto_materias_primas
                (producto_id, materia_prima_id, cantidad_por_unidad)
            VALUES
                (:producto_id, :materia_prima_id, :cantidad_por_unidad)
            ON DUPLICATE KEY UPDATE
                cantidad_por_unidad = VALUES(cantidad_por_unidad)
        ');

        $stmt->execute([
            'producto_id' => $productoId,
            'materia_prima_id' => $materiaPrimaId,
            'cantidad_por_unidad' => $cantidadPorUnidad,
        ]);

        Flash::set('success', 'Relación producto-materia prima guardada correctamente.');
    } catch (Throwable $e) {
        Flash::set('danger', 'Error al guardar receta: ' . $e->getMessage());
    }

    redirect('modules/product_recipes.php');
}

if (is_post() && ($_POST['form_type'] ?? '') === 'eliminar_receta') {
    if (!$canManageRecipes) {
        Flash::set('danger', 'No tienes permiso para eliminar relaciones de recetas.');
        redirect('modules/product_recipes.php');
    }

    if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
        Flash::set('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('modules/product_recipes.php');
    }

    $productoId = (int) ($_POST['producto_id'] ?? 0);
    $materiaPrimaId = (int) ($_POST['materia_prima_id'] ?? 0);

    if ($productoId <= 0 || $materiaPrimaId <= 0) {
        Flash::set('danger', 'No se pudo identificar la relación a eliminar.');
        redirect('modules/product_recipes.php');
    }

    try {
        $stmt = $pdo->prepare('
            DELETE FROM producto_materias_primas
            WHERE producto_id = :producto_id
              AND materia_prima_id = :materia_prima_id
        ');

        $stmt->execute([
            'producto_id' => $productoId,
            'materia_prima_id' => $materiaPrimaId,
        ]);

        Flash::set('success', 'Relación eliminada correctamente.');
    } catch (Throwable $e) {
        Flash::set('danger', 'Error al eliminar receta: ' . $e->getMessage());
    }

    redirect('modules/product_recipes.php');
}

$productos = $pdo->query('
    SELECT id, nombre, categoria, precio, activo
    FROM productos
    WHERE activo = 1
    ORDER BY categoria ASC, nombre ASC
')->fetchAll();

$materias = $pdo->query('
    SELECT id, nombre, unidad, stock_actual
    FROM materias_primas
    ORDER BY nombre ASC
')->fetchAll();

$recetas = $pdo->query('
    SELECT
        pmp.producto_id,
        pmp.materia_prima_id,
        pmp.cantidad_por_unidad,
        p.nombre AS producto,
        p.categoria,
        mp.nombre AS materia_prima,
        mp.unidad,
        mp.stock_actual
    FROM producto_materias_primas pmp
    INNER JOIN productos p ON p.id = pmp.producto_id
    INNER JOIN materias_primas mp ON mp.id = pmp.materia_prima_id
    ORDER BY p.nombre ASC, mp.nombre ASC
')->fetchAll();

$resumenProductos = $pdo->query('
    SELECT
        p.id,
        p.nombre,
        p.categoria,
        COUNT(pmp.materia_prima_id) AS total_materias
    FROM productos p
    LEFT JOIN producto_materias_primas pmp ON pmp.producto_id = p.id
    WHERE p.activo = 1
    GROUP BY p.id, p.nombre, p.categoria
    ORDER BY p.nombre ASC
')->fetchAll();

require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Recetas de productos</h1>
        <p class="muted">
            Relaciona cada producto con las materias primas necesarias para fabricarlo.
            Esta información permite calcular consumo y descontar inventario al registrar producción.
        </p>

        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('product_recipes', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('product_recipes', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('product_recipes', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('product_recipes', 'manage') ? 'sí' : 'no' ?></span>
        </div>

        <?php if ($canManageRecipes): ?>
            <div class="card small" style="margin-top: 22px;">
                <h3>Agregar o actualizar materia prima del producto</h3>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="form_type" value="receta_producto">

                    <div class="grid two">
                        <div class="form-group">
                            <label for="producto_id">Producto</label>
                            <select id="producto_id" name="producto_id" required>
                                <option value="">Selecciona un producto</option>
                                <?php foreach ($productos as $producto): ?>
                                    <option value="<?= h((string) $producto['id']) ?>">
                                        <?= h($producto['nombre']) ?> · <?= h($producto['categoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="materia_prima_id">Materia prima</label>
                            <select id="materia_prima_id" name="materia_prima_id" required>
                                <option value="">Selecciona una materia prima</option>
                                <?php foreach ($materias as $materia): ?>
                                    <option value="<?= h((string) $materia['id']) ?>">
                                        <?= h($materia['nombre']) ?> · <?= h($materia['unidad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cantidad_por_unidad">Cantidad por unidad producida</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                id="cantidad_por_unidad"
                                name="cantidad_por_unidad"
                                required
                                placeholder="Ej. 0.50"
                            >
                            <small class="muted">
                                Ejemplo: si un pastel usa 0.50 kg de harina, escribe 0.50.
                            </small>
                        </div>
                    </div>

                    <button type="submit" class="btn">Guardar receta</button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert warning">
                Tu usuario solo puede consultar recetas. Para agregar o modificar relaciones se requiere permiso.
            </div>
        <?php endif; ?>

        <div class="table-wrap" style="margin-top: 24px;">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Materia prima</th>
                        <th>Cantidad por unidad</th>
                        <th>Stock actual</th>
                        <?php if ($canManageRecipes): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recetas as $receta): ?>
                        <tr>
                            <td><?= h($receta['producto']) ?></td>
                            <td><?= h($receta['categoria']) ?></td>
                            <td><?= h($receta['materia_prima']) ?></td>
                            <td>
                                <?= h(number_format((float) $receta['cantidad_por_unidad'], 2)) ?>
                                <?= h($receta['unidad']) ?>
                            </td>
                            <td>
                                <?= h(number_format((float) $receta['stock_actual'], 2)) ?>
                                <?= h($receta['unidad']) ?>
                            </td>
                            <?php if ($canManageRecipes): ?>
                                <td>
                                    <form method="post" onsubmit="return confirm('¿Eliminar esta materia prima de la receta?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="form_type" value="eliminar_receta">
                                        <input type="hidden" name="producto_id" value="<?= h((string) $receta['producto_id']) ?>">
                                        <input type="hidden" name="materia_prima_id" value="<?= h((string) $receta['materia_prima_id']) ?>">
                                        <button type="submit" class="btn danger" style="padding: 7px 12px;">Eliminar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$recetas): ?>
                        <tr>
                            <td colspan="<?= $canManageRecipes ? '6' : '5' ?>">
                                Aún no hay relaciones producto-materia prima registradas.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="grid two">
    <div class="card small">
        <h3>Resumen por producto</h3>

        <?php if ($resumenProductos): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Materias relacionadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumenProductos as $producto): ?>
                            <tr>
                                <td><?= h($producto['nombre']) ?></td>
                                <td><?= h($producto['categoria']) ?></td>
                                <td><?= h((string) $producto['total_materias']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">No hay productos activos registrados.</p>
        <?php endif; ?>
    </div>

    <div class="card small">
        <h3>Ejemplo de uso</h3>
        <p>
            Si un producto usa 0.50 kg de harina y se registran 20 unidades producidas,
            el sistema calcula un consumo de 10 kg de harina y lo descuenta del inventario.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>