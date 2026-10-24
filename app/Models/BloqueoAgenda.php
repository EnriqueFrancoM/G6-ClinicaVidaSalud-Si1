<?php

namespace App\Models;

use App\Core\Model;

// Entidad BloqueoAgenda. CU09 («extend» Registrar bloqueo de agenda): listarPorMedico(), existeBloqueo(), insertar(), eliminar().
// id_medico NULL = feriado general (aplica a todos los médicos).
class BloqueoAgenda extends Model
{
    // Bloqueos de hoy en adelante del médico y feriados generales.
    public function listarPorMedico(int $idMedico): array
    {
        return $this->fetchAll(
            'SELECT id_bloqueo, id_medico, fecha, motivo
               FROM bloqueo_agenda
              WHERE (id_medico = :id OR id_medico IS NULL) AND fecha >= CURRENT_DATE
              ORDER BY fecha',
            ['id' => $idMedico]
        );
    }

    // ¿Ya existe un bloqueo ese día para el médico (o un feriado general ese día)?
    public function existeBloqueo(?int $idMedico, string $fecha): bool
    {
        $fila = $this->fetch(
            'SELECT 1 FROM bloqueo_agenda
              WHERE fecha = CAST(:fecha AS DATE)
                AND (id_medico IS NULL OR id_medico = :medico)',
            ['fecha' => $fecha, 'medico' => $idMedico ?? 0]
        );
        return $fila !== null;
    }

    public function insertar(?int $idMedico, string $fecha, string $motivo): void
    {
        $this->execute(
            'INSERT INTO bloqueo_agenda (id_medico, fecha, motivo) VALUES (:medico, :fecha, :motivo)',
            ['medico' => $idMedico, 'fecha' => $fecha, 'motivo' => $motivo]
        );
    }

    // Un bloqueo registrado por error se elimina físicamente (documento 6.3.2, Delete() n.º 1).
    public function eliminar(int $idBloqueo): ?array
    {
        return $this->fetch('DELETE FROM bloqueo_agenda WHERE id_bloqueo = :id RETURNING id_medico, fecha, motivo', ['id' => $idBloqueo]);
    }
}
