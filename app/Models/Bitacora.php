<?php

namespace App\Models;

use App\Core\Model;

// Entidad Bitacora. CU01: registrar("LOGIN", ip). CU02: registrar("LOGOUT", ip).
class Bitacora extends Model
{
    public function registrar(?int $idUsuario, string $accion, string $modulo, ?string $detalle = null): void
    {
        $this->execute(
            'INSERT INTO bitacora (id_usuario, accion, modulo, detalle, ip)
             VALUES (:id_usuario, :accion, :modulo, :detalle, :ip)',
            [
                'id_usuario' => $idUsuario,
                'accion'     => $accion,
                'modulo'     => $modulo,
                'detalle'    => $detalle,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }
}
