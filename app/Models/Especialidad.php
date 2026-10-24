<?php

namespace App\Models;

use App\Core\Model;

// Entidad Especialidad. CU07: listar(), existeNombreNormalizado(), insertar(), actualizar(), cambiarEstado().
class Especialidad extends Model
{
    public function listar(): array
    {
        $filas = $this->fetchAll('SELECT id_especialidad, nombre, descripcion, estado FROM especialidad ORDER BY id_especialidad');
        foreach ($filas as &$fila) {
            $fila['estado'] = in_array($fila['estado'], [true, 't', '1', 1], true);
        }

        return $filas;
    }

    // CU07 paso 5 / E1: compara sin espacios sobrantes y sin distinguir mayúsculas
    // (igual que el índice único ux_especialidad_nombre).
    public function existeNombreNormalizado(string $nombre, ?int $excluirId = null): bool
    {
        $fila = $this->fetch(
            'SELECT 1 FROM especialidad
              WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre)) AND id_especialidad <> COALESCE(:excluir, 0)',
            ['nombre' => $nombre, 'excluir' => $excluirId]
        );
        return $fila !== null;
    }

    public function insertar(array $datos): int
    {
        $fila = $this->fetch(
            'INSERT INTO especialidad (nombre, descripcion, estado) VALUES (:nombre, :descripcion, :estado) RETURNING id_especialidad',
            ['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'], 'estado' => $datos['estado'] ? 'true' : 'false']
        );
        return (int) $fila['id_especialidad'];
    }

    public function actualizar(int $idEspecialidad, array $datos): void
    {
        $this->execute(
            'UPDATE especialidad SET nombre = :nombre, descripcion = :descripcion, estado = :estado WHERE id_especialidad = :id',
            ['id' => $idEspecialidad, 'nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'], 'estado' => $datos['estado'] ? 'true' : 'false']
        );
    }

    // CU07 E2: las especialidades no se eliminan; se activan o desactivan.
    public function cambiarEstado(int $idEspecialidad, bool $activo): void
    {
        $this->execute(
            'UPDATE especialidad SET estado = :estado WHERE id_especialidad = :id',
            ['id' => $idEspecialidad, 'estado' => $activo ? 'true' : 'false']
        );
    }
}
