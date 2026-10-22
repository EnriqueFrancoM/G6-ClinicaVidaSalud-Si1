<?php
// Configuración general. Los valores reales van en el archivo .env (no se sube a GitHub).

$env = [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $env = parse_ini_file($envFile) ?: [];
}

return [
    'app' => [
        'name'     => $env['APP_NAME'] ?? 'Clínica Vida Salud',
        'base_url' => rtrim($env['APP_URL'] ?? '', '/'),
        'debug'    => filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'timezone' => 'America/La_Paz',
    ],
    'db' => [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => $env['DB_PORT'] ?? '5432',
        'name' => $env['DB_NAME'] ?? 'bd_vidasalud',
        'user' => $env['DB_USER'] ?? 'postgres',
        'pass' => $env['DB_PASS'] ?? '',
    ],
    'security' => [
        'max_intentos_login' => 5, // tras 5 intentos fallidos se bloquea la cuenta
    ],
];
