-- =========================================================
-- Proyecto Final - Manager Inteligente para Pastelería
-- Alumno 2: Base de Datos
-- Base de datos: pasteleria_manager
-- =========================================================

CREATE DATABASE IF NOT EXISTS pasteleria_manager
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE pasteleria_manager;

-- =========================================================
-- Tabla: roles
-- Guarda los roles principales del sistema: Admin y User
-- =========================================================

CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    display_name VARCHAR(60) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: users
-- Guarda los usuarios registrados en el sistema
-- =========================================================

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

    CONSTRAINT fk_users_roles 
        FOREIGN KEY (role_id) 
        REFERENCES roles(id)
        ON UPDATE CASCADE 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: login_logs
-- Guarda historial de intentos de inicio de sesión
-- =========================================================

CREATE TABLE IF NOT EXISTS login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    identifier VARCHAR(150) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_login_logs_users 
        FOREIGN KEY (user_id) 
        REFERENCES users(id)
        ON UPDATE CASCADE 
        ON DELETE SET NULL,

    INDEX idx_login_logs_user (user_id),
    INDEX idx_login_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: materias_primas
-- Guarda los insumos de la pastelería industrial
-- =========================================================

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

-- =========================================================
-- Tabla: productos
-- Guarda los productos fabricados por la pastelería
-- =========================================================

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

-- =========================================================
-- Tabla: producto_materias_primas
-- Relaciona cada producto con la materia prima que necesita
-- Funciona como receta técnica del producto
-- =========================================================

CREATE TABLE IF NOT EXISTS producto_materias_primas (
    producto_id INT UNSIGNED NOT NULL,
    materia_prima_id INT UNSIGNED NOT NULL,
    cantidad_por_unidad DECIMAL(10,2) NOT NULL DEFAULT 0,

    PRIMARY KEY (producto_id, materia_prima_id),

    CONSTRAINT fk_producto_materia_producto 
        FOREIGN KEY (producto_id) 
        REFERENCES productos(id)
        ON UPDATE CASCADE 
        ON DELETE CASCADE,

    CONSTRAINT fk_producto_materia_materia 
        FOREIGN KEY (materia_prima_id) 
        REFERENCES materias_primas(id)
        ON UPDATE CASCADE 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: produccion_diaria
-- Guarda los registros de producción diaria
-- =========================================================

CREATE TABLE IF NOT EXISTS produccion_diaria (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    linea VARCHAR(80) NOT NULL DEFAULT 'Línea general',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_produccion_producto 
        FOREIGN KEY (producto_id) 
        REFERENCES productos(id)
        ON UPDATE CASCADE 
        ON DELETE RESTRICT,

    CONSTRAINT fk_produccion_usuario 
        FOREIGN KEY (created_by) 
        REFERENCES users(id)
        ON UPDATE CASCADE 
        ON DELETE SET NULL,

    INDEX idx_produccion_fecha (fecha),
    INDEX idx_produccion_producto (producto_id),
    INDEX idx_produccion_linea (linea)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: movimientos_inventario
-- Guarda entradas, salidas y ajustes de inventario
-- =========================================================

CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    materia_prima_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada','salida','ajuste') NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_movimiento_materia 
        FOREIGN KEY (materia_prima_id) 
        REFERENCES materias_primas(id)
        ON UPDATE CASCADE 
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimiento_usuario 
        FOREIGN KEY (created_by) 
        REFERENCES users(id)
        ON UPDATE CASCADE 
        ON DELETE SET NULL,

    INDEX idx_movimientos_materia (materia_prima_id),
    INDEX idx_movimientos_tipo (tipo),
    INDEX idx_movimientos_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: app_settings
-- Guarda configuraciones generales del sistema
-- =========================================================

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: modules
-- Guarda los módulos disponibles dentro del sistema
-- =========================================================

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

-- =========================================================
-- Tabla: user_module_permissions
-- Guarda permisos específicos por usuario y módulo
-- =========================================================

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

    CONSTRAINT fk_permissions_user 
        FOREIGN KEY (user_id) 
        REFERENCES users(id)
        ON UPDATE CASCADE 
        ON DELETE CASCADE,

    CONSTRAINT fk_permissions_module 
        FOREIGN KEY (module_id) 
        REFERENCES modules(id)
        ON UPDATE CASCADE 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: workstation_status
-- Guarda información de las PCs que abren el sistema
-- =========================================================

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

    CONSTRAINT fk_workstation_last_user 
        FOREIGN KEY (last_user_id) 
        REFERENCES users(id)
        ON UPDATE CASCADE 
        ON DELETE SET NULL,

    INDEX idx_workstation_seen (last_seen_at),
    INDEX idx_workstation_user (last_user_id),
    INDEX idx_workstation_sync (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Tabla: ai_queries
-- Guarda historial de consultas realizadas a la IA
-- =========================================================

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

    CONSTRAINT fk_ai_queries_user 
        FOREIGN KEY (user_id) 
        REFERENCES users(id)
        ON UPDATE CASCADE 
        ON DELETE SET NULL,

    INDEX idx_ai_queries_user (user_id),
    INDEX idx_ai_queries_type (query_type),
    INDEX idx_ai_queries_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;