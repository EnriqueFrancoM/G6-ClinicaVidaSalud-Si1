<?php

namespace App\Models;

use App\Core\Model;

// Entidad HorarioAtencion. CU09: listarPorMedico(), existeSolapamiento(), insertar(), actualizar(), desactivar().
// CU11: consultarDisponibilidad(médico, fecha). CU12: validarNuevoCupo(datos). CU13: consultarCupoLiberado(datos).
// CU15: consultarHorario(fecha). CU18: consultarCupos(médico, fecha). CU21: buscarCupoCompatible(datos).
// CU22: obtenerCapacidad(filtros).
class HorarioAtencion extends Model
{
    // Días que se revisan hacia adelante al buscar un cupo compatible (CU21).
    private const DIAS_BUSQUEDA = 30;

    // Cupos de un médico en una fecha según sus horarios activos y su duración de cita (RN05, RN08).
    // Cada cupo indica si está libre: sin bloqueo de agenda, sin cita pendiente (RN07, RN09) y no pasado (RN06).
    // $excluirCita: la cita que se reprograma no ocupa su propio cupo.
    public function cuposDelDia(int $idMedico, string $fecha, ?int $excluirCita = null): array
    {
        if ((new BloqueoAgenda())->existeBloqueo($idMedico, $fecha)) {
            return [];
        }

        $horarios = $this->fetchAll(
            "SELECT TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio, TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin, duracion_cita_min
               FROM horario_atencion
              WHERE id_medico = :medico AND estado = TRUE AND dia_semana = EXTRACT(ISODOW FROM CAST(:fecha AS DATE))
              ORDER BY hora_inicio",
            ['medico' => $idMedico, 'fecha' => $fecha]
        );
        $ocupadas = $this->fetchAll(
            "SELECT TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio, TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin
               FROM cita
              WHERE id_medico = :medico AND fecha = CAST(:fecha AS DATE) AND estado <> 'CANCELADA'
                AND id_cita <> COALESCE(:excluir, 0)",
            ['medico' => $idMedico, 'fecha' => $fecha, 'excluir' => $excluirCita]
        );

        $minutos = fn (string $hora) => (int) substr($hora, 0, 2) * 60 + (int) substr($hora, 3, 2);
        $ahora = $fecha === date('Y-m-d') ? (int) date('G') * 60 + (int) date('i') : ($fecha < date('Y-m-d') ? PHP_INT_MAX : -1);

        $cupos = [];
        foreach ($horarios as $h) {
            $duracion = (int) $h['duracion_cita_min'];
            for ($inicio = $minutos($h['hora_inicio']); $inicio + $duracion <= $minutos($h['hora_fin']); $inicio += $duracion) {
                $fin = $inicio + $duracion;
                $libre = $inicio > $ahora;
                foreach ($ocupadas as $c) {
                    if ($minutos($c['hora_inicio']) < $fin && $minutos($c['hora_fin']) > $inicio) {
                        $libre = false;
                        break;
                    }
                }
                $cupos[] = [
                    'hora_inicio' => sprintf('%02d:%02d', intdiv($inicio, 60), $inicio % 60),
                    'hora_fin'    => sprintf('%02d:%02d', intdiv($fin, 60), $fin % 60),
                    'libre'       => $libre,
                ];
            }
        }

        return $cupos;
    }

    // CU11 mensaje consultarDisponibilidad(médico, fecha): solo los horarios libres.
    public function consultarDisponibilidad(int $idMedico, string $fecha): array
    {
        return array_values(array_filter($this->cuposDelDia($idMedico, $fecha), fn ($c) => $c['libre']));
    }

    // CU18 mensaje consultarCupos(médico, fecha): los cupos libres que ve el paciente en el portal.
    public function consultarCupos(int $idMedico, string $fecha): array
    {
        return $this->consultarDisponibilidad($idMedico, $fecha);
    }

    // CU12 mensaje validarNuevoCupo(datos): el nuevo horario es un cupo del médico y está libre
    // (sin contar la propia cita que se reprograma).
    public function validarNuevoCupo(array $datos): ?array
    {
        foreach ($this->cuposDelDia((int) $datos['id_medico'], $datos['fecha'], (int) ($datos['id_cita'] ?? 0) ?: null) as $cupo) {
            if ($cupo['hora_inicio'] === $datos['hora_inicio']) {
                return $cupo['libre'] ? $cupo : null;
            }
        }

        return null;
    }

    // CU13 mensaje consultarCupoLiberado(datos): tras cancelar, ¿el cupo quedó libre y sigue vigente?
    // Si es así se ofrece a la lista de espera (RN17).
    public function consultarCupoLiberado(array $datos): ?array
    {
        foreach ($this->cuposDelDia((int) $datos['id_medico'], $datos['fecha']) as $cupo) {
            if ($cupo['hora_inicio'] === $datos['hora_inicio'] && $cupo['libre']) {
                return ['id_medico' => (int) $datos['id_medico'], 'fecha' => $datos['fecha']] + $cupo;
            }
        }

        return null;
    }

    // CU15 mensaje consultarHorario(fecha): rangos de atención del médico ese día.
    public function consultarHorario(int $idMedico, string $fecha): array
    {
        return $this->fetchAll(
            "SELECT TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio, TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin, duracion_cita_min
               FROM horario_atencion
              WHERE id_medico = :medico AND estado = TRUE AND dia_semana = EXTRACT(ISODOW FROM CAST(:fecha AS DATE))
              ORDER BY hora_inicio",
            ['medico' => $idMedico, 'fecha' => $fecha]
        );
    }

    // CU21 mensaje buscarCupoCompatible(datos): primer cupo libre de la especialidad (y del médico, si se pidió)
    // dentro del rango de fechas preferido. Devuelve id_medico, fecha, hora_inicio y hora_fin, o null.
    public function buscarCupoCompatible(array $datos): ?array
    {
        $medicos = $this->fetchAll(
            'SELECT m.id_medico
               FROM medico m
               JOIN medico_especialidad me ON me.id_medico = m.id_medico
              WHERE me.id_especialidad = :especialidad AND m.estado = TRUE
                AND (CAST(:medico AS INT) IS NULL OR m.id_medico = CAST(:medico AS INT))
              ORDER BY m.codigo',
            ['especialidad' => (int) $datos['id_especialidad'], 'medico' => !empty($datos['id_medico']) ? (int) $datos['id_medico'] : null]
        );
        if ($medicos === []) {
            return null;
        }

        $desde = max(date('Y-m-d'), (string) ($datos['fecha_desde'] ?? '') ?: date('Y-m-d'));
        $limite = date('Y-m-d', strtotime('+' . self::DIAS_BUSQUEDA . ' days'));
        $hasta = !empty($datos['fecha_hasta']) ? min($datos['fecha_hasta'], $limite) : $limite;

        for ($dia = $desde; $dia <= $hasta; $dia = date('Y-m-d', strtotime($dia . ' +1 day'))) {
            foreach ($medicos as $m) {
                $libres = $this->consultarDisponibilidad((int) $m['id_medico'], $dia);
                if ($libres !== []) {
                    return ['id_medico' => (int) $m['id_medico'], 'fecha' => $dia,
                        'hora_inicio' => $libres[0]['hora_inicio'], 'hora_fin' => $libres[0]['hora_fin']];
                }
            }
        }

        return null;
    }

    // CU22 mensaje obtenerCapacidad(filtros): cupos ofrecidos por médico en el período (días del período ×
    // horarios activos ÷ duración de la cita). Filtros: desde, hasta, id_medico, id_especialidad.
    public function obtenerCapacidad(array $filtros): array
    {
        return $this->fetchAll(
            "SELECT h.id_medico, SUM(FLOOR(EXTRACT(EPOCH FROM (h.hora_fin - h.hora_inicio)) / 60 / h.duracion_cita_min))::int AS cupos
               FROM horario_atencion h
               JOIN medico m ON m.id_medico = h.id_medico AND m.estado = TRUE
               JOIN generate_series(CAST(:desde AS DATE), CAST(:hasta AS DATE), INTERVAL '1 day') AS d(dia)
                 ON EXTRACT(ISODOW FROM d.dia) = h.dia_semana
              WHERE h.estado = TRUE
                AND (CAST(:medico AS INT) IS NULL OR h.id_medico = CAST(:medico AS INT))
                AND (CAST(:especialidad AS INT) IS NULL OR EXISTS (
                     SELECT 1 FROM medico_especialidad x WHERE x.id_medico = h.id_medico AND x.id_especialidad = CAST(:especialidad AS INT)))
           GROUP BY h.id_medico",
            [
                'desde'        => $filtros['desde'],
                'hasta'        => $filtros['hasta'],
                'medico'       => !empty($filtros['id_medico']) ? (int) $filtros['id_medico'] : null,
                'especialidad' => !empty($filtros['id_especialidad']) ? (int) $filtros['id_especialidad'] : null,
            ]
        );
    }

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
