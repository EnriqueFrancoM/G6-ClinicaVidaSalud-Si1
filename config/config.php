<?php
// Configuración general. Los valores reales van en el archivo .env (no se sube a GitHub).

$env = [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $env = parse_ini_file($envFile) ?: [];
}

$valorEntorno = static function (string $clave, mixed $predeterminado = null) use ($env): mixed {
    if (array_key_exists($clave, $env)) {
        $valor = $env[$clave];
    } else {
        $valor = getenv($clave);
        if ($valor === false) {
            return $predeterminado;
        }
    }
    if (!is_string($valor)) {
        return $valor;
    }
    $valor = trim($valor);
    $largo = strlen($valor);
    if ($largo >= 2) {
        $abre = $valor[0];
        $cierra = $valor[$largo - 1];
        if (($abre === '"' && $cierra === '"') || ($abre === "'" && $cierra === "'")) {
            $valor = substr($valor, 1, -1);
        }
    }

    return $valor;
};

// Render entrega la conexión privada como DATABASE_URL. En local se siguen
// admitiendo las cinco variables DB_* del archivo .env.
$databaseUrl = trim((string) $valorEntorno('DATABASE_URL', ''));
$partesDb = $databaseUrl !== '' ? parse_url($databaseUrl) : [];
$partesDb = is_array($partesDb) ? $partesDb : [];
$decodificar = static fn (mixed $valor): string => is_string($valor) ? rawurldecode($valor) : '';

// Correo: si hay clave REST de Brevo (xkeysib-) y un remitente verificado, se usa la API
// HTTPS. En Render el SMTP del puerto 587 se corta. El relay SMTP (MAIL_HOST o
// BREVO_SMTP_USER + clave xsmtpsib-) queda como respaldo cuando no hay API.
$mailHost = trim((string) $valorEntorno('MAIL_HOST', ''));
$mailPort = (int) $valorEntorno('MAIL_PORT', 587);
$mailUser = trim((string) $valorEntorno('MAIL_USER', ''));
$mailPass = (string) $valorEntorno('MAIL_PASS', '');
$mailFrom = trim((string) $valorEntorno('MAIL_FROM', ''));
$mailEncryption = strtolower(trim((string) $valorEntorno('MAIL_ENCRYPTION', 'tls')));
$mailFromName = trim((string) $valorEntorno('MAIL_FROM_NAME', ''));
$brevoApiKey = trim((string) $valorEntorno('BREVO_API_KEY', ''));
$brevoSender = trim((string) $valorEntorno('BREVO_SENDER_EMAIL', ''));
$brevoSenderName = trim((string) $valorEntorno('BREVO_SENDER_NAME', ''));
$brevoSmtpUser = trim((string) $valorEntorno('BREVO_SMTP_USER', ''));
$brevoSmtpKey = (string) $valorEntorno('BREVO_SMTP_KEY', '');
if ($mailHost === '' && $brevoSmtpUser !== '' && $brevoSmtpKey !== '') {
    $mailHost = 'smtp-relay.brevo.com';
    $mailPort = (int) $valorEntorno('BREVO_SMTP_PORT', 587);
    $mailUser = $brevoSmtpUser;
    $mailPass = $brevoSmtpKey;
    $mailEncryption = 'tls';
    if ($mailFrom === '') {
        $mailFrom = $brevoSender !== '' ? $brevoSender : $brevoSmtpUser;
    }
    if ($mailFromName === '') {
        $mailFromName = $brevoSenderName;
    }
}

$canalRecordatorio = strtolower(trim((string) $valorEntorno('RECORDATORIO_CANAL', 'auto')));
if (!in_array($canalRecordatorio, ['auto', 'email', 'whatsapp'], true)) {
    $canalRecordatorio = 'auto';
}
$modoPlantilla = strtolower(trim((string) $valorEntorno('WHATSAPP_TEMPLATE_MODO', 'campos')));
if (!in_array($modoPlantilla, ['campos', 'texto'], true)) {
    $modoPlantilla = 'campos';
}
return [
    'app' => [
        'name'     => (string) $valorEntorno('APP_NAME', 'Clínica Vida Salud'),
        // RENDER_EXTERNAL_URL existe automáticamente en los Web Services de Render.
        'base_url' => rtrim((string) $valorEntorno('APP_URL', $valorEntorno('RENDER_EXTERNAL_URL', '')), '/'),
        'debug'    => filter_var($valorEntorno('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        'timezone' => 'America/La_Paz',
        // Firma de los enlaces de confirmar/cancelar de los recordatorios (CU20, RF21).
        'key'      => (string) $valorEntorno('APP_KEY', ''),
    ],
    // CU20 ProveedorNotificaciones. Sin host y sin API de Brevo el correo no se envía.
    'mail' => [
        'host'       => $mailHost,
        'port'       => $mailPort,
        'user'       => $mailUser,
        'pass'       => $mailPass,
        'from'       => $mailFrom,
        'from_name'  => $mailFromName,
        'encryption' => $mailEncryption,
    ],
    // API HTTPS de Brevo. Si la clave y el remitente están puestos, tiene prioridad sobre SMTP.
    'brevo' => [
        'api_key'     => $brevoApiKey,
        'sender_email'=> $brevoSender,
        'sender_name' => $brevoSenderName,
    ],
    // Meta Cloud API. Sin token o sin id de número, el recordatorio no elige WhatsApp.
    'whatsapp' => [
        'token'            => trim((string) $valorEntorno('WHATSAPP_TOKEN', '')),
        'phone_number_id'  => trim((string) $valorEntorno('WHATSAPP_PHONE_NUMBER_ID', '')),
        'template'         => trim((string) $valorEntorno('WHATSAPP_TEMPLATE', '')),
        'idioma'           => trim((string) $valorEntorno('WHATSAPP_TEMPLATE_IDIOMA', 'es')),
        'plantilla_modo'   => $modoPlantilla,
        'prefijo'          => trim((string) $valorEntorno('WHATSAPP_PREFIJO', '591')),
    ],
    // auto: WhatsApp si está configurado y hay teléfono; si no, correo; si no, portal.
    'recordatorio' => [
        'canal' => $canalRecordatorio,
    ],
    // Una sola clave de OpenRouter para todas las cuentas. Vacía: no se llama a OpenRouter.
    // GEMINI_API_KEY es el respaldo gratuito si OpenRouter se queda sin cupo. Vacía: no se llama a Gemini.
    'openrouter' => [
        'api_key' => trim((string) $valorEntorno('OPENROUTER_API_KEY', '')),
        'model'   => trim((string) $valorEntorno('OPENROUTER_MODEL', 'openrouter/free')) ?: 'openrouter/free',
    ],
    'gemini' => [
        'api_key' => trim((string) $valorEntorno('GEMINI_API_KEY', '')),
        'model'   => trim((string) $valorEntorno('GEMINI_MODEL', 'libre')),
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
