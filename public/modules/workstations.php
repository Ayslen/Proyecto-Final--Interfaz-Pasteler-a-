<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requirePermission('workstations', 'view');

if (is_post()) {
    if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
        Flash::set('danger', 'La sesión expiró. Intenta de nuevo.');
        redirect('modules/workstations.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'sync_current') {
        $result = WorkstationStatus::syncCurrent(Auth::id());
        Flash::set($result['ok'] ? 'success' : 'danger', $result['message']);
        redirect('modules/workstations.php');
    }

    if ($action === 'rename_current') {
        $ok = WorkstationStatus::updateCurrentDisplayName((string) ($_POST['display_name'] ?? ''));
        Flash::set($ok ? 'success' : 'danger', $ok ? 'Nombre del equipo actualizado.' : 'Escribe un nombre válido de máximo 120 caracteres.');
        redirect('modules/workstations.php');
    }
}

$devices = WorkstationStatus::all();
$current = WorkstationStatus::currentRow();
$dbInfo = Database::connectionSummary();
$host = (string) $dbInfo['host'];
$isLocalHost = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <span class="badge">Módulo de administración asignable</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Estado de PCs y base central</h1>
        <p class="muted">
            Este módulo muestra los equipos o navegadores que han abierto el sistema y confirma si pudieron escribir en la misma base de datos central.
        </p>

        <div class="alert <?= $isLocalHost ? 'warning' : 'success' ?>">
            <?php if ($isLocalHost): ?>
                La base actual apunta a <strong><?= h($host) ?></strong>. Eso funciona en tu PC, pero tus colaboradores deberán usar tu IP pública, dominio DDNS o red VPN para conectarse a tu base.
            <?php else: ?>
                La base está configurada hacia un host compartible: <strong><?= h($host) ?>:<?= h((string) $dbInfo['port']) ?></strong>.
            <?php endif; ?>
        </div>

        <div class="grid four">
            <div class="stat"><strong><?= h($dbInfo['host']) ?></strong><span>Host de base de datos</span></div>
            <div class="stat"><strong><?= h((string) $dbInfo['port']) ?></strong><span>Puerto MySQL</span></div>
            <div class="stat"><strong><?= h($dbInfo['database']) ?></strong><span>Base de datos</span></div>
            <div class="stat"><strong><?= count($devices) ?></strong><span>Equipos registrados</span></div>
        </div>

        <div class="grid two">
            <form class="card small" method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="sync_current">
                <h3>Sincronizar este equipo</h3>
                <p class="muted">
                    La sincronización hace una prueba real contra MySQL y actualiza la hora de este equipo en la base central.
                </p>
                <button class="btn" type="submit">🔄 Sincronizar ahora</button>
            </form>

            <form class="card small" method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="rename_current">
                <h3>Nombre visible de esta PC</h3>
                <div class="form-group">
                    <label for="display_name">Nombre para reconocerla</label>
                    <input id="display_name" name="display_name" maxlength="120" value="<?= h($current['display_name'] ?? '') ?>" placeholder="Ej. PC de Flavio, Laptop de Alumno 2">
                </div>
                <button class="btn secondary" type="submit">Guardar nombre</button>
            </form>
        </div>
    </div>
</section>

<section>
    <div class="card">
        <h2>Equipos que han abierto el sistema</h2>
        <p class="muted">
            Nota lógica: desde una página web no se puede forzar que otra PC haga pull de Git o sincronice archivos locales. Lo que sí se puede confirmar es que esa PC abrió el sistema y escribió su estado en la base central.
        </p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th>Usuario</th>
                        <th>Actividad</th>
                        <th>Estado BD</th>
                        <th>Último visto</th>
                        <th>Último sync manual</th>
                        <th>Datos técnicos</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <?php $isCurrent = WorkstationStatus::isCurrent($device); ?>
                        <tr>
                            <td>
                                <strong><?= h($device['display_name'] ?: 'Equipo sin nombre') ?></strong>
                                <?php if ($isCurrent): ?>
                                    <span class="status-pill online">Este equipo</span>
                                <?php endif; ?>
                                <br>
                                <small class="muted">Host PHP: <?= h($device['server_hostname'] ?: 'No detectado') ?></small>
                            </td>
                            <td>
                                <?= h($device['last_user_name'] ?: 'Sin sesión') ?><br>
                                <small class="muted"><?= h($device['last_user_email'] ?: '') ?></small>
                            </td>
                            <td>
                                <?php $activityClass = $device['activity_status'] === 'En línea' ? 'online' : ($device['activity_status'] === 'Reciente' ? 'recent' : 'offline'); ?>
                                <span class="status-pill <?= h($activityClass) ?>"><?= h($device['activity_status']) ?></span><br>
                                <small class="muted">Aperturas: <?= h((string) $device['opened_count']) ?></small>
                            </td>
                            <td>
                                <?php $syncClass = $device['sync_status'] === 'sincronizado' ? 'online' : ($device['sync_status'] === 'error' ? 'error' : 'pending'); ?>
                                <span class="status-pill <?= h($syncClass) ?>"><?= h($device['sync_status']) ?></span><br>
                                <small class="muted"><?= h($device['sync_message'] ?: 'Sin mensaje') ?></small>
                            </td>
                            <td><?= h((string) $device['last_seen_at']) ?></td>
                            <td><?= h((string) ($device['last_synced_at'] ?: 'Sin sync manual')) ?></td>
                            <td>
                                <small>
                                    IP navegador: <?= h($device['remote_ip'] ?: 'N/D') ?><br>
                                    Servidor: <?= h($device['server_addr'] ?: 'N/D') ?><br>
                                    App: <?= h($device['app_path'] ?: 'N/D') ?><br>
                                    BD: <?= h(($device['db_host'] ?: 'N/D') . ' / ' . ($device['db_name'] ?: 'N/D')) ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($isCurrent): ?>
                                    <form method="post">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="sync_current">
                                        <button class="btn secondary" type="submit">Sincronizar</button>
                                    </form>
                                <?php else: ?>
                                    <small class="muted">Debe sincronizarse desde esa PC al abrir el sistema.</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($devices === []): ?>
                        <tr><td colspan="8">Todavía no hay equipos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
