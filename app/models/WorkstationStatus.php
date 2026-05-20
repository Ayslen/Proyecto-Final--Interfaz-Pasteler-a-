<?php

class WorkstationStatus
{
    private const COOKIE_NAME = 'pm_device_token';

    public static function currentToken(): string
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';

        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = hash('sha256', bin2hex(random_bytes(32)) . microtime(true));
            setcookie(self::COOKIE_NAME, $token, [
                'expires' => time() + (60 * 60 * 24 * 365),
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[self::COOKIE_NAME] = $token;
        }

        return $token;
    }

    public static function touchCurrent(?int $userId = null): void
    {
        $token = self::currentToken();
        $data = self::currentData($userId);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(<<<SQL
INSERT INTO workstation_status
    (device_token, display_name, server_hostname, server_software, server_addr, remote_ip, user_agent,
     php_version, app_path, db_host, db_name, last_user_id, first_seen_at, last_seen_at,
     sync_status, sync_message, opened_count)
VALUES
    (:device_token, :display_name, :server_hostname, :server_software, :server_addr, :remote_ip, :user_agent,
     :php_version, :app_path, :db_host, :db_name, :last_user_id, NOW(), NOW(),
     'sincronizado', :sync_message, 1)
ON DUPLICATE KEY UPDATE
    server_hostname = VALUES(server_hostname),
    server_software = VALUES(server_software),
    server_addr = VALUES(server_addr),
    remote_ip = VALUES(remote_ip),
    user_agent = VALUES(user_agent),
    php_version = VALUES(php_version),
    app_path = VALUES(app_path),
    db_host = VALUES(db_host),
    db_name = VALUES(db_name),
    last_user_id = VALUES(last_user_id),
    last_seen_at = NOW(),
    sync_status = 'sincronizado',
    sync_message = VALUES(sync_message),
    opened_count = opened_count + 1
SQL);

        $stmt->execute([
            'device_token' => $token,
            'display_name' => $data['display_name'],
            'server_hostname' => $data['server_hostname'],
            'server_software' => $data['server_software'],
            'server_addr' => $data['server_addr'],
            'remote_ip' => $data['remote_ip'],
            'user_agent' => $data['user_agent'],
            'php_version' => $data['php_version'],
            'app_path' => $data['app_path'],
            'db_host' => $data['db_host'],
            'db_name' => $data['db_name'],
            'last_user_id' => $userId,
            'sync_message' => 'Se pudo escribir el estado en la base de datos central.',
        ]);
    }

    public static function syncCurrent(?int $userId = null): array
    {
        $token = self::currentToken();
        $data = self::currentData($userId);
        $pdo = Database::pdo();

        try {
            $pdo->query('SELECT 1')->fetchColumn();
            $status = 'sincronizado';
            $message = 'Sincronización manual correcta. Este equipo sí está conectado a la base de datos central.';
        } catch (Throwable $e) {
            $status = 'error';
            $message = 'No se pudo confirmar la conexión: ' . $e->getMessage();
        }

        $stmt = $pdo->prepare(<<<SQL
INSERT INTO workstation_status
    (device_token, display_name, server_hostname, server_software, server_addr, remote_ip, user_agent,
     php_version, app_path, db_host, db_name, last_user_id, first_seen_at, last_seen_at,
     last_synced_at, sync_status, sync_message, opened_count)
VALUES
    (:device_token, :display_name, :server_hostname, :server_software, :server_addr, :remote_ip, :user_agent,
     :php_version, :app_path, :db_host, :db_name, :last_user_id, NOW(), NOW(),
     NOW(), :sync_status, :sync_message, 1)
ON DUPLICATE KEY UPDATE
    server_hostname = VALUES(server_hostname),
    server_software = VALUES(server_software),
    server_addr = VALUES(server_addr),
    remote_ip = VALUES(remote_ip),
    user_agent = VALUES(user_agent),
    php_version = VALUES(php_version),
    app_path = VALUES(app_path),
    db_host = VALUES(db_host),
    db_name = VALUES(db_name),
    last_user_id = VALUES(last_user_id),
    last_seen_at = NOW(),
    last_synced_at = NOW(),
    sync_status = VALUES(sync_status),
    sync_message = VALUES(sync_message),
    opened_count = opened_count + 1
SQL);

        $stmt->execute([
            'device_token' => $token,
            'display_name' => $data['display_name'],
            'server_hostname' => $data['server_hostname'],
            'server_software' => $data['server_software'],
            'server_addr' => $data['server_addr'],
            'remote_ip' => $data['remote_ip'],
            'user_agent' => $data['user_agent'],
            'php_version' => $data['php_version'],
            'app_path' => $data['app_path'],
            'db_host' => $data['db_host'],
            'db_name' => $data['db_name'],
            'last_user_id' => $userId,
            'sync_status' => $status,
            'sync_message' => $message,
        ]);

        return ['ok' => $status === 'sincronizado', 'message' => $message];
    }

    public static function updateCurrentDisplayName(string $displayName): bool
    {
        $displayName = trim($displayName);
        if ($displayName === '' || mb_strlen($displayName) > 120) {
            return false;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE workstation_status SET display_name = :display_name WHERE device_token = :device_token');
        $stmt->execute([
            'display_name' => $displayName,
            'device_token' => self::currentToken(),
        ]);

        return true;
    }

    public static function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(<<<SQL
SELECT ws.*, u.name AS last_user_name, u.email AS last_user_email,
       TIMESTAMPDIFF(MINUTE, ws.last_seen_at, NOW()) AS minutes_since_seen,
       CASE
           WHEN TIMESTAMPDIFF(MINUTE, ws.last_seen_at, NOW()) <= 5 THEN 'En línea'
           WHEN TIMESTAMPDIFF(MINUTE, ws.last_seen_at, NOW()) <= 60 THEN 'Reciente'
           ELSE 'Sin actividad'
       END AS activity_status
FROM workstation_status ws
LEFT JOIN users u ON u.id = ws.last_user_id
ORDER BY ws.last_seen_at DESC, ws.id DESC
SQL);

        return $stmt->fetchAll();
    }

    public static function currentRow(): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM workstation_status WHERE device_token = :device_token LIMIT 1');
        $stmt->execute(['device_token' => self::currentToken()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function isCurrent(array $row): bool
    {
        return hash_equals((string) ($row['device_token'] ?? ''), self::currentToken());
    }

    private static function currentData(?int $userId): array
    {
        $summary = Database::connectionSummary();
        $host = gethostname() ?: php_uname('n') ?: 'Equipo sin nombre';

        return [
            'display_name' => 'Equipo ' . $host,
            'server_hostname' => substr((string) $host, 0, 120),
            'server_software' => substr((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'PHP local'), 0, 180),
            'server_addr' => substr((string) ($_SERVER['SERVER_ADDR'] ?? ($_SERVER['SERVER_NAME'] ?? '')), 0, 80),
            'remote_ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 80),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'php_version' => substr(PHP_VERSION, 0, 60),
            'app_path' => substr((string) realpath(__DIR__ . '/../../'), 0, 255),
            'db_host' => substr($summary['host'] . ':' . $summary['port'], 0, 150),
            'db_name' => substr($summary['database'], 0, 100),
            'last_user_id' => $userId,
        ];
    }
}
