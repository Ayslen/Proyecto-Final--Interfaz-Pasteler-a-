<?php

/*
|--------------------------------------------------------------------------
| Conexión de base de datos CENTRAL
|--------------------------------------------------------------------------
| Configuración general de MySQL para el proyecto.
|
| El sistema prueba automáticamente los puertos 3306 y 3307.
| Esto permite que funcione en equipos donde XAMPP usa 3306 o 3307.
*/

$envPorts = getenv('DB_PORTS');
$envPort = getenv('DB_PORT');

if ($envPorts) {
    $ports = array_values(array_filter(array_map('intval', explode(',', $envPorts))));
} elseif ($envPort) {
    $ports = [(int) $envPort];
} else {
    $ports = [3306, 3307];
}

$default = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => $ports[0] ?? 3306,
    'ports' => $ports,
    'database' => getenv('DB_NAME') ?: 'pasteleria_manager',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
    'auto_migrate' => true,
    'central_note' => 'Base de datos central del proyecto de pastelería industrial.',
];

/*
|--------------------------------------------------------------------------
| Configuración local opcional
|--------------------------------------------------------------------------
| database.local.php sirve para pruebas personales.
| No es obligatorio usarlo, porque el sistema ya prueba 3306 y 3307.
*/
$localConfig = __DIR__ . '/database.local.php';

if (file_exists($localConfig)) {
    $local = require $localConfig;

    if (is_array($local)) {
        $default = array_merge($default, $local);
    }
}

return $default;