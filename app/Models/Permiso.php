<?php

namespace App\Models;

use App\Core\Model;

// Entidad Permiso. CU04: listarPorModulo().
class Permiso extends Model
{
    // Devuelve los permisos agrupados: ['Seguridad' => [...], 'Citas' => [...], ...].
    public function listarPorModulo(): array
    {
        $filas = $this->fetchAll('SELECT id_permiso, codigo, nombre, modulo FROM permiso ORDER BY id_permiso');
        $grupos = [];
        foreach ($filas as $fila) {
            $grupos[$fila['modulo']][] = $fila;
        }

        return $grupos;
    }
}
