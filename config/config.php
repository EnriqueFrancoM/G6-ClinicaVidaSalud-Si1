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
        'max_intentos_login' => 5, // al 5.º fallo se bloquea la cuenta
        'penalizaciones' => [
            3 => 15,
            4 => 30,
        ],
        'codigo_minutos' => 10,
    ],
    'mail' => [
        'proveedor'   => 'brevo',
        'api_key'     => $env['BREVO_API_KEY'] ?? '',
        'sender'      => $env['BREVO_SENDER_EMAIL'] ?? '',
        'sender_name' => $env['BREVO_SENDER_NAME'] ?? 'Clínica Vida Salud',
    ],
];
