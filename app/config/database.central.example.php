<?php

// Copia estos valores dentro de app/config/database.php cuando ya tengas
// lista la conexión pública o por VPN hacia MySQL en tu PC 24/7.
return [
    'host' => 'TU_IP_PUBLICA_O_DOMINIO_DDNS',
    'port' => 3306,
    'database' => 'pasteleria_manager',
    'username' => 'pasteleria_remote',
    'password' => 'CAMBIA_ESTA_PASSWORD_SEGURA',
    'charset' => 'utf8mb4',
    'auto_migrate' => true,
    'central_note' => 'Base central alojada en la PC de Flavio.',
];
