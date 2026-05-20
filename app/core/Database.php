<?php

class Database
{
    private static ?PDO $pdo = null;
    private static bool $migrated = false;

    public static function config(): array
    {
        return require __DIR__ . '/../config/database.php';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }

        $config = self::config();
        if (($config['auto_migrate'] ?? true) === true && self::$migrated === false) {
            self::migrate();
            self::$migrated = true;
        }

        return self::$pdo;
    }

    private static function connect(): void
    {
        $config = self::config();
        $charset = $config['charset'] ?? 'utf8mb4';
        $host = $config['host'];
        $port = (int) $config['port'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $serverDsn = "mysql:host={$host};port={$port};charset={$charset}";
            $serverPdo = new PDO($serverDsn, $username, $password, $options);
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            self::$pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage());
        }
    }

    public static function migrate(): void
    {
        $pdo = self::$pdo;

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    display_name VARCHAR(60) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    identifier VARCHAR(150) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_logs_users FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS materias_primas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    unidad VARCHAR(30) NOT NULL,
    stock_actual DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_minimo DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL UNIQUE,
    categoria ENUM('pastel','cupcake','galleta','postre') NOT NULL,
    precio DECIMAL(10,2) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS produccion_diaria (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    linea VARCHAR(80) NOT NULL DEFAULT 'Línea general',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produccion_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_produccion_usuario FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_produccion_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS modules (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    route VARCHAR(160) NOT NULL,
    icon VARCHAR(12) NOT NULL DEFAULT '📦',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    admin_only TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS user_module_permissions (
    user_id INT UNSIGNED NOT NULL,
    module_id SMALLINT UNSIGNED NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_manage TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, module_id),
    CONSTRAINT fk_permissions_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_permissions_module FOREIGN KEY (module_id) REFERENCES modules(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS workstation_status (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_token CHAR(64) NOT NULL UNIQUE,
    display_name VARCHAR(120) NULL,
    server_hostname VARCHAR(120) NULL,
    server_software VARCHAR(180) NULL,
    server_addr VARCHAR(80) NULL,
    remote_ip VARCHAR(80) NULL,
    user_agent VARCHAR(255) NULL,
    php_version VARCHAR(60) NULL,
    app_path VARCHAR(255) NULL,
    db_host VARCHAR(150) NULL,
    db_name VARCHAR(100) NULL,
    last_user_id INT UNSIGNED NULL,
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    sync_status VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    sync_message VARCHAR(255) NULL,
    opened_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_workstation_last_user FOREIGN KEY (last_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_workstation_seen (last_seen_at),
    INDEX idx_workstation_user (last_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        self::seedInitialData($pdo);
    }

    private static function seedInitialData(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
INSERT INTO roles (id, name, display_name)
VALUES
    (1, 'admin', 'Admin'),
    (2, 'user', 'User')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    display_name = VALUES(display_name);
SQL);

        $adminHash = '$2y$12$T2dKaOmUIKjTGsC8rPsH..BnH2GV6CHRHE7WtL04SZeIXmBAq5iaS'; // Admin123*
        $userHash = '$2y$12$rcgZhWLaKdf7m.bZ30jnSeqCQEANPHfw6RRBcO0iN5tbKq0GUC/bi'; // User123*

        $stmt = $pdo->prepare(<<<SQL
INSERT INTO users (role_id, name, username, email, password_hash)
SELECT 1, 'Administrador General', 'admin', 'admin@pasteleria.local', :admin_hash
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin' OR email = 'admin@pasteleria.local')
SQL);
        $stmt->execute(['admin_hash' => $adminHash]);

        $stmt = $pdo->prepare(<<<SQL
INSERT INTO users (role_id, name, username, email, password_hash)
SELECT 2, 'Usuario de Producción', 'user', 'user@pasteleria.local', :user_hash
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'user' OR email = 'user@pasteleria.local')
SQL);
        $stmt->execute(['user_hash' => $userHash]);

        $pdo->exec(<<<SQL
INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES
    ('theme_primary', '#8b4513'),
    ('theme_secondary', '#f2b705');
SQL);

        $pdo->exec(<<<SQL
INSERT IGNORE INTO materias_primas (nombre, unidad, stock_actual, stock_minimo)
VALUES
    ('Harina', 'kg', 250.00, 50.00),
    ('Azúcar', 'kg', 180.00, 40.00),
    ('Huevos', 'piezas', 900.00, 200.00),
    ('Leche', 'litros', 120.00, 30.00),
    ('Mantequilla', 'kg', 90.00, 20.00),
    ('Crema', 'litros', 70.00, 15.00),
    ('Chocolate', 'kg', 85.00, 20.00),
    ('Frutas', 'kg', 65.00, 15.00),
    ('Levadura', 'kg', 25.00, 5.00),
    ('Colorantes', 'litros', 12.00, 3.00),
    ('Empaques', 'piezas', 1500.00, 300.00);
SQL);

        $pdo->exec(<<<SQL
INSERT IGNORE INTO productos (nombre, categoria, precio)
VALUES
    ('Pastel de chocolate empaquetado', 'pastel', 180.00),
    ('Cupcake de vainilla', 'cupcake', 35.00),
    ('Galleta con chispas de chocolate', 'galleta', 25.00),
    ('Postre de crema y frutas', 'postre', 60.00);
SQL);

        $pdo->exec(<<<SQL
INSERT INTO produccion_diaria (fecha, producto_id, cantidad, linea)
SELECT CURDATE(), p.id, 25, 'Línea pasteles'
FROM productos p
WHERE p.nombre = 'Pastel de chocolate empaquetado'
  AND NOT EXISTS (SELECT 1 FROM produccion_diaria WHERE fecha = CURDATE() AND linea = 'Línea pasteles')
LIMIT 1;
SQL);

        $pdo->exec(<<<SQL
INSERT INTO modules (module_key, name, description, route, icon, is_active, admin_only, sort_order)
VALUES
    ('admin_dashboard', 'Dashboard Admin', 'Panel estratégico del administrador.', 'admin/dashboard.php', '📊', 1, 1, 10),
    ('users', 'Usuarios y permisos', 'Crear usuarios, asignar roles y controlar permisos por módulo.', 'admin/users.php', '👥', 1, 1, 20),
    ('appearance', 'Apariencia', 'Cambiar colores permanentes del sistema.', 'admin/appearance.php', '🎨', 1, 1, 30),
    ('workstations', 'Estado de PCs', 'Ver equipos que han abierto el sistema y confirmar conexión con la base central.', 'modules/workstations.php', '💻', 1, 0, 90),
    ('inventory', 'Inventario de materia prima', 'Consulta de harina, azúcar, huevos, leche, mantequilla y demás insumos.', 'modules/inventory.php', '📦', 1, 0, 100),
    ('products', 'Productos', 'Catálogo de pasteles, cupcakes, galletas y postres empaquetados.', 'modules/products.php', '🍰', 1, 0, 110),
    ('production', 'Registro de producción', 'Producción diaria por producto, cantidad y línea.', 'modules/production.php', '🏭', 1, 0, 120),
    ('reports', 'Dashboards y reportes', 'Indicadores básicos y avanzados de producción.', 'modules/reports.php', '📈', 1, 0, 130),
    ('ai', 'Consultas IA', 'Preguntas inteligentes sobre producción e inventario.', 'modules/ai.php', '🤖', 1, 0, 140)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    route = VALUES(route),
    icon = VALUES(icon),
    is_active = VALUES(is_active),
    admin_only = VALUES(admin_only),
    sort_order = VALUES(sort_order);
SQL);

        // Los administradores siempre conservan acceso completo.
        $pdo->exec(<<<SQL
INSERT INTO user_module_permissions
    (user_id, module_id, can_view, can_create, can_edit, can_delete, can_manage)
SELECT u.id, m.id, 1, 1, 1, 1, 1
FROM users u
CROSS JOIN modules m
INNER JOIN roles r ON r.id = u.role_id
WHERE r.name = 'admin'
ON DUPLICATE KEY UPDATE
    can_view = 1,
    can_create = 1,
    can_edit = 1,
    can_delete = 1,
    can_manage = 1;
SQL);

        // Los usuarios normales reciben permisos iniciales sin tocar cambios hechos después por el Admin.
        $pdo->exec(<<<SQL
INSERT IGNORE INTO user_module_permissions
    (user_id, module_id, can_view, can_create, can_edit, can_delete, can_manage)
SELECT
    u.id,
    m.id,
    CASE WHEN m.admin_only = 0 AND m.module_key <> 'workstations' THEN 1 ELSE 0 END,
    CASE WHEN m.module_key = 'production' THEN 1 ELSE 0 END,
    0,
    0,
    0
FROM users u
CROSS JOIN modules m
INNER JOIN roles r ON r.id = u.role_id
WHERE r.name = 'user';
SQL);
    }

    public static function connectionSummary(): array
    {
        $config = self::config();

        return [
            'host' => (string) ($config['host'] ?? ''),
            'port' => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? ''),
            'username' => (string) ($config['username'] ?? ''),
            'central_note' => (string) ($config['central_note'] ?? 'Base configurada para este proyecto.'),
        ];
    }

    public static function logLogin(?int $userId, string $identifier, bool $success): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(<<<SQL
INSERT INTO login_logs (user_id, identifier, success, ip_address, user_agent)
VALUES (:user_id, :identifier, :success, :ip_address, :user_agent)
SQL);
        $stmt->execute([
            'user_id' => $userId,
            'identifier' => $identifier,
            'success' => $success ? 1 : 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }
}
