<?php
/** @var array $modules */
/** @var array $permissions */
$actions = Module::actions();
?>
<div class="permission-matrix" data-permission-matrix>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Módulo</th>
                    <?php foreach ($actions as $label): ?>
                        <th><?= h($label) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module): ?>
                    <?php
                    $key = $module['module_key'];
                    $modulePermissions = $permissions[$key] ?? [];
                    $isAdminOnly = (int) $module['admin_only'] === 1;
                    ?>
                    <tr data-admin-only="<?= $isAdminOnly ? '1' : '0' ?>">
                        <td>
                            <div class="module-title">
                                <span><?= h($module['icon']) ?></span>
                                <div>
                                    <strong><?= h($module['name']) ?></strong>
                                    <small><?= h($module['description']) ?></small>
                                    <?php if ($isAdminOnly): ?>
                                        <span class="admin-only-note">Solo Admin</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php foreach ($actions as $action => $label): ?>
                            <td>
                                <input
                                    type="checkbox"
                                    name="permissions[<?= h($key) ?>][<?= h($action) ?>]"
                                    value="1"
                                    <?= !empty($modulePermissions[$action]) ? 'checked' : '' ?>
                                    aria-label="<?= h($label . ' ' . $module['name']) ?>"
                                >
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted">
        Si marcas Registrar, Editar, Eliminar o Administrar, el sistema activa automáticamente el permiso de Ver. Los módulos marcados como Solo Admin no se pueden asignar a usuarios normales.
    </p>
</div>
