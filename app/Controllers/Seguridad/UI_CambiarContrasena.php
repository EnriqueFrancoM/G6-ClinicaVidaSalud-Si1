<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;

// CU05 boundary. Mensajes: ingresar(actual, nueva, confirmacion), mostrarConfirmacion().
class UI_CambiarContrasena extends Controller
{
    public function ingresar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->view('seguridad/UI_CambiarContrasena', [
                'campoError'    => Session::flash('campo_error'),
                'primerIngreso' => (bool) (Session::user()['primer_ingreso'] ?? false),
            ]);
            return;
        }

        $control = new CtrlContrasena();
        $ok = $control->cambiarContrasena([
            'actual'       => (string) ($_POST['txtActual'] ?? ''),
            'nueva'        => (string) ($_POST['txtNueva'] ?? ''),
            'confirmacion' => (string) ($_POST['txtConfirmar'] ?? ''),
        ]);

        if (!$ok) {
            Session::flash('error', $control->mensaje);
            Session::flash('campo_error', (string) $control->campoError);
            $this->redirect('/cambiar-contrasena');
        }

        $this->mostrarConfirmacion($control->mensaje);
    }

    public function mostrarConfirmacion(string $mensaje): void
    {
        Session::flash('success', $mensaje);
        $this->redirect('/dashboard');
    }
}
