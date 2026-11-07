<?php

namespace App\Models;

use App\Core\Model;
use App\Services\CanalRecordatorio;

// Entidad Recordatorio. CU20: buscarPendientes(instante), marcarResultado().
// Canal WHATSAPP si hay teléfono y la API está configurada; si no, EMAIL; si no hay correo, SISTEMA.
class Recordatorio extends Model
{
    public const ESTADOS = ['PENDIENTE' => 'Pendiente', 'ENVIADO' => 'Enviado', 'FALLIDO' => 'Fallido'];

    public const CANALES = ['EMAIL' => 'Correo', 'WHATSAPP' => 'WhatsApp', 'SISTEMA' => 'Portal'];

    // Horas antes de la cita en que se envía el recordatorio (RF20).
    public const HORAS_ANTES = 24;

    // CU11 / CU18: programa el recordatorio de una cita nueva. Si ya pasó el momento, queda para la próxima ejecución.
    public function programar(int $idCita, string $fecha, string $horaInicio, ?string $email): void
    {
        $fila = $this->fetch(
            "SELECT NULLIF(TRIM(p.email), '') AS email, NULLIF(TRIM(p.telefono), '') AS telefono
               FROM cita c
               JOIN paciente p ON p.id_paciente = c.id_paciente
              WHERE c.id_cita = :cita",
            ['cita' => $idCita]
        ) ?: [];
        $emailContacto = trim((string) ($email ?? ''));
        if ($emailContacto === '') {
            $emailContacto = trim((string) ($fila['email'] ?? ''));
        }
        $telefono = trim((string) ($fila['telefono'] ?? ''));
        $canal = CanalRecordatorio::elegir(
            $emailContacto !== '' ? $emailContacto : null,
            $telefono !== '' ? $telefono : null
        );
        $this->execute(
            "INSERT INTO recordatorio (id_cita, canal, fecha_programada)
             VALUES (:cita, :canal, GREATEST(LOCALTIMESTAMP, CAST(:fecha AS DATE) + CAST(:hora AS TIME) - make_interval(hours => :horas)))",
            ['cita' => $idCita, 'canal' => $canal, 'fecha' => $fecha, 'hora' => $horaInicio, 'horas' => self::HORAS_ANTES]
        );
    }

    // CU12: al reprogramar, el recordatorio pendiente sigue al nuevo horario.
    // Si ya se envió o falló, se crea uno nuevo: el aviso anterior no cubre el horario nuevo.
    public function reprogramar(int $idCita, string $fecha, string $horaInicio): void
    {
        $actualizados = $this->execute(
            "UPDATE recordatorio
                SET fecha_programada = GREATEST(LOCALTIMESTAMP, CAST(:fecha AS DATE) + CAST(:hora AS TIME) - make_interval(hours => :horas))
              WHERE id_cita = :cita AND estado_envio = 'PENDIENTE'",
            ['cita' => $idCita, 'fecha' => $fecha, 'hora' => $horaInicio, 'horas' => self::HORAS_ANTES]
        );
        if ($actualizados > 0) {
            return;
        }

        $this->programar($idCita, $fecha, $horaInicio, null);
    }

    // CU20 mensaje buscarPendientes(instante): pendientes cuyo momento ya llegó. FOR UPDATE SKIP LOCKED:
    // dos ejecuciones simultáneas no toman el mismo recordatorio (PN12: no duplicar el envío).
    public function buscarPendientes(string $instante): array
    {
        return $this->fetchAll(
            "SELECT id_recordatorio, id_cita, canal, fecha_programada
               FROM recordatorio
              WHERE estado_envio = 'PENDIENTE' AND fecha_programada <= CAST(:instante AS TIMESTAMP)
              ORDER BY fecha_programada, id_recordatorio
                FOR UPDATE SKIP LOCKED",
            ['instante' => $instante]
        );
    }

    // CU20 paso 6: registra el resultado del intento (ENVIADO o FALLIDO).
    public function marcarResultado(int $idRecordatorio, string $estado, bool $reintento = false): void
    {
        $condicion = $reintento
            ? "estado_envio IN ('PENDIENTE', 'FALLIDO')"
            : "estado_envio = 'PENDIENTE'";
        $this->execute(
            "UPDATE recordatorio SET estado_envio = :estado WHERE id_recordatorio = :id AND $condicion",
            ['id' => $idRecordatorio, 'estado' => $estado]
        );
    }

    public function obtener(int $idRecordatorio): ?array
    {
        return $this->fetch(
            'SELECT id_recordatorio, id_cita, canal, estado_envio
               FROM recordatorio
              WHERE id_recordatorio = :id',
            ['id' => $idRecordatorio]
        );
    }

    public function bloquear(int $idRecordatorio): ?array
    {
        return $this->fetch(
            'SELECT id_recordatorio, id_cita, canal, estado_envio
               FROM recordatorio
              WHERE id_recordatorio = :id
                FOR UPDATE',
            ['id' => $idRecordatorio]
        );
    }

    // Seguimiento de recordatorios (PN12): últimos recordatorios con su cita y paciente.
    public function listar(?string $estado = null): array
    {
        $filtro = $estado ? 'WHERE r.estado_envio = :estado' : '';

        return $this->fetchAll(
            "SELECT r.id_recordatorio, r.canal, TO_CHAR(r.fecha_programada, 'DD/MM/YYYY HH24:MI') AS fecha_programada,
                    r.estado_envio, c.codigo, c.fecha, TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio, c.estado AS estado_cita,
                    p.nombres || ' ' || p.apellidos AS paciente, p.email
               FROM recordatorio r
               JOIN cita c ON c.id_cita = r.id_cita
               JOIN paciente p ON p.id_paciente = c.id_paciente
               $filtro
              ORDER BY r.fecha_programada DESC, r.id_recordatorio DESC
              LIMIT 200",
            $estado ? ['estado' => $estado] : []
        );
    }

    // Avisos del canal SISTEMA ya entregados al paciente (se muestran en Mis citas).
    public function avisosDelPaciente(int $idPaciente): array
    {
        return $this->fetchAll(
            "SELECT c.codigo, c.fecha, TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio, c.estado,
                    m.nombres || ' ' || m.apellidos AS medico
               FROM recordatorio r
               JOIN cita c ON c.id_cita = r.id_cita
               JOIN medico m ON m.id_medico = c.id_medico
              WHERE c.id_paciente = :paciente AND r.canal = 'SISTEMA' AND r.estado_envio = 'ENVIADO'
                AND c.estado IN ('PROGRAMADA', 'CONFIRMADA') AND c.fecha >= CURRENT_DATE
              ORDER BY c.fecha, c.hora_inicio",
            ['paciente' => $idPaciente]
        );
    }
}
