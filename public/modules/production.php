<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requirePermission('production', 'view');

$pdo = Database::pdo();
$canCreateProduction = Auth::can('production', 'create') || Auth::can('production', 'manage');

function descontarInventarioPorProduccion(PDO $pdo, int $productoId, int $cantidadProducida): int
{
    $stmt = $pdo->prepare('SELECT pmp.materia_prima_id, mp.nombre AS materia_prima, pmp.cantidad_por_unidad, (pmp.cantidad_por_unidad * :cantidad) AS consumo_total FROM producto_materias_primas pmp INNER JOIN materias_primas mp ON mp.id = pmp.materia_prima_id WHERE pmp.producto_id = :producto_id');

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

        $update = $pdo->prepare('UPDATE materias_primas SET stock_actual = GREATEST(stock_actual - :consumo, 0) WHERE id = :materia_prima_id');

        $update->execute([
            'consumo' => $consumoTotal,
            'materia_prima_id' => (int) $receta['materia_prima_id'],
        ]);

        $movement = $pdo->prepare('INSERT INTO movimientos_inventario (materia_prima_id, tipo, cantidad, descripcion, created_by) VALUES (:materia_prima_id, \'salida\', :cantidad, :descripcion, :created_by)');

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

        $stmt = $pdo->prepare('INSERT INTO produccion_diaria (fecha, producto_id, cantidad, linea, created_by) VALUES (:fecha, :producto_id, :cantidad, :linea, :created_by)');

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

$products = $pdo->query('SELECT id, nombre, categoria FROM productos WHERE activo = 1 ORDER BY categoria ASC, nombre ASC')->fetchAll();

$records = $pdo->query('SELECT pd.fecha, pd.cantidad, pd.linea, p.nombre AS producto, u.name AS usuario FROM produccion_diaria pd INNER JOIN productos p ON p.id = pd.producto_id LEFT JOIN users u ON u.id = pd.created_by ORDER BY pd.fecha DESC, pd.id DESC LIMIT 40')->fetchAll();

$summary = $pdo->query('SELECT p.nombre AS producto, SUM(pd.cantidad) AS total FROM produccion_diaria pd INNER JOIN productos p ON p.id = pd.producto_id GROUP BY p.id, p.nombre ORDER BY total DESC LIMIT 5')->fetchAll();

require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero" style="padding-bottom: 0;">
    <div class="card" style="margin-bottom: 24px;">
        <span class="badge">Módulo</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Registro de producción</h1>
        <p class="muted">
            Registro diario de producción de pasteles, cupcakes, galletas y postres. Al guardar la producción, el sistema descuenta automáticamente la materia prima correspondiente a la receta.
        </p>

        <div class="permission-summary" style="margin-top: 15px;">
            <span class="permission-pill">Ver: sí</span>
            <span class="permission-pill">Registrar: <?= Auth::can('production', 'create') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Editar: <?= Auth::can('production', 'edit') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Eliminar: <?= Auth::can('production', 'delete') ? 'sí' : 'no' ?></span>
            <span class="permission-pill">Administrar: <?= Auth::can('production', 'manage') ? 'sí' : 'no' ?></span>
        </div>
    </div>

    <?php if ($canCreateProduction): ?>
        <div class="card highlight" style="margin-bottom: 24px;">
            <h3 style="margin-bottom: 20px;">🥣 Registrar producción diaria</h3>

            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="form_type" value="produccion">

                <div class="grid four">
                    <div class="form-group">
                        <label for="fecha">Fecha de lote</label>
                        <input type="date" id="fecha" name="fecha" required value="<?= h(date('Y-m-d')) ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label for="producto_id">Producto fabricado</label>
                        <select id="producto_id" name="producto_id" required>
                            <option value="">Selecciona un producto del menú...</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= h((string) $product['id']) ?>">
                                    <?= h($product['nombre']) ?> [<?= h($product['categoria']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cantidad">Cantidad total</label>
                        <input type="number" id="cantidad" name="cantidad" min="1" required placeholder="Ej. 25">
                    </div>
                </div>

                <div class="grid two" style="align-items: end; margin-top: 5px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="linea">Línea u Horno asignado</label>
                        <select id="linea" name="linea" required>
                            <option value="Línea pasteles">Línea pasteles</option>
                            <option value="Línea cupcakes">Línea cupcakes</option>
                            <option value="Línea galletas">Línea galletas</option>
                            <option value="Línea postres">Línea postres</option>
                            <option value="Línea general">Línea general</option>
                        </select>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="btn" style="padding: 12px 24px;">
                            INGRESAR A PRODUCCIÓN
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="alert warning">
            Tu usuario solo puede consultar producción. Para registrar producción se requiere permiso de registro.
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 24px;">
        <h3>📋 Historial de Lotes Fabricados</h3>
        <div class="table-wrap" style="margin-top: 15px;">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Línea</th>
                        <th>Operador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td style="font-weight: bold; color: var(--muted);"><?= h($record['fecha']) ?></td>
                            <td style="font-weight: bold; color: var(--primary-dark);"><?= h($record['producto']) ?></td>
                            <td><span class="role-pill" style="padding: 3px 8px; font-size: 13px;"><?= h((string) $record['cantidad']) ?> pz</span></td>
                            <td><?= h($record['linea']) ?></td>
                            <td><?= h($record['usuario'] ?? 'Sistema') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$records): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px;">Aún no hay registros de producción en esta jornada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="grid two" style="padding-bottom: 40px;">
    <div class="card small">
        <h3>🏆 Top 5: Más Fabricados</h3>
        <?php if ($summary): ?>
            <div class="table-wrap" style="margin-top: 10px;">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Total Producido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary as $item): ?>
                            <tr>
                                <td style="font-weight: bold; color: var(--primary-dark);"><?= h($item['producto']) ?></td>
                                <td><strong><?= h((string) $item['total']) ?> unidades</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Aún no hay datos para generar resumen.</p>
        <?php endif; ?>
    </div>

    <div class="card small" style="display: flex; flex-direction: column; justify-content: center;">
        <h3>⚙️ Descuento Automatizado</h3>
        <p class="muted" style="font-size: 14px; line-height: 1.5;">
            Si el postre cuenta con una receta en la tabla <strong>producto_materias_primas</strong>, la cocina descontará la cantidad exacta de harina, azúcar y huevo al momento, generando un reporte inmediato en el módulo de almacén.
        </p>
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