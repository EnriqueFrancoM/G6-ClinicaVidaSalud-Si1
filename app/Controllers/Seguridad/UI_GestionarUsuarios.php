<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;

// CU03 boundary. Mensaje usado aquí: abrirUsuarios().
class UI_GestionarUsuarios extends Controller
{
    public function abrirUsuarios(): void
    {
        $this->view('Seguridad/UI_GestionarUsuarios', [
            'usuarios' => (new CtrlUsuario())->listarUsuarios(),
        ]);
    }

    public function desbloquear(): void
    {
        (new CtrlUsuario())->desbloquear((int) $this->input('id_usuario', 0));
        $this->redirect('/usuarios');
    }
}
