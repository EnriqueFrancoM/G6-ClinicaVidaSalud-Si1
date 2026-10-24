<?php

namespace App\Models;

use App\Core\Model;

// Entidad Paciente. CU03: buscar() para el vínculo opcional con un usuario.
class Paciente extends Model
{
    public function buscar(): array
    {
        return $this->fetchAll(
            'SELECT id_paciente, id_usuario, codigo, nombres, apellidos
               FROM paciente
              WHERE estado = TRUE
              ORDER BY apellidos, nombres'
        );
    }
}
