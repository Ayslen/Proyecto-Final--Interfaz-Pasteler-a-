<?php

class User
{
    public static function findByIdentifier(string $identifier): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(<<<SQL
SELECT users.*, roles.name AS role_name, roles.display_name AS role_display_name
FROM users
INNER JOIN roles ON roles.id = users.role_id
WHERE users.email = :email OR users.username = :username
LIMIT 1
SQL);
        $stmt->execute([
            'email' => $identifier,
            'username' => $identifier,
        ]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(<<<SQL
SELECT users.*, roles.name AS role_name, roles.display_name AS role_display_name
FROM users
INNER JOIN roles ON roles.id = users.role_id
WHERE users.id = :id
LIMIT 1
SQL);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function usernameOrEmailExists(string $username, string $email, ?int $excludeId = null): bool
    {
        $pdo = Database::pdo();

        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND id <> :id');
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'id' => $excludeId,
            ]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
            $stmt->execute([
                'username' => $username,
                'email' => $email,
            ]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(array $data): array
    {
        $validated = self::validateUserData($data, false);
        if (!$validated['ok']) {
            return $validated;
        }

        $clean = $validated['data'];

        if (self::usernameOrEmailExists($clean['username'], $clean['email'])) {
            return [
                'ok' => false,
                'errors' => [
                    'general' => 'Ya existe un usuario con ese nombre de usuario o correo.',
                ],
            ];
        }

        $pdo = Database::pdo();
        $roleId = self::roleIdByName($clean['role']);

        if ($roleId === null) {
            return ['ok' => false, 'errors' => ['role' => 'No se encontró el rol en la base de datos.']];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(<<<SQL
INSERT INTO users (role_id, name, username, email, password_hash, is_active)
VALUES (:role_id, :name, :username, :email, :password_hash, 1)
SQL);
            $stmt->execute([
                'role_id' => $roleId,
                'name' => $clean['name'],
                'username' => $clean['username'],
                'email' => $clean['email'],
                'password_hash' => password_hash($clean['password'], PASSWORD_DEFAULT),
            ]);

            $userId = (int) $pdo->lastInsertId();
            $permissions = $data['permissions'] ?? Module::defaultPermissionsForRole($clean['role']);
            Module::saveUserPermissions($userId, $clean['role'], $permissions);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                return [
                    'ok' => false,
                    'errors' => ['general' => 'El usuario o correo ya está registrado.'],
                ];
            }
            throw $e;
        }

        return [
            'ok' => true,
            'user_id' => $userId,
        ];
    }

    public static function update(int $id, array $data): array
    {
        $current = self::findById($id);
        if (!$current) {
            return ['ok' => false, 'errors' => ['general' => 'El usuario no existe.']];
        }

        $validated = self::validateUserData($data, true);
        if (!$validated['ok']) {
            return $validated;
        }

        $clean = $validated['data'];
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $protectSelf = !empty($data['protect_self']);

        if ($protectSelf) {
            $clean['role'] = 'admin';
            $isActive = 1;
        }

        if (self::usernameOrEmailExists($clean['username'], $clean['email'], $id)) {
            return [
                'ok' => false,
                'errors' => [
                    'general' => 'Ya existe otro usuario con ese nombre de usuario o correo.',
                ],
            ];
        }

        $roleId = self::roleIdByName($clean['role']);
        if ($roleId === null) {
            return ['ok' => false, 'errors' => ['role' => 'No se encontró el rol en la base de datos.']];
        }

        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $params = [
                'id' => $id,
                'role_id' => $roleId,
                'name' => $clean['name'],
                'username' => $clean['username'],
                'email' => $clean['email'],
                'is_active' => $isActive,
            ];

            $passwordSql = '';
            if ($clean['password'] !== '') {
                $passwordSql = ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($clean['password'], PASSWORD_DEFAULT);
            }

            $stmt = $pdo->prepare(<<<SQL
UPDATE users
SET role_id = :role_id,
    name = :name,
    username = :username,
    email = :email,
    is_active = :is_active
    {$passwordSql}
WHERE id = :id
SQL);
            $stmt->execute($params);

            $permissions = $data['permissions'] ?? Module::defaultPermissionsForRole($clean['role']);
            Module::saveUserPermissions($id, $clean['role'], $permissions);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                return [
                    'ok' => false,
                    'errors' => ['general' => 'El usuario o correo ya está registrado.'],
                ];
            }
            throw $e;
        }

        return ['ok' => true, 'user_id' => $id];
    }

    public static function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(<<<SQL
SELECT users.id, users.name, users.username, users.email, users.is_active,
       users.created_at, roles.display_name AS role_display_name, roles.name AS role_name
FROM users
INNER JOIN roles ON roles.id = users.role_id
ORDER BY users.created_at DESC
SQL);

        return $stmt->fetchAll();
    }

    public static function roles(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT id, name, display_name FROM roles ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    private static function validateUserData(array $data, bool $passwordOptional): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $roleName = strtolower(trim((string) ($data['role'] ?? 'user')));

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) {
            $errors['username'] = 'El usuario debe tener mínimo 3 caracteres y solo puede usar letras, números, guion, punto o guion bajo.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El correo no tiene un formato válido.';
        }

        if ((!$passwordOptional || $password !== '') && strlen($password) < 8) {
            $errors['password'] = 'La contraseña debe tener mínimo 8 caracteres.';
        }

        if (!in_array($roleName, ['admin', 'user'], true)) {
            $errors['role'] = 'El rol seleccionado no es válido.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        return [
            'ok' => true,
            'data' => [
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $roleName,
            ],
        ];
    }

    private static function roleIdByName(string $roleName): ?int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $roleName]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}
