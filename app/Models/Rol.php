<?php

namespace App\Models;

use App\Core\Model;

// Entidad Rol. CU01: obtenerPermisos(id_rol). CU03: listar(), obtenerRol(id_rol).
// CU04: listar(), existeNombre(), insertar(), actualizar().
class Rol extends Model
{
    // CU04 E2: ¿existe otro rol con el mismo nombre?
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $fila = $this->fetch(
            'SELECT 1 FROM rol WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) AND id_rol <> COALESCE(:excluir, 0)',
            ['nombre' => $nombre, 'excluir' => $excluirId]
        );
        return $fila !== null;
    }

    public function insertar(array $datos): int
    {
        $fila = $this->fetch(
            'INSERT INTO rol (nombre, descripcion, estado) VALUES (:nombre, :descripcion, :estado) RETURNING id_rol',
            [
                'nombre'      => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'estado'      => $datos['estado'] ? 'true' : 'false',
            ]
        );
        return (int) $fila['id_rol'];
    }

    // CU04 E3: un rol no se elimina; se actualiza o se desactiva.
    public function actualizar(int $idRol, array $datos): void
    {
        $this->execute(
            'UPDATE rol SET nombre = :nombre, descripcion = :descripcion, estado = :estado WHERE id_rol = :id_rol',
            [
                'id_rol'      => $idRol,
                'nombre'      => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'estado'      => $datos['estado'] ? 'true' : 'false',
            ]
        );
    }

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
