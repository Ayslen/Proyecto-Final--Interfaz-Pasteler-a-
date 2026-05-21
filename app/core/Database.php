<?php

class Database
{
    private static ?PDO $pdo = null;
    private static bool $migrated = false;
    private static ?int $activePort = null;

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
        $host = $config['host'] ?? '127.0.0.1';
        $database = $config['database'] ?? 'pasteleria_manager';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';

        $ports = self::getPorts($config);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $errors = [];

        foreach ($ports as $port) {
            try {
                $serverDsn = "mysql:host={$host};port={$port};charset={$charset}";
                $serverPdo = new PDO($serverDsn, $username, $password, $options);

                $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                self::$pdo = new PDO($dsn, $username, $password, $options);
                self::$activePort = $port;

                return;
            } catch (PDOException $e) {
                $errors[] = "Puerto {$port}: " . $e->getMessage();
            }
        }

        throw new RuntimeException(
            'Error al conectar con la base de datos. Puertos probados: ' .
            implode(', ', $ports) .
            '. Detalle: ' .
            implode(' | ', $errors)
        );
    }

    private static function getPorts(array $config): array
    {
        $ports = [];

        if (isset($config['port'])) {
            $ports[] = (int) $config['port'];
        }

        if (isset($config['ports']) && is_array($config['ports'])) {
            foreach ($config['ports'] as $port) {
                $ports[] = (int) $port;
            }
        }

        $ports = array_values(array_unique(array_filter($ports)));

        return empty($ports) ? [3306, 3307] : $ports;
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
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_login_logs_user (user_id),
    INDEX idx_login_logs_created (created_at)
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_materias_nombre (nombre),
    INDEX idx_materias_stock (stock_actual, stock_minimo)
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_productos_categoria (categoria),
    INDEX idx_productos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS producto_materias_primas (
    producto_id INT UNSIGNED NOT NULL,
    materia_prima_id INT UNSIGNED NOT NULL,
    cantidad_por_unidad DECIMAL(10,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (producto_id, materia_prima_id),
    CONSTRAINT fk_producto_materia_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_producto_materia_materia FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
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
    INDEX idx_produccion_fecha (fecha),
    INDEX idx_produccion_producto (producto_id),
    INDEX idx_produccion_linea (linea)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    materia_prima_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada','salida','ajuste') NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movimiento_materia FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_movimientos_materia (materia_prima_id),
    INDEX idx_movimientos_tipo (tipo),
    INDEX idx_movimientos_fecha (created_at)
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_modules_active (is_active),
    INDEX idx_modules_order (sort_order)
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
    INDEX idx_workstation_user (last_user_id),
    INDEX idx_workstation_sync (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS ai_queries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    role_name VARCHAR(30) NULL,
    question TEXT NOT NULL,
    context_summary TEXT NULL,
    response TEXT NULL,
    query_type ENUM('general','produccion','inventario','recomendacion') NOT NULL DEFAULT 'general',
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_message VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_queries_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_ai_queries_user (user_id),
    INDEX idx_ai_queries_type (query_type),
    INDEX idx_ai_queries_created (created_at)
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

        self::seedMateriasPrimas($pdo);
        self::seedProductos($pdo);
        self::seedRecetas($pdo);
        self::seedProduccion($pdo);
        self::seedMovimientosInventario($pdo);
        self::seedModulesAndPermissions($pdo);
    }

    private static function seedMateriasPrimas(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
INSERT INTO materias_primas (nombre, unidad, stock_actual, stock_minimo)
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
    ('Empaques', 'piezas', 1500.00, 300.00),
    ('Queso crema', 'kg', 55.00, 12.00),
    ('Vainilla', 'litros', 18.00, 4.00),
    ('Fresas', 'kg', 40.00, 10.00),
    ('Nueces', 'kg', 32.00, 8.00),
    ('Cacao en polvo', 'kg', 45.00, 10.00),
    ('Cajas para pastel', 'piezas', 600.00, 120.00),
    ('Capacillos', 'piezas', 3000.00, 700.00)
ON DUPLICATE KEY UPDATE
    unidad = VALUES(unidad),
    stock_actual = VALUES(stock_actual),
    stock_minimo = VALUES(stock_minimo);
SQL);
    }

    private static function seedProductos(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
INSERT INTO productos (nombre, categoria, precio, activo)
VALUES
    ('Pastel de chocolate empaquetado', 'pastel', 180.00, 1),
    ('Pastel de tres leches', 'pastel', 220.00, 1),
    ('Pastel de zanahoria', 'pastel', 240.00, 1),
    ('Cupcake de vainilla', 'cupcake', 35.00, 1),
    ('Cupcake de chocolate', 'cupcake', 38.00, 1),
    ('Galleta con chispas de chocolate', 'galleta', 25.00, 1),
    ('Galleta de nuez', 'galleta', 28.00, 1),
    ('Postre de crema y frutas', 'postre', 60.00, 1),
    ('Cheesecake de fresa', 'postre', 75.00, 1),
    ('Brownie empaquetado', 'postre', 45.00, 1)
ON DUPLICATE KEY UPDATE
    categoria = VALUES(categoria),
    precio = VALUES(precio),
    activo = VALUES(activo);
SQL);
    }

    private static function seedRecetas(PDO $pdo): void
    {
        $recetas = [
            'Pastel de chocolate empaquetado' => [
                ['Harina', 0.50],
                ['Azúcar', 0.30],
                ['Huevos', 4.00],
                ['Leche', 0.40],
                ['Mantequilla', 0.20],
                ['Chocolate', 0.25],
                ['Cacao en polvo', 0.08],
                ['Empaques', 1.00],
            ],
            'Pastel de tres leches' => [
                ['Harina', 0.45],
                ['Azúcar', 0.28],
                ['Huevos', 5.00],
                ['Leche', 0.90],
                ['Crema', 0.35],
                ['Vainilla', 0.03],
                ['Cajas para pastel', 1.00],
            ],
            'Pastel de zanahoria' => [
                ['Harina', 0.45],
                ['Azúcar', 0.25],
                ['Huevos', 4.00],
                ['Mantequilla', 0.18],
                ['Queso crema', 0.22],
                ['Nueces', 0.10],
                ['Cajas para pastel', 1.00],
            ],
            'Cupcake de vainilla' => [
                ['Harina', 0.08],
                ['Azúcar', 0.05],
                ['Huevos', 1.00],
                ['Leche', 0.06],
                ['Mantequilla', 0.03],
                ['Vainilla', 0.01],
                ['Capacillos', 1.00],
            ],
            'Cupcake de chocolate' => [
                ['Harina', 0.08],
                ['Azúcar', 0.05],
                ['Huevos', 1.00],
                ['Leche', 0.05],
                ['Chocolate', 0.04],
                ['Cacao en polvo', 0.02],
                ['Capacillos', 1.00],
            ],
            'Cheesecake de fresa' => [
                ['Queso crema', 0.25],
                ['Azúcar', 0.10],
                ['Huevos', 2.00],
                ['Crema', 0.12],
                ['Fresas', 0.18],
                ['Empaques', 1.00],
            ],
            'Brownie empaquetado' => [
                ['Harina', 0.10],
                ['Azúcar', 0.08],
                ['Huevos', 1.00],
                ['Mantequilla', 0.05],
                ['Chocolate', 0.08],
                ['Cacao en polvo', 0.03],
                ['Empaques', 1.00],
            ],
        ];

        foreach ($recetas as $producto => $materias) {
            foreach ($materias as [$materia, $cantidad]) {
                self::insertReceta($pdo, $producto, $materia, (float) $cantidad);
            }
        }
    }

    private static function insertReceta(PDO $pdo, string $producto, string $materia, float $cantidad): void
    {
        $stmt = $pdo->prepare(<<<SQL
INSERT INTO producto_materias_primas (producto_id, materia_prima_id, cantidad_por_unidad)
SELECT p.id, m.id, :cantidad
FROM productos p
INNER JOIN materias_primas m ON m.nombre = :materia
WHERE p.nombre = :producto
ON DUPLICATE KEY UPDATE
    cantidad_por_unidad = VALUES(cantidad_por_unidad)
SQL);

        $stmt->execute([
            'producto' => $producto,
            'materia' => $materia,
            'cantidad' => $cantidad,
        ]);
    }

    private static function seedProduccion(PDO $pdo): void
    {
        $registros = [
            ['CURDATE()', 'Pastel de chocolate empaquetado', 25, 'Línea pasteles'],
            ['CURDATE()', 'Cupcake de vainilla', 120, 'Línea cupcakes'],
            ['CURDATE()', 'Galleta con chispas de chocolate', 180, 'Línea galletas'],
            ['DATE_SUB(CURDATE(), INTERVAL 1 DAY)', 'Pastel de tres leches', 18, 'Línea pasteles'],
            ['DATE_SUB(CURDATE(), INTERVAL 1 DAY)', 'Cupcake de chocolate', 140, 'Línea cupcakes'],
            ['DATE_SUB(CURDATE(), INTERVAL 1 DAY)', 'Cheesecake de fresa', 32, 'Línea postres'],
            ['DATE_SUB(CURDATE(), INTERVAL 2 DAY)', 'Pastel de zanahoria', 15, 'Línea pasteles'],
            ['DATE_SUB(CURDATE(), INTERVAL 2 DAY)', 'Galleta de nuez', 160, 'Línea galletas'],
            ['DATE_SUB(CURDATE(), INTERVAL 2 DAY)', 'Brownie empaquetado', 90, 'Línea postres'],
            ['DATE_SUB(CURDATE(), INTERVAL 3 DAY)', 'Postre de crema y frutas', 75, 'Línea postres'],
            ['DATE_SUB(CURDATE(), INTERVAL 3 DAY)', 'Cupcake de vainilla', 110, 'Línea cupcakes'],
            ['DATE_SUB(CURDATE(), INTERVAL 4 DAY)', 'Pastel de chocolate empaquetado', 22, 'Línea pasteles'],
            ['DATE_SUB(CURDATE(), INTERVAL 4 DAY)', 'Galleta con chispas de chocolate', 210, 'Línea galletas'],
            ['DATE_SUB(CURDATE(), INTERVAL 5 DAY)', 'Pastel de tres leches', 20, 'Línea pasteles'],
            ['DATE_SUB(CURDATE(), INTERVAL 5 DAY)', 'Brownie empaquetado', 100, 'Línea postres'],
            ['DATE_SUB(CURDATE(), INTERVAL 6 DAY)', 'Cupcake de chocolate', 130, 'Línea cupcakes'],
            ['DATE_SUB(CURDATE(), INTERVAL 6 DAY)', 'Cheesecake de fresa', 28, 'Línea postres'],
        ];

        foreach ($registros as [$fechaSql, $producto, $cantidad, $linea]) {
            self::insertProduccionIfMissing($pdo, $fechaSql, $producto, (int) $cantidad, $linea);
        }
    }

    private static function insertProduccionIfMissing(PDO $pdo, string $fechaSql, string $producto, int $cantidad, string $linea): void
    {
        $stmt = $pdo->prepare(<<<SQL
INSERT INTO produccion_diaria (fecha, producto_id, cantidad, linea, created_by)
SELECT {$fechaSql}, p.id, :cantidad, :linea, u.id
FROM productos p
LEFT JOIN users u ON u.username = 'admin'
WHERE p.nombre = :producto
  AND NOT EXISTS (
      SELECT 1
      FROM produccion_diaria pd
      WHERE pd.fecha = {$fechaSql}
        AND pd.producto_id = p.id
        AND pd.linea = :linea_check
  )
LIMIT 1
SQL);

        $stmt->execute([
            'cantidad' => $cantidad,
            'linea' => $linea,
            'producto' => $producto,
            'linea_check' => $linea,
        ]);
    }

    private static function seedMovimientosInventario(PDO $pdo): void
    {
        $movimientos = [
            ['Harina', 'entrada', 100.00, 'Compra semanal de harina'],
            ['Azúcar', 'entrada', 80.00, 'Compra semanal de azúcar'],
            ['Huevos', 'entrada', 300.00, 'Recepción de huevo para producción'],
            ['Chocolate', 'salida', 12.00, 'Consumo por producción de pasteles y brownies'],
            ['Fresas', 'salida', 8.00, 'Consumo por cheesecake de fresa'],
            ['Empaques', 'salida', 180.00, 'Empaques usados en producción diaria'],
            ['Capacillos', 'salida', 260.00, 'Capacillos usados en cupcakes'],
            ['Queso crema', 'ajuste', 2.00, 'Ajuste por merma de refrigeración'],
        ];

        foreach ($movimientos as [$materia, $tipo, $cantidad, $descripcion]) {
            self::insertMovimientoIfMissing($pdo, $materia, $tipo, (float) $cantidad, $descripcion);
        }
    }

    private static function insertMovimientoIfMissing(PDO $pdo, string $materia, string $tipo, float $cantidad, string $descripcion): void
    {
        $stmt = $pdo->prepare(<<<SQL
INSERT INTO movimientos_inventario (materia_prima_id, tipo, cantidad, descripcion, created_by)
SELECT m.id, :tipo, :cantidad, :descripcion, u.id
FROM materias_primas m
LEFT JOIN users u ON u.username = 'admin'
WHERE m.nombre = :materia
  AND NOT EXISTS (
      SELECT 1
      FROM movimientos_inventario mi
      WHERE mi.materia_prima_id = m.id
        AND mi.tipo = :tipo_check
        AND mi.descripcion = :descripcion_check
  )
LIMIT 1
SQL);

        $stmt->execute([
            'materia' => $materia,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'descripcion' => $descripcion,
            'tipo_check' => $tipo,
            'descripcion_check' => $descripcion,
        ]);
    }

    private static function seedModulesAndPermissions(PDO $pdo): void
    {
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
        $ports = self::getPorts($config);

        return [
            'host' => (string) ($config['host'] ?? ''),
            'port' => self::$activePort ?? $ports[0],
            'ports' => implode(', ', $ports),
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