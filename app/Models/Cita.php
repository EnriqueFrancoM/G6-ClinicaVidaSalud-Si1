<?php

namespace App\Models;

use App\Core\Model;

// Entidad Cita. CU09: contarFuturasEnHorario(). CU10: historialPorPaciente().
class Cita extends Model
{
    // CU10 paso 9: historial de citas del paciente, de la más reciente a la más antigua.
    public function historialPorPaciente(int $idPaciente): array
    {
        return $this->fetchAll(
            "SELECT c.codigo, c.fecha, TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio, c.estado, c.motivo,
                    c.motivo_cancelacion, m.nombres || ' ' || m.apellidos AS medico, e.nombre AS especialidad
               FROM cita c
               JOIN medico m ON m.id_medico = c.id_medico
               JOIN especialidad e ON e.id_especialidad = c.id_especialidad
              WHERE c.id_paciente = :id
              ORDER BY c.fecha DESC, c.hora_inicio DESC",
            ['id' => $idPaciente]
        );
    }

    // CU09 E3: citas futuras (programadas o confirmadas) dentro de un horario que se modifica o desactiva.
    // Si se indica el nuevo rango, solo cuenta las que quedan fuera de él.
    public function contarFuturasEnHorario(array $horario, ?array $nuevo = null): int
    {
        $sql = "SELECT COUNT(*) AS total FROM cita
                 WHERE id_medico = :medico
                   AND estado IN ('PROGRAMADA', 'CONFIRMADA')
                   AND fecha >= CURRENT_DATE
                   AND EXTRACT(ISODOW FROM fecha) = :dia
                   AND hora_inicio >= CAST(:inicio AS TIME) AND hora_fin <= CAST(:fin AS TIME)";
        $params = [
            'medico' => $horario['id_medico'],
            'dia'    => $horario['dia_semana'],
            'inicio' => $horario['hora_inicio'],
            'fin'    => $horario['hora_fin'],
        ];
        if ($nuevo !== null) {
            $sql .= ' AND NOT (EXTRACT(ISODOW FROM fecha) = :nuevo_dia
                               AND hora_inicio >= CAST(:nuevo_inicio AS TIME) AND hora_fin <= CAST(:nuevo_fin AS TIME))';
            $params += ['nuevo_dia' => $nuevo['dia_semana'], 'nuevo_inicio' => $nuevo['hora_inicio'], 'nuevo_fin' => $nuevo['hora_fin']];
        }

        return (int) $this->fetch($sql, $params)['total'];
    }
}
