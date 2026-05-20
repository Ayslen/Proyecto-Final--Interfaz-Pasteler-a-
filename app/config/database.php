<?php

/*
|--------------------------------------------------------------------------
| Conexión de base de datos CENTRAL
|--------------------------------------------------------------------------
| Para que tus compañeros descarguen el proyecto de Git y usen TU MISMA
| base de datos, cambia aquí el host por la IP pública, dominio DDNS o IP
| de VPN de tu PC, y luego sube este archivo ya configurado al repositorio.
|
| IMPORTANTE:
| - 127.0.0.1 significa "la misma PC donde corre el código".
| - Para tus colaboradores, 127.0.0.1 sería SU propia PC, no la tuya.
| - Por eso, si quieres base central en tu PC, usa algo como:
|   mi-pasteleria.ddns.net, 187.xxx.xxx.xxx o una IP de Tailscale/ZeroTier.
*/

$default = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: 'pasteleria_manager',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
    'auto_migrate' => true,
    'central_note' => 'Base de datos central del proyecto de pastelería industrial.',
];

/*
| database.local.php sirve para pruebas personales.
| No lo subas a Git si quieres que todos usen exactamente la misma conexión.
*/
$localConfig = __DIR__ . '/database.local.php';

if (file_exists($localConfig)) {
    $local = require $localConfig;
    if (is_array($local)) {
        $default = array_merge($default, $local);
    }
}

return $default;
