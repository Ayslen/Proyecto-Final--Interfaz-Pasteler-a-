<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requireRole(['admin']);

$user = Auth::user();
$users = User::all();
$totalUsers = count($users);
$totalAdmins = count(array_filter($users, fn($u) => $u['role_name'] === 'admin'));
$totalNormalUsers = count(array_filter($users, fn($u) => $u['role_name'] === 'user'));

$modules = Module::all();
$publicModules = array_filter($modules, fn($m) => (int) $m['admin_only'] === 0);

// MAGIA FRONTEND: Intento seguro de obtener datos reales para la gráfica.
// Si aún no hay registros, cargará datos de muestra para que el diseño no se rompa.
$chartLabels = "['Pastel Chocolate', 'Cupcakes Vainilla', 'Pay de Limón', 'Galletas', 'Cheesecake']";
$chartData = "";

try {
    $pdo = Database::pdo();
    $topProductos = $pdo->query("SELECT p.nombre, SUM(pd.cantidad) as total FROM produccion_diaria pd INNER JOIN productos p ON p.id = pd.producto_id GROUP BY p.id ORDER BY total DESC LIMIT 5")->fetchAll();
    
    if (count($topProductos) > 0) {
        $nombres = [];
        $totales = [];
        foreach ($topProductos as $tp) {
            $nombres[] = $tp['nombre'];
            $totales[] = $tp['total'];
        }
        $chartLabels = json_encode($nombres);
        $chartData = json_encode($totales);
    }
} catch (Throwable $e) {
    // Si la tabla no existe o hay error, usamos los datos de demostración.
}

require_once __DIR__ . '/../partials/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<section class="hero" style="padding-bottom: 20px;">
    <div class="card highlight" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div>
            <span class="badge">Panel de Control</span>
            <h1 style="font-size: 38px; margin-top: 14px; color: var(--primary-dark);">Bienvenido, <?= h($user['name']) ?> 👋</h1>
            <p style="color: var(--text); max-width: 600px; font-size: 16px; line-height: 1.6;">
                Resumen general de la pastelería. Desde aquí tienes control total sobre inventarios, producción, usuarios y la apariencia visual del sistema.
            </p>
        </div>
        <div style="font-size: 60px; opacity: 0.8;">🎂</div>
    </div>

    <div class="grid">
        <div class="stat card" style="text-align: center;">
            <strong style="font-size: 32px;"><?= h((string) $totalUsers) ?></strong>
            <span class="muted">Usuarios Totales</span>
        </div>
        <div class="stat card" style="text-align: center;">
            <strong style="font-size: 32px; color: var(--secondary-dark);"><?= h((string) $totalAdmins) ?></strong>
            <span class="muted">Administradores</span>
        </div>
        <div class="stat card" style="text-align: center;">
            <strong style="font-size: 32px; color: var(--success);"><?= h((string) $totalNormalUsers) ?></strong>
            <span class="muted">Empleados (Users)</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 24px;">
        
        <div class="card">
            <h3 style="margin-bottom: 20px;">📈 Top 5: Productos Destacados</h3>
            <canvas id="ventasChart" height="120"></canvas>
        </div>

        <div class="card" style="display: flex; flex-direction: column; gap: 15px;">
            <h3>⚡ Acciones Rápidas</h3>
            <a class="btn" href="<?= h(url('admin/users.php')) ?>" style="width: 100%; justify-content: flex-start;">
                👥 Administrar Accesos
            </a>
            <a class="btn secondary" href="<?= h(url('admin/user_create.php')) ?>" style="width: 100%; justify-content: flex-start;">
                ➕ Nuevo Empleado
            </a>
            <a class="btn secondary" href="<?= h(url('admin/appearance.php')) ?>" style="width: 100%; justify-content: flex-start;">
                🎨 Personalizar Colores
            </a>
        </div>
    </div>
</section>

<section class="grid two" style="padding-bottom: 40px;">
    <div class="card small">
        <h3>🧩 Sistema de módulos</h3>
        <p class="muted">El Admin puede activar permisos de Ver, Registrar, Editar, Eliminar y Administrar por cada usuario en las siguientes áreas:</p>
        <div class="permission-summary" style="margin-top: 15px;">
            <?php foreach ($publicModules as $module): ?>
                <span class="permission-pill"><?= h($module['icon']) ?> <?= h($module['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card small">
        <h3>🎨 Apariencia Inteligente</h3>
        <p class="muted">Los colores primario y secundario se guardan en la base de datos. El sistema calcula automáticamente el color del texto para mantener un contraste perfecto y las gráficas se adaptan al instante a tu paleta de colores.</p>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('ventasChart').getContext('2d');

        // Leer los colores dinámicos del CSS de tu equipo
        const rootStyles = getComputedStyle(document.documentElement);
        const colorPrimary = rootStyles.getPropertyValue('--primary').trim() || '#8b4513';
        const colorSecondary = rootStyles.getPropertyValue('--secondary').trim() || '#f2b705';
        const colorPrimarySoft = rootStyles.getPropertyValue('--primary-soft').trim() || '#b8703e';
        const colorMuted = rootStyles.getPropertyValue('--muted').trim() || '#7b6b5d';

        // Crear la gráfica animada
        new Chart(ctx, {
            type: 'bar', // Puedes cambiar 'bar' por 'doughnut' si prefieres un círculo
            data: {
                labels: <?= $chartLabels ?>,
                datasets: [{
                    label: 'Unidades Registradas',
                    data: <?= $chartData ?>,
                    backgroundColor: [
                        colorPrimary,
                        colorSecondary,
                        colorPrimarySoft,
                        colorMuted,
                        '#ead9c5' // Color neutro para el quinto lugar
                    ],
                    borderWidth: 0,
                    borderRadius: 8, // Bordes redondeados modernos
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false // Ocultamos la leyenda para diseño más limpio
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#000',
                        bodyColor: '#000',
                        borderColor: colorPrimary,
                        borderWidth: 1,
                        padding: 12
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                },
                animation: {
                    duration: 1500, // Duración de la animación inicial
                    easing: 'easeOutQuart'
                }
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>