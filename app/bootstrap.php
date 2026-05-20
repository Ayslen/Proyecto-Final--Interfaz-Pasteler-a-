<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$appConfig = require __DIR__ . '/config/app.php';

date_default_timezone_set($appConfig['timezone'] ?? 'America/Monterrey');

if (($appConfig['debug'] ?? false) === true) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Flash.php';
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/models/Theme.php';
require_once __DIR__ . '/models/Module.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/WorkstationStatus.php';
require_once __DIR__ . '/core/Auth.php';

// Inicializa la base de datos automáticamente si está activado.
Database::pdo();

// Registra desde qué equipo/navegador se abrió el sistema.
// Este registro usa la base central y permite revisar el estado desde el módulo Estado de PCs.
if (PHP_SAPI !== 'cli') {
    WorkstationStatus::touchCurrent(Auth::id());
}
