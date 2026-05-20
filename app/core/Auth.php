<?php

class Auth
{
    public static function attempt(string $identifier, string $password): bool
    {
        $identifier = trim($identifier);
        $user = User::findByIdentifier($identifier);

        if (!$user || !(bool) $user['is_active']) {
            Database::logLogin($user['id'] ?? null, $identifier, false);
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            Database::logLogin((int) $user['id'], $identifier, false);
            return false;
        }

        session_regenerate_id(true);
        self::storeUserInSession($user);

        Database::logLogin((int) $user['id'], $identifier, true);
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function can(string $moduleKey, string $action = 'view'): bool
    {
        if (!self::check()) {
            return false;
        }

        return Module::hasPermission((int) self::id(), (string) self::role(), $moduleKey, $action);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Flash::set('warning', 'Primero inicia sesión para entrar al sistema.');
            redirect('login.php');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        if (!in_array(self::role(), $roles, true)) {
            Flash::set('danger', 'No tienes permiso para entrar a esa sección.');
            redirect('403.php');
        }
    }

    public static function requirePermission(string $moduleKey, string $action = 'view'): void
    {
        self::requireLogin();

        if (!self::can($moduleKey, $action)) {
            Flash::set('danger', 'No tienes permiso para realizar esa acción.');
            redirect('403.php');
        }
    }

    public static function redirectByRole(): never
    {
        self::requireLogin();

        if (self::role() === 'admin') {
            redirect('admin/dashboard.php');
        }

        redirect('user/dashboard.php');
    }

    public static function refreshSessionUser(int $userId): void
    {
        $user = User::findById($userId);
        if ($user) {
            self::storeUserInSession($user);
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    private static function storeUserInSession(array $user): void
    {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role_name'],
            'role_display' => $user['role_display_name'],
        ];
    }
}
