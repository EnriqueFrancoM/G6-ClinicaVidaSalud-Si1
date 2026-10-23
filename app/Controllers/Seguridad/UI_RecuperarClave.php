<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;
use App\Services\CorreoBrevo;

class UI_RecuperarClave extends Controller
{
    public function solicitar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $control = new CtrlRecuperacion();
            if (!$control->solicitarCodigo((string) $this->input('txtUsuario', ''))) {
                if ((new CorreoBrevo())->configurado()) {
                    Session::flash('error', $control->mensaje);
                }
                $this->redirect('/recuperar');
            }
            Session::flash('success', $control->mensaje);
            $this->redirect('/recuperar/codigo');
        }

        $this->pantalla('solicitar');
    }

    public function ingresarCodigo(): void
    {
        $control = new CtrlRecuperacion();
        if (!$control->tieneCodigoPendiente()) {
            $this->redirect('/recuperar');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$control->verificarCodigo((string) $this->input('txtCodigo', ''))) {
                Session::flash('error', $control->mensaje);
                $this->redirect($control->tieneCodigoPendiente() ? '/recuperar/codigo' : '/recuperar');
            }
            $this->redirect('/recuperar/clave');
        }

        $this->pantalla('codigo', $control->correoEnmascarado());
    }

    public function definirClave(): void
    {
        $control = new CtrlRecuperacion();
        if (!$control->claveHabilitada()) {
            $this->redirect('/recuperar');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$control->definirClave((string) $this->input('txtContrasenaNueva', ''), (string) $this->input('txtContrasenaConfirmacion', ''))) {
                Session::flash('error', $control->mensaje);
                $this->redirect($control->claveHabilitada() ? '/recuperar/clave' : '/recuperar');
            }
            Session::flash('success', $control->mensaje);
            $this->redirect('/login');
        }

        $this->pantalla('clave');
    }

    private function pantalla(string $paso, string $correo = ''): void
    {
        $this->view('Seguridad/UI_RecuperarClave', [
            'paso'         => $paso,
            'correo'       => $correo,
            'correoListo'  => (new CorreoBrevo())->configurado(),
        ], 'auth');
    }
}
