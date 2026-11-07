<?php
// CU20 Enviar recordatorios de citas: ejecución programada por el actor Tiempo.
// Programarlo cada 15 minutos, por ejemplo con cron:
//   */15 * * * * cd /var/www/html && php scripts/enviar-recordatorios.php
// En Windows, con el Programador de tareas apuntando a este archivo.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require_once BASE_PATH . '/app/Core/View.php';

$config = require BASE_PATH . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);
App\Core\App::boot($config);

$resumen = App\Controllers\Reportes\ServicioRecordatorios::ejecutarProgramado();
printf(
    "[%s] Recordatorios: %d enviados, %d fallidos, %d descartados; %d ofertas de espera vencidas.\n",
    date('Y-m-d H:i:s'), $resumen['enviados'], $resumen['fallidos'], $resumen['descartados'], $resumen['ofertas_vencidas']
);
