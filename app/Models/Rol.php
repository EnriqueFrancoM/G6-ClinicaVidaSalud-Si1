<?php

namespace App\Models;

use App\Core\Model;

// Entidad Rol. CU01: obtenerPermisos(id_rol). CU03: listar(), obtenerRol(id_rol).
class Rol extends Model
{
    public function listar(): array
    {
        return $this->fetchAll('SELECT id_rol, nombre, descripcion, estado FROM rol ORDER BY id_rol');
    }

    public function obtenerRol(int $idRol): ?array
    {
        return $this->fetch(
            'SELECT id_rol, nombre, descripcion, estado FROM rol WHERE id_rol = :id_rol',
            ['id_rol' => $idRol]
        );
    }

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
