<?php

namespace App\Models;

use App\Core\Model;

// Entidad HistorialEstadoCita (tabla historial_estado_cita).
// CU12: registrarReprogramación(datos). CU13: registrarCancelación(datos).
// CU14: registrarTransición(datos). CU16: consultarCambios(citas).
class HistorialEstadoCita extends Model
{
    // CU11 / CU18: estado inicial de la cita recién registrada.
    public function registrarInicial(int $idCita, int $idUsuario, string $observacion): void
    {
        $this->insertar($idCita, $idUsuario, null, 'PROGRAMADA', $observacion);
    }

    // CU12 mensaje registrarReprogramación(datos): el estado no cambia; se guarda el horario anterior y el nuevo.
    public function registrarReprogramacion(array $datos): void
    {
        $this->insertar($datos['id_cita'], $datos['id_usuario'], $datos['estado'], $datos['estado'], $datos['observacion']);
    }

    // CU13 mensaje registrarCancelación(datos): estado anterior → CANCELADA con el motivo.
    public function registrarCancelacion(array $datos): void
    {
        $this->insertar($datos['id_cita'], $datos['id_usuario'], $datos['estado_anterior'], 'CANCELADA', $datos['motivo']);
    }

    // CU14 mensaje registrarTransición(datos): usuario, estado anterior, nuevo y observación.
    public function registrarTransicion(array $datos): void
    {
        $this->insertar($datos['id_cita'], $datos['id_usuario'], $datos['estado_anterior'], $datos['estado_nuevo'], $datos['observacion'] ?? null);
    }

    // CU16 mensaje consultarCambios(citas): cambios de estado de cada cita, agrupados por id_cita.
    public function consultarCambios(array $idsCita): array
    {
        if ($idsCita === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($idsCita), '?'));
        $stmt = $this->db->prepare(
            "SELECT h.id_cita, h.estado_anterior, h.estado_nuevo, h.observacion,
                    TO_CHAR(h.fecha_hora, 'DD/MM/YYYY HH24:MI') AS fecha_hora, u.username AS usuario
               FROM historial_estado_cita h
               JOIN usuario u ON u.id_usuario = h.id_usuario
              WHERE h.id_cita IN ($marcas)
              ORDER BY h.fecha_hora, h.id_historial"
        );
        $stmt->execute(array_map('intval', array_values($idsCita)));

        $cambios = [];
        foreach ($stmt->fetchAll() as $fila) {
            $cambios[(int) $fila['id_cita']][] = $fila;
        }

        return $cambios;
    }

    private function insertar(int $idCita, int $idUsuario, ?string $anterior, string $nuevo, ?string $observacion): void
    {
        $this->execute(
            'INSERT INTO historial_estado_cita (id_cita, id_usuario, estado_anterior, estado_nuevo, observacion)
             VALUES (:cita, :usuario, :anterior, :nuevo, :observacion)',
            [
                'cita'        => $idCita,
                'usuario'     => $idUsuario,
                'anterior'    => $anterior,
                'nuevo'       => $nuevo,
                'observacion' => $observacion !== null ? mb_substr($observacion, 0, 200) : null,
            ]
        );
    }
}
