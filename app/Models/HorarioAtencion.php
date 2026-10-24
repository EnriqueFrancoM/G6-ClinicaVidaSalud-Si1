<?php

namespace App\Models;

use App\Core\Model;

// Entidad HorarioAtencion. CU09: listarPorMedico(), existeSolapamiento(), insertar(), actualizar(), desactivar().
class HorarioAtencion extends Model
{
    public function listarPorMedico(int $idMedico): array
    {
        $filas = $this->fetchAll(
            "SELECT id_horario, id_medico, dia_semana, TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio,
                    TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin, duracion_cita_min, estado
               FROM horario_atencion
              WHERE id_medico = :id
              ORDER BY dia_semana, hora_inicio",
            ['id' => $idMedico]
        );
        foreach ($filas as &$fila) {
            $fila['estado'] = in_array($fila['estado'], [true, 't', '1', 1], true);
        }

        return $filas;
    }

    // CU09 paso 5 / E2: ¿se cruza con otro horario activo del mismo médico y día?
    public function existeSolapamiento(int $idMedico, int $dia, string $inicio, string $fin, ?int $excluirId = null): bool
    {
        $fila = $this->fetch(
            'SELECT 1 FROM horario_atencion
              WHERE id_medico = :medico AND dia_semana = :dia AND estado = TRUE
                AND id_horario <> COALESCE(:excluir, 0)
                AND hora_inicio < CAST(:fin AS TIME) AND hora_fin > CAST(:inicio AS TIME)',
            ['medico' => $idMedico, 'dia' => $dia, 'inicio' => $inicio, 'fin' => $fin, 'excluir' => $excluirId]
        );
        return $fila !== null;
    }

    public function insertar(array $datos): int
    {
        $fila = $this->fetch(
            'INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita_min)
             VALUES (:medico, :dia, :inicio, :fin, :duracion) RETURNING id_horario',
            [
                'medico'   => $datos['id_medico'],
                'dia'      => $datos['dia_semana'],
                'inicio'   => $datos['hora_inicio'],
                'fin'      => $datos['hora_fin'],
                'duracion' => $datos['duracion_cita_min'],
            ]
        );
        return (int) $fila['id_horario'];
    }

    public function actualizar(int $idHorario, array $datos): void
    {
        $this->execute(
            'UPDATE horario_atencion
                SET dia_semana = :dia, hora_inicio = :inicio, hora_fin = :fin, duracion_cita_min = :duracion, estado = TRUE
              WHERE id_horario = :id',
            [
                'id'       => $idHorario,
                'dia'      => $datos['dia_semana'],
                'inicio'   => $datos['hora_inicio'],
                'fin'      => $datos['hora_fin'],
                'duracion' => $datos['duracion_cita_min'],
            ]
        );
    }

    // CU09 E3: el horario se desactiva; las citas no se eliminan.
    public function desactivar(int $idHorario): void
    {
        $this->execute('UPDATE horario_atencion SET estado = FALSE WHERE id_horario = :id', ['id' => $idHorario]);
    }
}
