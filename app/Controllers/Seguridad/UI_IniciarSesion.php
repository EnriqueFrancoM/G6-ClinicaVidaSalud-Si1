<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;

// CU01 boundary. Mensajes: ingresarCredenciales(usuario, clave).
// CU02: destruirSesion(), redirigir(), mostrarMensaje("Sesión cerrada").
class UI_IniciarSesion extends Controller
{
    public function ingresarCredenciales(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            if (Session::user() !== null) {
                (new UI_PanelPrincipal())->redirigir((string) Session::user()['rol']);
            }
            $this->view('seguridad/UI_IniciarSesion', [], 'auth');
            return;
        }

        $usuario = (string) $this->input('txtUsuario', '');
        $clave = (string) $this->input('txtContrasena', '');

        // CU01 paso 4: validar que los campos no estén vacíos (servidor).
        if ($usuario === '' || $clave === '') {
            $this->mostrarMensaje('Ingrese su usuario y contraseña.');
            $this->redirigir();
        }

        $autenticacion = new CtrlAutenticacion();
        if (!$autenticacion->autenticar($usuario, $clave)) {
            $this->mostrarMensaje($autenticacion->mensajeFallido);
            if ($autenticacion->segundosBloqueo > 0) {
                Session::flash('login_lock_seconds', (string) $autenticacion->segundosBloqueo);
            }
            if ($autenticacion->ultimoIntento) {
                Session::flash('login_last_attempt', '1');
            }
            $this->redirigir();
        }
    }

    public function destruirSesion(): void
    {
        Session::destroy();
        Session::start();
    }

    public function redirigir(): void
    {
        $this->redirect('/login');
    }

    public function mostrarMensaje(string $mensaje): void
    {
        $tipo = $mensaje === 'Sesión cerrada' ? 'success' : 'error';
        Session::flash($tipo, $mensaje);
    }
}
