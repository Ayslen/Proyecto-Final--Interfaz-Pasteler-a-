<?php
require_once __DIR__ . '/../app/bootstrap.php';

$config = Database::config();
$pdo = Database::pdo();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Prueba de conexión</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Base de datos lista</h1>
        <p>La conexión a MySQL funciona y las tablas iniciales fueron verificadas.</p>
        <div class="grid two">
            <div class="stat"><strong><?= h($config['host']) ?></strong><span>Host configurado</span></div>
            <div class="stat"><strong><?= h((string) $config['database']) ?></strong><span>Base de datos</span></div>
        </div>
        <h3>Tablas detectadas</h3>
        <ul>
            <?php foreach ($tables as $table): ?>
                <li><?= h($table) ?></li>
            <?php endforeach; ?>
        </ul>
        <a class="btn" href="<?= h(url('login.php')) ?>">Ir al login</a>
    </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
