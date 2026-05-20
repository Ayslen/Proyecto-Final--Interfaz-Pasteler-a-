<?php

function app_config(string $key, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config/app.php';
    }

    return $config[$key] ?? $default;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base_url(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $publicPos = strpos($scriptName, '/public/');

    if ($publicPos !== false) {
        return rtrim(substr($scriptName, 0, $publicPos + strlen('/public')), '/');
    }

    $dir = str_replace('\\', '/', dirname($scriptName));
    return rtrim($dir === '/' ? '' : $dir, '/');
}

function url(string $path = ''): string
{
    return app_base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function current_page(): string
{
    return basename($_SERVER['PHP_SELF'] ?? '');
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}
