<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requirePermission('inventory', 'view');

$pdo = Database::pdo();
$canManageInventory = Auth::can('inventory', 'create') || Auth::can('inventory', 'edit') || Auth::can('inventory', 'manage');

$editingItem = null;

if (isset($_GET['edit_id']) && $canManageInventory) {
    $stmt = $pdo->prepare('SELECT * FROM materias_primas WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_GET['edit_id']]);
    $editingItem = $stmt->fetch() ?: null;
}

function registrarMovimientoInventario(PDO $pdo, int $materiaId, string $tipo, float $cantidad, string $descripcion): void
{
    if ($cantidad <= 0) {
        return;
    }

    $stmt = $pdo->prepare(<<<SQL
INSERT INTO movimientos_inventario
    (materia_prima_id, tipo, cantidad, descripcion, created_by)
VALUES
    (:materia_prima_id, :tipo, :cantidad, :descripcion, :created_by)
SQL);

    $stmt->execute([
        'materia_prima_id' => $materiaId,
        'tipo' => $tipo,
        'cantidad' => $cantidad,
        'descripcion' => $descripcion,
        'created_by' => Auth::id(),
    ]);
}

if (is_post() && ($_POST['form_type'] ?? '') === 'materia_prima') {
    if (!$canManageInventory) {
        Flash::set('danger', 'No tienes permiso para registrar o editar materia prima.');
        redirect('modules/inventory.php');
    }

    if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
        Flash::set('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('modules/inventory.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $unidad = trim((string) ($_POST['unidad'] ?? ''));
    $stockActual = (float) ($_POST['stock_actual'] ?? 0);
    $stockMinimo = (float) ($_POST['stock_minimo'] ?? 0);

    if ($nombre === '' || $unidad === '') {
        Flash::set('danger', 'El nombre y la unidad son obligatorios.');
        redirect('modules/inventory.php');
    }

    if ($stockActual < 0 || $stockMinimo < 0) {
        Flash::set('danger', 'El stock no puede ser negativo.');
        redirect('modules/inventory.php');
    }

    try {
        $pdo->beginTransaction();

        $oldStock = null;
        $materiaId = $id;

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT id, stock_actual FROM materias_primas WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $oldItem = $stmt->fetch();

            if (!$oldItem) {
                throw new RuntimeException('La materia prima que intentas editar no existe.');
            }

            $oldStock = (float) $oldItem['stock_actual'];

            $stmt = $pdo->prepare(<<<SQL
UPDATE materias_primas
SET nombre = :nombre,
    unidad = :unidad,
    stock_actual = :stock_actual,
    stock_minimo = :stock_minimo
WHERE id = :id
SQL);

            $stmt->execute([
                'nombre' => $nombre,
                'unidad' => $unidad,
                'stock_actual' => $stockActual,
                'stock_minimo' => $stockMinimo,
                'id' => $id,
            ]);

            $diferencia = $stockActual - $oldStock;

            if ($diferencia !== 0.0) {
                registrarMovimientoInventario(
                    $pdo,
                    $materiaId,
                    $diferencia > 0 ? 'entrada' : 'salida',
                    abs($diferencia),
                    'Actualización manual de stock'
                );
            }

            Flash::set('success', 'Materia prima actualizada correctamente.');
        } else {
            $stmt = $pdo->prepare('SELECT id, stock_actual FROM materias_primas WHERE nombre = :nombre LIMIT 1');
            $stmt->execute(['nombre' => $nombre]);
            $oldItem = $stmt->fetch();

            $stmt = $pdo->prepare(<<<SQL
INSERT INTO materias_primas (nombre, unidad, stock_actual, stock_minimo)
VALUES (:nombre, :unidad, :stock_actual, :stock_minimo)
ON DUPLICATE KEY UPDATE
    unidad = VALUES(unidad),
    stock_actual = VALUES(stock_actual),
    stock_minimo = VALUES(stock_minimo)
SQL);

            $stmt->execute([
                'nombre' => $nombre,
                'unidad' => $unidad,
                'stock_actual' => $stockActual,
                'stock_minimo' => $stockMinimo,
            ]);

            $stmt = $pdo->prepare('SELECT id, stock_actual FROM materias_primas WHERE nombre = :nombre LIMIT 1');
            $stmt->execute(['nombre' => $nombre]);
            $savedItem = $stmt->fetch();

            if (!$savedItem) {
                throw new RuntimeException('No se pudo guardar la materia prima.');
            }

            $materiaId = (int) $savedItem['id'];

            if ($oldItem) {
                $diferencia = $stockActual - (float) $oldItem['stock_actual'];

                if ($diferencia !== 0.0) {
                    registrarMovimientoInventario(
                        $pdo,
                        $materiaId,
                        $diferencia > 0 ? 'entrada' : 'salida',
                        abs($diferencia),
                        'Actualización manual de stock'
                    );
                }

                Flash::set('success', 'Materia prima existente actualizada correctamente.');
            } else {
                registrarMovimientoInventario(
                    $pdo,
                    $materiaId,
                    'entrada',
                    $stockActual,
                    'Registro inicial de materia prima'
                );

                Flash::set('success', 'Materia prima registrada correctamente.');
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        Flash::set('danger', 'Error al guardar materia prima: ' . $e->getMessage());
    }

    redirect('modules/inventory.php');
}

$items = $pdo->query('SELECT * FROM materias_primas ORDER BY nombre ASC')->fetchAll();

$movements = [];
try {
    $movements = $pdo->query(<<<SQL
SELECT mi.tipo, mi.cantidad, mi.descripcion, mi.created_at, mp.nombre AS materia_prima, u.name AS usuario
FROM movimientos_inventario mi
INNER JOIN materias_primas mp ON mp.id = mi.materia_prima_id
LEFT JOIN users u ON u.id = mi.created_by
ORDER BY mi.created_at DESC, mi.id DESC
LIMIT 10
SQL)->fetchAll();
} catch (Throwable $e) {
    $movements = [];
}

require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Inventario de materia prima</h1>
        <p class="muted">
            Consulta, registro y actualización de insumos de la pastelería industrial.
        </p>

        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('inventory', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('inventory', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('inventory', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('inventory', 'manage') ? 'sí' : 'no' ?></span>
        </div>

        <?php if ($canManageInventory): ?>
            <div class="card small" style="margin-top: 22px;">
                <h3><?= $editingItem ? 'Actualizar materia prima' : 'Registrar materia prima' ?></h3>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="form_type" value="materia_prima">
                    <input type="hidden" name="id" value="<?= h((string) ($editingItem['id'] ?? '')) ?>">

                    <div class="grid two">
                        <div class="form-group">
                            <label for="nombre">Materia prima</label>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                required
                                value="<?= h($editingItem['nombre'] ?? '') ?>"
                                placeholder="Ej. Harina, azúcar, fresas"
                            >
                        </div>

                        <div class="form-group">
                            <label for="unidad">Unidad</label>
                            <select id="unidad" name="unidad" required>
                                <?php
                                $unidadActual = $editingItem['unidad'] ?? '';
                                $unidades = ['kg', 'litros', 'piezas', 'gramos', 'ml'];
                                foreach ($unidades as $unidad):
                                ?>
                                    <option value="<?= h($unidad) ?>" <?= $unidadActual === $unidad ? 'selected' : '' ?>>
                                        <?= h($unidad) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="stock_actual">Stock actual</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="stock_actual"
                                name="stock_actual"
                                required
                                value="<?= h((string) ($editingItem['stock_actual'] ?? '')) ?>"
                                placeholder="Ej. 250"
                            >
                        </div>

                        <div class="form-group">
                            <label for="stock_minimo">Stock mínimo</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="stock_minimo"
                                name="stock_minimo"
                                required
                                value="<?= h((string) ($editingItem['stock_minimo'] ?? '')) ?>"
                                placeholder="Ej. 50"
                            >
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">
                            <?= $editingItem ? 'Actualizar materia prima' : 'Registrar materia prima' ?>
                        </button>

                        <?php if ($editingItem): ?>
                            <a class="btn secondary" href="<?= h(url('modules/inventory.php')) ?>">Cancelar edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert warning">
                Tu usuario solo puede consultar inventario. Para registrar o editar materia prima se requiere permiso de Admin o permisos de edición.
            </div>
        <?php endif; ?>

        <div class="table-wrap" style="margin-top: 24px;">
            <table>
                <thead>
                    <tr>
                        <th>Materia prima</th>
                        <th>Unidad</th>
                        <th>Stock actual</th>
                        <th>Stock mínimo</th>
                        <th>Estado</th>
                        <?php if ($canManageInventory): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $bajoStock = (float) $item['stock_actual'] <= (float) $item['stock_minimo']; ?>
                        <tr>
                            <td><?= h($item['nombre']) ?></td>
                            <td><?= h($item['unidad']) ?></td>
                            <td><?= h(number_format((float) $item['stock_actual'], 2)) ?></td>
                            <td><?= h(number_format((float) $item['stock_minimo'], 2)) ?></td>
                            <td>
                                <span class="status-pill <?= $bajoStock ? 'error' : 'online' ?>">
                                    <?= $bajoStock ? 'Bajo stock' : 'Disponible' ?>
                                </span>
                            </td>
                            <?php if ($canManageInventory): ?>
                                <td>
                                    <a href="<?= h(url('modules/inventory.php?edit_id=' . $item['id'])) ?>">Editar</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$items): ?>
                        <tr>
                            <td colspan="<?= $canManageInventory ? '6' : '5' ?>">Aún no hay materia prima registrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="grid two">
    <div class="card small">
        <h3>Movimientos recientes</h3>
        <?php if ($movements): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td><?= h($movement['materia_prima']) ?></td>
                                <td><?= h($movement['tipo']) ?></td>
                                <td><?= h(number_format((float) $movement['cantidad'], 2)) ?></td>
                                <td><?= h($movement['descripcion'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Aún no hay movimientos registrados.</p>
        <?php endif; ?>
    </div>

    <div class="card small">
        <h3>Uso del módulo</h3>
        <p>
            Este módulo permite registrar materia prima, actualizar existencias y detectar insumos con bajo stock.
            Los cambios de stock se guardan también como movimientos de inventario.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>