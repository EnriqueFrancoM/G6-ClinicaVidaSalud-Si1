<?php
// Front controller: todas las peticiones entran por aquí (ver .htaccess).

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Autoload PSR-4 simple: App\X\Y -> app/X/Y.php
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$config = require BASE_PATH . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');

App\Core\Session::start();
App\Core\App::boot($config);

$router = new App\Core\Router(require BASE_PATH . '/config/routes.php');
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
