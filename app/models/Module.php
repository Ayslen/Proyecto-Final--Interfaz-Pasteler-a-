<?php

class Module
{
    public const ACTIONS = [
        'view' => 'Ver',
        'create' => 'Registrar',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'manage' => 'Administrar',
    ];

    public const ACTION_COLUMNS = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'manage' => 'can_manage',
    ];

    public static function all(bool $onlyActive = true): array
    {
        $pdo = Database::pdo();
        $where = $onlyActive ? 'WHERE is_active = 1' : '';
        $stmt = $pdo->query("SELECT * FROM modules {$where} ORDER BY sort_order ASC, id ASC");
        return $stmt->fetchAll();
    }

    public static function findByKey(string $moduleKey): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM modules WHERE module_key = :module_key LIMIT 1');
        $stmt->execute(['module_key' => $moduleKey]);
        $module = $stmt->fetch();
        return $module ?: null;
    }

    public static function actions(): array
    {
        return self::ACTIONS;
    }

    public static function permissionsForUser(int $userId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(<<<SQL
SELECT m.module_key, p.can_view, p.can_create, p.can_edit, p.can_delete, p.can_manage
FROM modules m
LEFT JOIN user_module_permissions p
    ON p.module_id = m.id AND p.user_id = :user_id
ORDER BY m.sort_order ASC, m.id ASC
SQL);
        $stmt->execute(['user_id' => $userId]);

        $permissions = [];
        foreach ($stmt->fetchAll() as $row) {
            $permissions[$row['module_key']] = [
                'view' => (bool) ($row['can_view'] ?? false),
                'create' => (bool) ($row['can_create'] ?? false),
                'edit' => (bool) ($row['can_edit'] ?? false),
                'delete' => (bool) ($row['can_delete'] ?? false),
                'manage' => (bool) ($row['can_manage'] ?? false),
            ];
        }

        return $permissions;
    }

    public static function defaultPermissionsForRole(string $roleName): array
    {
        $roleName = strtolower($roleName);
        $permissions = [];

        foreach (self::all() as $module) {
            $key = $module['module_key'];
            $isAdminOnly = (int) $module['admin_only'] === 1;

            if ($roleName === 'admin') {
                $permissions[$key] = [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'manage' => true,
                ];
                continue;
            }

            $permissions[$key] = [
                'view' => !$isAdminOnly && $key !== 'workstations',
                'create' => !$isAdminOnly && in_array($key, ['production'], true),
                'edit' => false,
                'delete' => false,
                'manage' => false,
            ];
        }

        return $permissions;
    }

    public static function permissionsFromPost(array $postPermissions): array
    {
        $permissions = [];

        foreach (self::all() as $module) {
            $key = $module['module_key'];
            $permissions[$key] = [];

            foreach (array_keys(self::ACTIONS) as $action) {
                $permissions[$key][$action] = isset($postPermissions[$key][$action]);
            }
        }

        return $permissions;
    }

    public static function saveUserPermissions(int $userId, string $roleName, array $permissions): void
    {
        $roleName = strtolower($roleName);
        $pdo = Database::pdo();
        $modules = self::all(false);
        $stmt = $pdo->prepare(<<<SQL
INSERT INTO user_module_permissions
    (user_id, module_id, can_view, can_create, can_edit, can_delete, can_manage)
VALUES
    (:user_id, :module_id, :can_view, :can_create, :can_edit, :can_delete, :can_manage)
ON DUPLICATE KEY UPDATE
    can_view = VALUES(can_view),
    can_create = VALUES(can_create),
    can_edit = VALUES(can_edit),
    can_delete = VALUES(can_delete),
    can_manage = VALUES(can_manage)
SQL);

        foreach ($modules as $module) {
            $key = $module['module_key'];
            $isAdminOnly = (int) $module['admin_only'] === 1;
            $posted = $permissions[$key] ?? [];

            if ($roleName === 'admin') {
                $values = ['view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'manage' => true];
            } elseif ($isAdminOnly) {
                $values = ['view' => false, 'create' => false, 'edit' => false, 'delete' => false, 'manage' => false];
            } else {
                $values = [
                    'view' => (bool) ($posted['view'] ?? false),
                    'create' => (bool) ($posted['create'] ?? false),
                    'edit' => (bool) ($posted['edit'] ?? false),
                    'delete' => (bool) ($posted['delete'] ?? false),
                    'manage' => (bool) ($posted['manage'] ?? false),
                ];

                if ($values['create'] || $values['edit'] || $values['delete'] || $values['manage']) {
                    $values['view'] = true;
                }
            }

            $stmt->execute([
                'user_id' => $userId,
                'module_id' => (int) $module['id'],
                'can_view' => $values['view'] ? 1 : 0,
                'can_create' => $values['create'] ? 1 : 0,
                'can_edit' => $values['edit'] ? 1 : 0,
                'can_delete' => $values['delete'] ? 1 : 0,
                'can_manage' => $values['manage'] ? 1 : 0,
            ]);
        }
    }

    public static function hasPermission(int $userId, string $roleName, string $moduleKey, string $action = 'view'): bool
    {
        $roleName = strtolower($roleName);
        $action = strtolower($action);

        if ($roleName === 'admin') {
            return true;
        }

        if (!isset(self::ACTION_COLUMNS[$action])) {
            return false;
        }

        $column = self::ACTION_COLUMNS[$action];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(<<<SQL
SELECT COUNT(*)
FROM user_module_permissions p
INNER JOIN modules m ON m.id = p.module_id
WHERE p.user_id = :user_id
  AND m.module_key = :module_key
  AND m.is_active = 1
  AND m.admin_only = 0
  AND p.{$column} = 1
SQL);
        $stmt->execute([
            'user_id' => $userId,
            'module_key' => $moduleKey,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function visibleNavigationForUser(int $userId, string $roleName): array
    {
        $pdo = Database::pdo();

        if (strtolower($roleName) === 'admin') {
            $stmt = $pdo->query(<<<SQL
SELECT * FROM modules
WHERE is_active = 1
  AND admin_only = 0
  AND route LIKE 'modules/%'
ORDER BY sort_order ASC, id ASC
SQL);
            return $stmt->fetchAll();
        }

        $stmt = $pdo->prepare(<<<SQL
SELECT m.*
FROM modules m
INNER JOIN user_module_permissions p ON p.module_id = m.id
WHERE p.user_id = :user_id
  AND p.can_view = 1
  AND m.is_active = 1
  AND m.admin_only = 0
  AND m.route LIKE 'modules/%'
ORDER BY m.sort_order ASC, m.id ASC
SQL);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
