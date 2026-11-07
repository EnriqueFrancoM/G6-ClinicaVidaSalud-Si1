<?php

namespace App\Models;

use App\Core\Model;

// Entidad BloqueoAgenda. CU09 («extend» Registrar bloqueo de agenda): listarPorMedico(), existeBloqueo(), insertar(), eliminar().
// CU22: descontarBloqueos(filtros).
// id_medico NULL = feriado general (aplica a todos los médicos).
class BloqueoAgenda extends Model
{
    // Bloqueos y feriados desde una fecha. id_medico null es un día festivo de toda la clínica.
    public function listarDesde(string $desde): array
    {
        return $this->fetchAll(
            "SELECT b.id_bloqueo, b.id_medico, b.fecha::text AS fecha, b.motivo,
                    CASE WHEN b.id_medico IS NULL THEN 'festivo' ELSE 'medico' END AS alcance,
                    m.codigo AS medico_codigo, m.nombres || ' ' || m.apellidos AS medico
               FROM bloqueo_agenda b
          LEFT JOIN medico m ON m.id_medico = b.id_medico
              WHERE b.fecha >= CAST(:desde AS DATE)
              ORDER BY b.fecha, b.id_medico NULLS FIRST",
            ['desde' => $desde]
        );
    }

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

    // CU22 mensaje descontarBloqueos(filtros): cupos que no se ofrecieron por bloqueos o feriados en el período,
    // por médico. Se restan de la capacidad de HorarioAtencion.obtenerCapacidad().
    public function descontarBloqueos(array $filtros): array
    {
        return $this->fetchAll(
            "SELECT h.id_medico, SUM(FLOOR(EXTRACT(EPOCH FROM (h.hora_fin - h.hora_inicio)) / 60 / h.duracion_cita_min))::int AS cupos
               FROM horario_atencion h
               JOIN medico m ON m.id_medico = h.id_medico AND m.estado = TRUE
               JOIN (SELECT DISTINCT b.fecha, b.id_medico FROM bloqueo_agenda b
                      WHERE b.fecha BETWEEN CAST(:desde AS DATE) AND CAST(:hasta AS DATE)) b
                 ON EXTRACT(ISODOW FROM b.fecha) = h.dia_semana AND (b.id_medico IS NULL OR b.id_medico = h.id_medico)
              WHERE h.estado = TRUE
                AND (CAST(:medico AS INT) IS NULL OR h.id_medico = CAST(:medico AS INT))
                AND (CAST(:especialidad AS INT) IS NULL OR EXISTS (
                     SELECT 1 FROM medico_especialidad x WHERE x.id_medico = h.id_medico AND x.id_especialidad = CAST(:especialidad AS INT)))
                -- un feriado general y un bloqueo propio el mismo día no se descuentan dos veces
                AND NOT (b.id_medico IS NOT NULL AND EXISTS (
                     SELECT 1 FROM bloqueo_agenda g WHERE g.fecha = b.fecha AND g.id_medico IS NULL))
           GROUP BY h.id_medico",
            [
                'desde'        => $filtros['desde'],
                'hasta'        => $filtros['hasta'],
                'medico'       => !empty($filtros['id_medico']) ? (int) $filtros['id_medico'] : null,
                'especialidad' => !empty($filtros['id_especialidad']) ? (int) $filtros['id_especialidad'] : null,
            ]
        );
    }

    // Un bloqueo registrado por error se elimina físicamente (documento 6.3.2, Delete() n.º 1).
    public function eliminar(int $idBloqueo): ?array
    {
        return $this->fetch('DELETE FROM bloqueo_agenda WHERE id_bloqueo = :id RETURNING id_medico, fecha, motivo', ['id' => $idBloqueo]);
    }
}
