<?php

namespace App\Core;

use App\Middleware\AuthMiddleware;

// Enrutador: resuelve la ruta, ejecuta el middleware de sesión/permisos y llama al controlador.
class Router
{
    public function __construct(private array $routes)
    {
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = parse_url(App::config('app.base_url', ''), PHP_URL_PATH) ?: '';
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = '/' . trim($path, '/');

        foreach ($this->routes as [$routeMethod, $routePath, $handler, $permiso]) {
            if ($routeMethod === $method && $routePath === $path) {
                if ($method === 'POST') {
                    $this->checkCsrf();
                }
                AuthMiddleware::handle($permiso, $path);
                [$class, $action] = $handler;
                (new $class())->$action();
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', [], null);
    }

    private function checkCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals(Session::csrfToken(), $token)) {
            http_response_code(419);
            exit('Token CSRF inválido. Recarga la página.');
        }
    }
}
