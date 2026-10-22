<?php

namespace App\Controllers\Seguridad;

use App\Core\App;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Usuario;

// CU01 Iniciar sesión / CU02 Cerrar sesión
class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Session::user() !== null) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', [], 'auth');
    }

    public function login(): void
    {
        $login = (string) $this->input('login', '');
        $password = (string) $this->input('password', '');

        if ($login === '' || $password === '') {
            Session::flash('error', 'Ingrese su usuario y contraseña.');
            $this->redirect('/login');
        }

        $usuarios = new Usuario();
        $bitacora = new Bitacora();
        $usuario = $usuarios->findByLogin($login);

        if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
            if ($usuario !== null) {
                $usuarios->registrarIntentoFallido($usuario['id_usuario'], App::config('security.max_intentos_login'));
                $bitacora->registrar($usuario['id_usuario'], 'LOGIN_FALLIDO', 'Seguridad', 'Contraseña incorrecta');
            }
            Session::flash('error', 'Usuario o contraseña incorrectos.');
            $this->redirect('/login');
        }

        if (!$usuario['estado']) {
            Session::flash('error', 'La cuenta está desactivada. Contacte al administrador.');
            $this->redirect('/login');
        }

        session_regenerate_id(true);
        Session::set('usuario', [
            'id_usuario' => $usuario['id_usuario'],
            'username'   => $usuario['username'],
            'id_rol'     => $usuario['id_rol'],
            'rol'        => $usuario['rol'],
        ]);
        Session::set('permisos', $usuarios->permisos($usuario['id_rol']));

        $usuarios->registrarAccesoExitoso($usuario['id_usuario']);
        $bitacora->registrar($usuario['id_usuario'], 'LOGIN', 'Seguridad');

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $usuario = Session::user();
        (new Bitacora())->registrar($usuario['id_usuario'], 'LOGOUT', 'Seguridad');
        Session::destroy();

        Session::start();
        Session::flash('success', 'Sesión cerrada correctamente.');
        $this->redirect('/login');
    }
}
