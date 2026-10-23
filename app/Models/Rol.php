<?php

namespace App\Models;

use App\Core\Model;

// Entidad Rol. CU01: obtenerPermisos(id_rol).
class Rol extends Model
{
    public function obtenerPermisos(int $idRol): array
    {
        $rows = $this->fetchAll(
            'SELECT p.codigo
               FROM rol_permiso rp
               JOIN permiso p ON p.id_permiso = rp.id_permiso
              WHERE rp.id_rol = :id_rol',
            ['id_rol' => $idRol]
        );
        return array_column($rows, 'codigo');
    }
}
