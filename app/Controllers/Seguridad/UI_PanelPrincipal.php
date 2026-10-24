<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Cita;

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
        $usuario = Session::user() ?? [];
        $this->view('seguridad/UI_PanelPrincipal', [
            'usuario' => $usuario,
            'citas'   => (new Cita())->delDia((int) ($usuario['id_usuario'] ?? 0), (string) ($usuario['rol'] ?? '')),
        ]);
    }

    public function seleccionarCerrarSesion(): void
    {
        (new CtrlAutenticacion())->cerrarSesion();
    }
}
