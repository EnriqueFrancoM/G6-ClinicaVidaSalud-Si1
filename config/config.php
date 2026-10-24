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
        'max_intentos_login'  => 5,  // CU01 E2: al superar 5 intentos fallidos se desactiva la cuenta
        'inactividad_minutos' => 30, // CU02 E1: la sesión expira tras 30 minutos sin actividad
    ],
];
