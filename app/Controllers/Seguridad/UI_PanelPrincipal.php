<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;

// CU01 boundary. Mensajes: redirigir(rol), mostrarMenuSegunRol().
// CU02: seleccionarCerrarSesion().
class UI_PanelPrincipal extends Controller
{
    public function redirigir(string $rol): void
    {
        $usuario = Session::user() ?? [];
        $usuario['rol'] = $rol;
        Session::set('usuario', $usuario);
        $this->redirect('/dashboard');
    }

    public function mostrarMenuSegunRol(): void
    {
        $this->view('Seguridad/UI_PanelPrincipal', [
            'usuario' => Session::user() ?? [],
        ]);
    }

    public function seleccionarCerrarSesion(): void
    {
        (new CtrlAutenticacion())->cerrarSesion();
    }
}
