<?php

namespace App\Models;

use App\Core\Model;

// Entidad Medico. CU03: listar() para el vínculo opcional con un usuario.
class Medico extends Model
{
    public function listar(): array
    {
        return $this->fetchAll(
            'SELECT id_medico, id_usuario, codigo, nombres, apellidos
               FROM medico
              WHERE estado = TRUE
              ORDER BY apellidos, nombres'
        );
    }
}
