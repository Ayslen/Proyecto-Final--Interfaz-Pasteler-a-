-- Datos de ejemplo.
-- Las contraseñas ya están guardadas con hash.
-- Admin123* y User123* son únicamente para prueba escolar.

USE pasteleria_manager;

INSERT INTO roles (id, name, display_name)
VALUES
    (1, 'admin', 'Admin'),
    (2, 'user', 'User')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    display_name = VALUES(display_name);

INSERT INTO users (role_id, name, username, email, password_hash)
SELECT 1, 'Administrador General', 'admin', 'admin@pasteleria.local', '$2y$12$T2dKaOmUIKjTGsC8rPsH..BnH2GV6CHRHE7WtL04SZeIXmBAq5iaS'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin' OR email = 'admin@pasteleria.local');

INSERT INTO users (role_id, name, username, email, password_hash)
SELECT 2, 'Usuario de Producción', 'user', 'user@pasteleria.local', '$2y$12$rcgZhWLaKdf7m.bZ30jnSeqCQEANPHfw6RRBcO0iN5tbKq0GUC/bi'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'user' OR email = 'user@pasteleria.local');

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
    ('Empaques', 'piezas', 1500.00, 300.00)
ON DUPLICATE KEY UPDATE
    unidad = VALUES(unidad),
    stock_actual = VALUES(stock_actual),
    stock_minimo = VALUES(stock_minimo);

INSERT INTO productos (nombre, categoria, precio)
VALUES
    ('Pastel de chocolate empaquetado', 'pastel', 180.00),
    ('Cupcake de vainilla', 'cupcake', 35.00),
    ('Galleta con chispas de chocolate', 'galleta', 25.00),
    ('Postre de crema y frutas', 'postre', 60.00)
ON DUPLICATE KEY UPDATE
    categoria = VALUES(categoria),
    precio = VALUES(precio);
