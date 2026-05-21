<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requirePermission('inventory_movements', 'view');

$pdo = Database::pdo();

$canManageMovements =
    Auth::can('inventory_movements', 'create') ||
    Auth::can('inventory_movements', 'edit') ||
    Auth::can('inventory_movements', 'manage');

$materias = $pdo->query('
    SELECT id, nombre, unidad, stock_actual, stock_minimo
    FROM materias_primas
    ORDER BY nombre ASC
')->fetchAll();

if (is_post() && ($_POST['form_type'] ?? '') === 'movimiento_inventario') {
    if (!$canManageMovements) {
        Flash::set('danger', 'No tienes permiso para registrar movimientos de inventario.');
        redirect('modules/inventory_movements.php');
    }

    if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
        Flash::set('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('modules/inventory_movements.php');
    }

    $materiaPrimaId = (int) ($_POST['materia_prima_id'] ?? 0);
    $tipo = trim((string) ($_POST['tipo'] ?? ''));
    $cantidad = (float) ($_POST['cantidad'] ?? 0);
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

    $tiposPermitidos = ['entrada', 'salida', 'ajuste'];

    if ($materiaPrimaId <= 0 || !in_array($tipo, $tiposPermitidos, true)) {
        Flash::set('danger', 'Selecciona una materia prima y un tipo de movimiento válido.');
        redirect('modules/inventory_movements.php');
    }

    if ($cantidad < 0 || ($tipo !== 'ajuste' && $cantidad <= 0)) {
        Flash::set('danger', 'La cantidad debe ser mayor a 0. En ajuste puede ser 0 para dejar el stock en cero.');
        redirect('modules/inventory_movements.php');
    }

    if ($descripcion === '') {
        $descripcion = 'Movimiento manual de inventario';
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            SELECT id, nombre, stock_actual
            FROM materias_primas
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $materiaPrimaId]);
        $materia = $stmt->fetch();

        if (!$materia) {
            throw new RuntimeException('La materia prima seleccionada no existe.');
        }

        $stockActual = (float) $materia['stock_actual'];
        $cantidadMovimiento = $cantidad;

        if ($tipo === 'entrada') {
            $nuevoStock = $stockActual + $cantidad;

            $stmt = $pdo->prepare('
                UPDATE materias_primas
                SET stock_actual = :nuevo_stock
                WHERE id = :id
            ');
            $stmt->execute([
                'nuevo_stock' => $nuevoStock,
                'id' => $materiaPrimaId,
            ]);
        }

        if ($tipo === 'salida') {
            $nuevoStock = max($stockActual - $cantidad, 0);

            $stmt = $pdo->prepare('
                UPDATE materias_primas
                SET stock_actual = :nuevo_stock
                WHERE id = :id
            ');
            $stmt->execute([
                'nuevo_stock' => $nuevoStock,
                'id' => $materiaPrimaId,
            ]);
        }

        if ($tipo === 'ajuste') {
            $nuevoStock = $cantidad;
            $diferencia = $nuevoStock - $stockActual;
            $cantidadMovimiento = abs($diferencia);

            $stmt = $pdo->prepare('
                UPDATE materias_primas
                SET stock_actual = :nuevo_stock
                WHERE id = :id
            ');
            $stmt->execute([
                'nuevo_stock' => $nuevoStock,
                'id' => $materiaPrimaId,
            ]);

            $descripcion .= ' | Stock anterior: ' . $stockActual . ' | Stock nuevo: ' . $nuevoStock;
        }

        $stmt = $pdo->prepare('
            INSERT INTO movimientos_inventario
                (materia_prima_id, tipo, cantidad, descripcion, created_by)
            VALUES
                (:materia_prima_id, :tipo, :cantidad, :descripcion, :created_by)
        ');

        $stmt->execute([
            'materia_prima_id' => $materiaPrimaId,
            'tipo' => $tipo,
            'cantidad' => $cantidadMovimiento,
            'descripcion' => $descripcion,
            'created_by' => Auth::id(),
        ]);

        $pdo->commit();

        Flash::set('success', 'Movimiento de inventario registrado correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        Flash::set('danger', 'Error al registrar movimiento: ' . $e->getMessage());
    }

    redirect('modules/inventory_movements.php');
}

$movements = $pdo->query('
    SELECT
        mi.id,
        mi.tipo,
        mi.cantidad,
        mi.descripcion,
        mi.created_at,
        mp.nombre AS materia_prima,
        mp.unidad,
        u.name AS usuario
    FROM movimientos_inventario mi
    INNER JOIN materias_primas mp ON mp.id = mi.materia_prima_id
    LEFT JOIN users u ON u.id = mi.created_by
    ORDER BY mi.created_at DESC, mi.id DESC
    LIMIT 100
')->fetchAll();

$resumen = $pdo->query('
    SELECT
        tipo,
        COUNT(*) AS total_movimientos,
        SUM(cantidad) AS cantidad_total
    FROM movimientos_inventario
    GROUP BY tipo
    ORDER BY tipo ASC
')->fetchAll();

require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Movimientos de inventario</h1>
        <p class="muted">
            Historial de entradas, salidas y ajustes de materia prima. Este módulo ayuda a rastrear
            cómo cambia el inventario de la pastelería.
        </p>

        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('inventory_movements', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('inventory_movements', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('inventory_movements', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('inventory_movements', 'manage') ? 'sí' : 'no' ?></span>
        </div>

        <?php if ($canManageMovements): ?>
            <div class="card small" style="margin-top: 22px;">
                <h3>Registrar movimiento manual</h3>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="form_type" value="movimiento_inventario">

                    <div class="grid two">
                        <div class="form-group">
                            <label for="materia_prima_id">Materia prima</label>
                            <select id="materia_prima_id" name="materia_prima_id" required>
                                <option value="">Selecciona una materia prima</option>
                                <?php foreach ($materias as $materia): ?>
                                    <option value="<?= h((string) $materia['id']) ?>">
                                        <?= h($materia['nombre']) ?>
                                        · Stock actual: <?= h(number_format((float) $materia['stock_actual'], 2)) ?>
                                        <?= h($materia['unidad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tipo">Tipo de movimiento</label>
                            <select id="tipo" name="tipo" required>
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="ajuste">Ajuste de stock</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cantidad">Cantidad</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="cantidad"
                                name="cantidad"
                                required
                                placeholder="Ej. 25"
                            >
                            <small class="muted">
                                En ajuste, esta cantidad será el nuevo stock final.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <input
                                type="text"
                                id="descripcion"
                                name="descripcion"
                                placeholder="Ej. Compra semanal de harina"
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn">Registrar movimiento</button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert warning">
                Tu usuario solo puede consultar movimientos. Para registrar entradas, salidas o ajustes se requiere permiso.
            </div>
        <?php endif; ?>

        <div class="table-wrap" style="margin-top: 24px;">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Materia prima</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= h($movement['created_at']) ?></td>
                            <td><?= h($movement['materia_prima']) ?></td>
                            <td>
                                <span class="status-pill <?= $movement['tipo'] === 'entrada' ? 'online' : ($movement['tipo'] === 'salida' ? 'error' : 'warning') ?>">
                                    <?= h($movement['tipo']) ?>
                                </span>
                            </td>
                            <td>
                                <?= h(number_format((float) $movement['cantidad'], 2)) ?>
                                <?= h($movement['unidad']) ?>
                            </td>
                            <td><?= h($movement['descripcion'] ?? '') ?></td>
                            <td><?= h($movement['usuario'] ?? 'Sistema') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$movements): ?>
                        <tr>
                            <td colspan="6">Aún no hay movimientos de inventario registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="grid two">
    <div class="card small">
        <h3>Resumen de movimientos</h3>

        <?php if ($resumen): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Movimientos</th>
                            <th>Cantidad total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen as $item): ?>
                            <tr>
                                <td><?= h($item['tipo']) ?></td>
                                <td><?= h((string) $item['total_movimientos']) ?></td>
                                <td><?= h(number_format((float) $item['cantidad_total'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">No hay información suficiente para generar resumen.</p>
        <?php endif; ?>
    </div>

    <div class="card small">
        <h3>Uso del módulo</h3>
        <p>
            Las entradas aumentan el stock, las salidas lo disminuyen y los ajustes permiten colocar
            el inventario en una cantidad final específica. Esto sirve como historial para auditoría interna.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>