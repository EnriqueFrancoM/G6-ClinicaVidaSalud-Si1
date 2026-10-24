<?php

namespace App\Models;

use App\Core\Model;

// Entidad MedicoEspecialidad. CU08: asignar(), quitar().
class MedicoEspecialidad extends Model
{
    public function asignar(int $idMedico, int $idEspecialidad): void
    {
        $this->execute(
            'INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (:medico, :especialidad) ON CONFLICT DO NOTHING',
            ['medico' => $idMedico, 'especialidad' => $idEspecialidad]
        );
    }

    public function quitar(int $idMedico, int $idEspecialidad): void
    {
        $this->execute(
            'DELETE FROM medico_especialidad WHERE id_medico = :medico AND id_especialidad = :especialidad',
            ['medico' => $idMedico, 'especialidad' => $idEspecialidad]
        );
    }
}
