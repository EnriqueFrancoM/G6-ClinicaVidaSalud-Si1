<?php

namespace App\Middleware;

use App\Core\App;
use App\Core\Session;
use App\Core\View;

// Verifica la sesión y los permisos del rol antes de ejecutar cualquier controlador (doc. 6.1.2).
class AuthMiddleware
{
    public static function handle(?string $permiso, string $path = ''): void
    {
        if ($permiso === null) {
            return;
        }

        // CU01 E3: página interna sin sesión válida.
        if (Session::user() === null) {
            header('Location: ' . App::url('/login'));
            exit;
        }

        // CU02 E1: la sesión expiró por inactividad.
        if (Session::expirada((int) App::config('security.inactividad_minutos', 30))) {
            Session::destroy();
            Session::start();
            Session::flash('success', 'Su sesión expiró por inactividad. Inicie sesión nuevamente.');
            header('Location: ' . App::url('/login'));
            exit;
        }
        Session::tocar();

        // CU05 E3: con primer ingreso solo puede cambiar la contraseña o cerrar sesión.
        if ((Session::user()['primer_ingreso'] ?? false) && !in_array($path, ['/cambiar-contrasena', '/logout'], true)) {
            header('Location: ' . App::url('/cambiar-contrasena'));
            exit;
        }

        if ($permiso !== 'auth' && !Session::hasPermission($permiso)) {
            http_response_code(403);
            View::render('errors/403', [], null);
            exit;
        }
    }
}
