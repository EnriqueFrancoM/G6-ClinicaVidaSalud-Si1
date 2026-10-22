<?php

namespace App\Middleware;

use App\Core\App;
use App\Core\Session;
use App\Core\View;

// Verifica la sesión y los permisos del rol antes de ejecutar cualquier controlador (doc. 6.1.2).
class AuthMiddleware
{
    public static function handle(?string $permiso): void
    {
        if ($permiso === null) {
            return;
        }

        if (Session::user() === null) {
            header('Location: ' . App::url('/login'));
            exit;
        }

        if ($permiso !== 'auth' && !Session::hasPermission($permiso)) {
            http_response_code(403);
            View::render('errors/403', [], null);
            exit;
        }
    }
}
