<?php

namespace App\Controllers\Seguridad;

use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Usuario;

// CU03, solo el desbloqueo: cambiarEstado() lo hace el administrador.
class CtrlUsuario
{
    public function listarUsuarios(): array
    {
        return (new Usuario())->listar();
    }

    public function desbloquear(int $idUsuario): void
    {
        $usuarios = new Usuario();
        $fila = $usuarios->buscarPorId($idUsuario);
        if ($fila === null || $fila['estado']) {
            Session::flash('error', 'Esa cuenta no está bloqueada.');
            return;
        }

        $usuarios->cambiarEstado($idUsuario, true);
        $admin = Session::user();
        (new Bitacora())->registrar(
            (int) ($admin['id_usuario'] ?? 0),
            'DESBLOQUEO',
            'Seguridad',
            $fila['username']
        );
        Session::flash('success', 'Cuenta desbloqueada.');
    }
}
