<?php

namespace App\Models;

use App\Core\Model;

// Entidad ContactoEmergencia. CU10: guardar(contacto).
class ContactoEmergencia extends Model
{
    // Guarda el contacto de emergencia del paciente (reemplaza el anterior). Con $contacto = null lo quita.
    public function guardar(int $idPaciente, ?array $contacto): void
    {
        $this->execute('DELETE FROM contacto_emergencia WHERE id_paciente = :id', ['id' => $idPaciente]);
        if ($contacto === null) {
            return;
        }
        $this->execute(
            'INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono) VALUES (:id, :nombre, :parentesco, :telefono)',
            ['id' => $idPaciente, 'nombre' => $contacto['nombre'], 'parentesco' => $contacto['parentesco'], 'telefono' => $contacto['telefono']]
        );
    }
}
