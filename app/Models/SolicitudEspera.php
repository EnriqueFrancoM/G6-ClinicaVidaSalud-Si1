<?php

namespace App\Models;

use App\Core\Model;

// Entidad SolicitudEspera (tabla solicitud_espera). CU21: registrarSinDuplicado(datos).
// La cola se ordena por fecha_ingreso; al liberarse un cupo compatible se ofrece al primero (RN17).
class SolicitudEspera extends Model
{
    public const ESTADOS = ['ACTIVA' => 'En espera', 'OFERTADA' => 'Con oferta', 'ASIGNADA' => 'Asignada', 'CANCELADA' => 'Cancelada'];

    // RF22: plazo de respuesta de una oferta.
    public const HORAS_OFERTA = 2;

    private const SELECT = "SELECT s.*, TO_CHAR(s.oferta_hora_inicio, 'HH24:MI') AS oferta_inicio,
                    TO_CHAR(s.oferta_hora_fin, 'HH24:MI') AS oferta_fin,
                    TO_CHAR(s.oferta_hasta, 'DD/MM/YYYY HH24:MI') AS oferta_vence,
                    TO_CHAR(s.fecha_ingreso, 'DD/MM/YYYY HH24:MI') AS ingreso,
                    p.codigo AS paciente_codigo, p.nombres || ' ' || p.apellidos AS paciente, p.telefono,
                    e.nombre AS especialidad,
                    m.nombres || ' ' || m.apellidos AS medico_preferido,
                    om.nombres || ' ' || om.apellidos AS oferta_medico,
                    c.codigo AS cita_codigo
               FROM solicitud_espera s
               JOIN paciente p ON p.id_paciente = s.id_paciente
               JOIN especialidad e ON e.id_especialidad = s.id_especialidad
          LEFT JOIN medico m ON m.id_medico = s.id_medico
          LEFT JOIN medico om ON om.id_medico = s.oferta_id_medico
          LEFT JOIN cita c ON c.id_cita = s.id_cita";

    // CU21 mensaje registrarSinDuplicado(datos): una sola solicitud vigente por paciente y especialidad.
    // Devuelve el id de la solicitud, o null si ya existe una vigente.
    public function registrarSinDuplicado(array $datos): ?int
    {
        $existe = $this->fetch(
            "SELECT 1 FROM solicitud_espera WHERE id_paciente = :paciente AND id_especialidad = :especialidad
                AND estado IN ('ACTIVA', 'OFERTADA')",
            ['paciente' => $datos['id_paciente'], 'especialidad' => $datos['id_especialidad']]
        );
        if ($existe !== null) {
            return null;
        }

        $fila = $this->fetch(
            'INSERT INTO solicitud_espera (id_paciente, id_especialidad, id_medico, fecha_desde, fecha_hasta, preferencias, id_usuario_registro)
             VALUES (:paciente, :especialidad, :medico, :desde, :hasta, :preferencias, :usuario)
             RETURNING id_solicitud',
            [
                'paciente'     => $datos['id_paciente'],
                'especialidad' => $datos['id_especialidad'],
                'medico'       => $datos['id_medico'],
                'desde'        => $datos['fecha_desde'],
                'hasta'        => $datos['fecha_hasta'],
                'preferencias' => $datos['preferencias'],
                'usuario'      => $datos['id_usuario_registro'],
            ]
        );

        return (int) $fila['id_solicitud'];
    }

    public function buscarPorId(int $idSolicitud): ?array
    {
        return $this->fetch(self::SELECT . ' WHERE s.id_solicitud = :id', ['id' => $idSolicitud]);
    }

    // Cola de espera: vigentes primero, en orden de ingreso.
    public function listar(?string $estado = null): array
    {
        $filtro = $estado ? 'WHERE s.estado = :estado' : "WHERE s.estado IN ('ACTIVA', 'OFERTADA')";

        return $this->fetchAll(
            self::SELECT . " $filtro ORDER BY CASE s.estado WHEN 'OFERTADA' THEN 0 WHEN 'ACTIVA' THEN 1 ELSE 2 END, s.fecha_ingreso, s.id_solicitud",
            $estado ? ['estado' => $estado] : []
        );
    }

    // RN17: la primera solicitud ACTIVA compatible con el cupo liberado (especialidad que atiende el médico,
    // médico preferido y rango de fechas). Se bloquea la fila para que dos liberaciones no la tomen a la vez.
    public function primeraCompatible(array $cupo, array $excluir = []): ?array
    {
        $excluir = array_map('intval', $excluir) ?: [0];

        return $this->fetch(
            'SELECT s.id_solicitud, s.id_paciente, s.id_especialidad
               FROM solicitud_espera s
              WHERE s.estado = \'ACTIVA\'
                AND s.id_especialidad IN (SELECT id_especialidad FROM medico_especialidad WHERE id_medico = :medico)
                AND (s.id_medico IS NULL OR s.id_medico = :medico)
                AND (s.fecha_desde IS NULL OR s.fecha_desde <= CAST(:fecha AS DATE))
                AND (s.fecha_hasta IS NULL OR s.fecha_hasta >= CAST(:fecha AS DATE))
                AND s.id_solicitud NOT IN (' . implode(',', $excluir) . ')
              ORDER BY s.fecha_ingreso, s.id_solicitud
              LIMIT 1
                FOR UPDATE SKIP LOCKED',
            ['medico' => $cupo['id_medico'], 'fecha' => $cupo['fecha']]
        );
    }

    // RF22: ofrece el cupo al solicitante con plazo de respuesta.
    public function ofertar(int $idSolicitud, array $cupo): void
    {
        $this->execute(
            "UPDATE solicitud_espera
                SET estado = 'OFERTADA', oferta_id_medico = :medico, oferta_fecha = :fecha,
                    oferta_hora_inicio = :inicio, oferta_hora_fin = :fin,
                    oferta_hasta = LOCALTIMESTAMP + make_interval(hours => :horas)
              WHERE id_solicitud = :id",
            ['id' => $idSolicitud, 'medico' => $cupo['id_medico'], 'fecha' => $cupo['fecha'],
                'inicio' => $cupo['hora_inicio'], 'fin' => $cupo['hora_fin'], 'horas' => self::HORAS_OFERTA]
        );
    }

    // Bloquea la oferta vigente para que dos aceptaciones no la tomen a la vez.
    public function bloquearOfertada(int $idSolicitud): ?array
    {
        return $this->fetch(
            self::SELECT . " WHERE s.id_solicitud = :id AND s.estado = 'OFERTADA' FOR UPDATE OF s",
            ['id' => $idSolicitud]
        );
    }

    public function marcarAsignada(int $idSolicitud, int $idCita): bool
    {
        return $this->execute(
            "UPDATE solicitud_espera SET estado = 'ASIGNADA', id_cita = :cita, oferta_hasta = NULL
              WHERE id_solicitud = :id AND estado = 'OFERTADA'",
            ['id' => $idSolicitud, 'cita' => $idCita]
        ) === 1;
    }

    // PN13: si la oferta no se concreta, la solicitud vuelve a la cola conservando su orden de ingreso.
    public function volverACola(int $idSolicitud): void
    {
        $this->execute(
            "UPDATE solicitud_espera
                SET estado = 'ACTIVA', oferta_id_medico = NULL, oferta_fecha = NULL,
                    oferta_hora_inicio = NULL, oferta_hora_fin = NULL, oferta_hasta = NULL
              WHERE id_solicitud = :id AND estado = 'OFERTADA'",
            ['id' => $idSolicitud]
        );
    }

    public function cancelar(int $idSolicitud): bool
    {
        return $this->execute(
            "UPDATE solicitud_espera SET estado = 'CANCELADA', oferta_hasta = NULL
              WHERE id_solicitud = :id AND estado IN ('ACTIVA', 'OFERTADA')",
            ['id' => $idSolicitud]
        ) === 1;
    }

    // Ofertas cuyo plazo de respuesta venció.
    public function ofertasVencidas(): array
    {
        return $this->fetchAll(
            "SELECT id_solicitud, oferta_id_medico AS id_medico, oferta_fecha AS fecha,
                    TO_CHAR(oferta_hora_inicio, 'HH24:MI') AS hora_inicio, TO_CHAR(oferta_hora_fin, 'HH24:MI') AS hora_fin
               FROM solicitud_espera
              WHERE estado = 'OFERTADA' AND oferta_hasta < LOCALTIMESTAMP
                FOR UPDATE SKIP LOCKED"
        );
    }

    // CU22 (RF25): uso de la lista de espera en el período (por fecha de ingreso).
    public function contarPorEstado(array $filtros): array
    {
        $fila = $this->fetch(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE estado = 'ASIGNADA') AS asignadas,
                    COUNT(*) FILTER (WHERE estado IN ('ACTIVA', 'OFERTADA')) AS vigentes,
                    COUNT(*) FILTER (WHERE estado = 'CANCELADA') AS canceladas
               FROM solicitud_espera
              WHERE fecha_ingreso >= CAST(:desde AS DATE) AND fecha_ingreso < CAST(:hasta AS DATE) + 1
                AND (CAST(:especialidad AS INT) IS NULL OR id_especialidad = CAST(:especialidad AS INT))",
            ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta'],
                'especialidad' => !empty($filtros['id_especialidad']) ? (int) $filtros['id_especialidad'] : null]
        );

        return array_map('intval', $fila);
    }
}
