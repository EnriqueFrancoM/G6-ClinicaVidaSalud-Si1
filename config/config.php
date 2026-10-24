<?php
// Configuración general. Los valores reales van en el archivo .env (no se sube a GitHub).

$env = [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $env = parse_ini_file($envFile) ?: [];
}

$valorEntorno = static function (string $clave, mixed $predeterminado = null) use ($env): mixed {
    if (array_key_exists($clave, $env)) {
        return $env[$clave];
    }

    $valor = getenv($clave);
    return $valor === false ? $predeterminado : $valor;
};

// Render entrega la conexión privada como DATABASE_URL. En local se siguen
// admitiendo las cinco variables DB_* del archivo .env.
$databaseUrl = trim((string) $valorEntorno('DATABASE_URL', ''));
$partesDb = $databaseUrl !== '' ? parse_url($databaseUrl) : [];
$partesDb = is_array($partesDb) ? $partesDb : [];
$decodificar = static fn (mixed $valor): string => is_string($valor) ? rawurldecode($valor) : '';

return [
    'app' => [
        'name'     => (string) $valorEntorno('APP_NAME', 'Clínica Vida Salud'),
        // RENDER_EXTERNAL_URL existe automáticamente en los Web Services de Render.
        'base_url' => rtrim((string) $valorEntorno('APP_URL', $valorEntorno('RENDER_EXTERNAL_URL', '')), '/'),
        'debug'    => filter_var($valorEntorno('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        'timezone' => 'America/La_Paz',
    ],
    'db' => [
        'host' => (string) $valorEntorno('DB_HOST', $partesDb['host'] ?? '127.0.0.1'),
        'port' => (string) $valorEntorno('DB_PORT', $partesDb['port'] ?? '5432'),
        'name' => (string) $valorEntorno('DB_NAME', $decodificar(ltrim((string) ($partesDb['path'] ?? ''), '/')) ?: 'bd_vidasalud'),
        'user' => (string) $valorEntorno('DB_USER', $decodificar($partesDb['user'] ?? '') ?: 'postgres'),
        'pass' => (string) $valorEntorno('DB_PASS', $decodificar($partesDb['pass'] ?? '')),
    ],
    'security' => [
        'max_intentos_login'  => 6,  // El sexto fallo bloquea la cuenta
        'bloqueo_login_15s'   => 4,
        'bloqueo_login_30s'   => 5,
        'ultimo_intento_login'=> 5,
        'inactividad_minutos' => 30, // CU02 E1: la sesión expira tras 30 minutos sin actividad
    ],
];
