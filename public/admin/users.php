<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireRole(['admin']);
$users = User::all();
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap;">
            <div>
                <span class="badge">Admin</span>
                <h1 style="font-size: 34px; margin-top: 14px;">Usuarios, roles y permisos</h1>
                <p class="muted">Lista de usuarios registrados. Desde Editar puedes asignar rol y marcar qué módulos ve o qué acciones puede realizar.</p>
            </div>
            <a class="btn" href="<?= h(url('admin/user_create.php')) ?>">Crear usuario</a>
        </div>

        <div class="table-wrap" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= h((string) $u['id']) ?></td>
                            <td><?= h($u['name']) ?></td>
                            <td><?= h($u['username']) ?></td>
                            <td><?= h($u['email']) ?></td>
                            <td><span class="role-pill <?= $u['role_name'] === 'admin' ? 'admin' : '' ?>"><?= h($u['role_display_name']) ?></span></td>
                            <td><?= ((int) $u['is_active'] === 1) ? 'Activo' : 'Inactivo' ?></td>
                            <td><?= h($u['created_at']) ?></td>
                            <td>
                                <a class="btn secondary" href="<?= h(url('admin/user_edit.php?id=' . $u['id'])) ?>">Editar permisos</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
