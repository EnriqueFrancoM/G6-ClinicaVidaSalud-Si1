<?php

namespace App\Models;

use App\Core\Model;

// Entidad RolPermiso. CU04: asignar(), revocar().
class RolPermiso extends Model
{
    public function asignar(int $idRol, int $idPermiso): void
    {
        $this->execute(
            'INSERT INTO rol_permiso (id_rol, id_permiso) VALUES (:id_rol, :id_permiso) ON CONFLICT DO NOTHING',
            ['id_rol' => $idRol, 'id_permiso' => $idPermiso]
        );
    }

    public function revocar(int $idRol, int $idPermiso): void
    {
        $this->execute(
            'DELETE FROM rol_permiso WHERE id_rol = :id_rol AND id_permiso = :id_permiso',
            ['id_rol' => $idRol, 'id_permiso' => $idPermiso]
        );
    }
}
