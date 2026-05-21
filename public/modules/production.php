<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requirePermission('production', 'view');

$pdo = Database::pdo();
$canCreateProduction = Auth::can('production', 'create') || Auth::can('production', 'manage');

function descontarInventarioPorProduccion(PDO $pdo, int $productoId, int $cantidadProducida): int
{
    $stmt = $pdo->prepare(<<<SQL
SELECT
    pmp.materia_prima_id,
    mp.nombre AS materia_prima,
    pmp.cantidad_por_unidad,
    (pmp.cantidad_por_unidad * :cantidad) AS consumo_total
FROM producto_materias_primas pmp
INNER JOIN materias_primas mp ON mp.id = pmp.materia_prima_id
WHERE pmp.producto_id = :producto_id
SQL);

    $stmt->execute([
        'cantidad' => $cantidadProducida,
        'producto_id' => $productoId,
    ]);

    $recetas = $stmt->fetchAll();

    foreach ($recetas as $receta) {
        $consumoTotal = (float) $receta['consumo_total'];

        if ($consumoTotal <= 0) {
            continue;
        }

        $update = $pdo->prepare(<<<SQL
UPDATE materias_primas
SET stock_actual = GREATEST(stock_actual - :consumo, 0)
WHERE id = :materia_prima_id
SQL);

        $update->execute([
            'consumo' => $consumoTotal,
            'materia_prima_id' => (int) $receta['materia_prima_id'],
        ]);

        $movement = $pdo->prepare(<<<SQL
INSERT INTO movimientos_inventario
    (materia_prima_id, tipo, cantidad, descripcion, created_by)
VALUES
    (:materia_prima_id, 'salida', :cantidad, :descripcion, :created_by)
SQL);

        $movement->execute([
            'materia_prima_id' => (int) $receta['materia_prima_id'],
            'cantidad' => $consumoTotal,
            'descripcion' => 'Consumo automático por registro de producción',
            'created_by' => Auth::id(),
        ]);
    }

    return count($recetas);
}

if (is_post() && ($_POST['form_type'] ?? '') === 'produccion') {
    if (!$canCreateProduction) {
        Flash::set('danger', 'No tienes permiso para registrar producción.');
        redirect('modules/production.php');
    }

    if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
        Flash::set('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('modules/production.php');
    }

    $fecha = trim((string) ($_POST['fecha'] ?? ''));
    $productoId = (int) ($_POST['producto_id'] ?? 0);
    $cantidad = (int) ($_POST['cantidad'] ?? 0);
    $linea = trim((string) ($_POST['linea'] ?? ''));

    if ($fecha === '' || $productoId <= 0 || $cantidad <= 0 || $linea === '') {
        Flash::set('danger', 'Completa todos los campos de producción correctamente.');
        redirect('modules/production.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, nombre FROM productos WHERE id = :id AND activo = 1 LIMIT 1');
        $stmt->execute(['id' => $productoId]);
        $producto = $stmt->fetch();

        if (!$producto) {
            throw new RuntimeException('El producto seleccionado no existe o está inactivo.');
        }

        $stmt = $pdo->prepare(<<<SQL
INSERT INTO produccion_diaria
    (fecha, producto_id, cantidad, linea, created_by)
VALUES
    (:fecha, :producto_id, :cantidad, :linea, :created_by)
SQL);

        $stmt->execute([
            'fecha' => $fecha,
            'producto_id' => $productoId,
            'cantidad' => $cantidad,
            'linea' => $linea,
            'created_by' => Auth::id(),
        ]);

        $recetasUsadas = descontarInventarioPorProduccion($pdo, $productoId, $cantidad);

        $pdo->commit();

        if ($recetasUsadas > 0) {
            Flash::set('success', 'Producción registrada correctamente. También se descontó materia prima del inventario.');
        } else {
            Flash::set('success', 'Producción registrada correctamente. Este producto aún no tiene receta de materia prima configurada.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        Flash::set('danger', 'Error al registrar producción: ' . $e->getMessage());
    }

    redirect('modules/production.php');
}

$products = $pdo->query(<<<SQL
SELECT id, nombre, categoria
FROM productos
WHERE activo = 1
ORDER BY categoria ASC, nombre ASC
SQL)->fetchAll();

$records = $pdo->query(<<<SQL
SELECT pd.fecha, pd.cantidad, pd.linea, p.nombre AS producto, u.name AS usuario
FROM produccion_diaria pd
INNER JOIN productos p ON p.id = pd.producto_id
LEFT JOIN users u ON u.id = pd.created_by
ORDER BY pd.fecha DESC, pd.id DESC
LIMIT 40
SQL)->fetchAll();

$summary = $pdo->query(<<<SQL
SELECT p.nombre AS producto, SUM(pd.cantidad) AS total
FROM produccion_diaria pd
INNER JOIN productos p ON p.id = pd.producto_id
GROUP BY p.id, p.nombre
ORDER BY total DESC
LIMIT 5
SQL)->fetchAll();

require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero">
    <div class="card">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Registro de producción</h1>
        <p class="muted">
            Registro diario de producción de pasteles, cupcakes, galletas y postres.
            Al registrar producción, el sistema descuenta automáticamente la materia prima relacionada con la receta del producto.
        </p>

        <div class="permission-summary">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('production', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('production', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('production', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('production', 'manage') ? 'sí' : 'no' ?></span>
        </div>

        <?php if ($canCreateProduction): ?>
            <div class="card small" style="margin-top: 22px;">
                <h3>Registrar producción diaria</h3>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="form_type" value="produccion">

                    <div class="grid two">
                        <div class="form-group">
                            <label for="fecha">Fecha de producción</label>
                            <input
                                type="date"
                                id="fecha"
                                name="fecha"
                                required
                                value="<?= h(date('Y-m-d')) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="producto_id">Producto fabricado</label>
                            <select id="producto_id" name="producto_id" required>
                                <option value="">Selecciona un producto</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= h((string) $product['id']) ?>">
                                        <?= h($product['nombre']) ?> · <?= h($product['categoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cantidad">Cantidad producida</label>
                            <input
                                type="number"
                                id="cantidad"
                                name="cantidad"
                                min="1"
                                required
                                placeholder="Ej. 25"
                            >
                        </div>

                        <div class="form-group">
                            <label for="linea">Línea de producción</label>
                            <select id="linea" name="linea" required>
                                <option value="Línea pasteles">Línea pasteles</option>
                                <option value="Línea cupcakes">Línea cupcakes</option>
                                <option value="Línea galletas">Línea galletas</option>
                                <option value="Línea postres">Línea postres</option>
                                <option value="Línea general">Línea general</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn">Registrar producción</button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert warning">
                Tu usuario solo puede consultar producción. Para registrar producción se requiere permiso de registro.
            </div>
        <?php endif; ?>

        <div class="table-wrap" style="margin-top: 24px;">
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
                        <tr>
                            <td colspan="5">Aún no hay registros de producción.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="grid two">
    <div class="card small">
        <h3>Productos más fabricados</h3>

        <?php if ($summary): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Total producido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary as $item): ?>
                            <tr>
                                <td><?= h($item['producto']) ?></td>
                                <td><?= h((string) $item['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Aún no hay datos para generar resumen.</p>
        <?php endif; ?>
    </div>

    <div class="card small">
        <h3>Nota de inventario</h3>
        <p>
            Si el producto tiene relación en la tabla <strong>producto_materias_primas</strong>,
            el sistema descuenta automáticamente el consumo de materia prima y registra el movimiento.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>