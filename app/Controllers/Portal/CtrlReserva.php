<?php

namespace App\Controllers\Portal;

use App\Controllers\Citas\CtrlCita;
use App\Controllers\Reportes\CtrlRecordatorio;
use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HistorialEstadoCita;
use App\Models\HorarioAtencion;
use App\Models\Paciente;
use App\Models\Recordatorio;

// CU18 control. Mensajes: reservar(datos), validarAcceso().
// Entidades: Paciente.validarTitular(sesión), HorarioAtencion.consultarCupos(médico, fecha), Cita.reservarAtómicamente(datos).
class CtrlReserva
{
    // RF18: grupos de "Mis citas".
    public const GRUPOS = [
        'pendientes'  => ['PROGRAMADA'],
        'confirmadas' => ['CONFIRMADA'],
        'completadas' => ['ATENDIDA', 'INASISTENCIA'],
        'canceladas'  => ['CANCELADA'],
    ];

    public ?array $usuarioActual;

    public string $mensaje = '';

    public ?string $campoError = null;

    public ?array $cita = null;

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    public function validarAcceso(): bool
    {
        return Session::hasPermission('POR_PACIENTE');
    }

    // Mensaje validarTitular(sesión): ficha del paciente de la sesión, o null si aún no la completó.
    public function titular(): ?array
    {
        return (new Paciente())->validarTitular($this->usuarioActual ?? []);
    }

    // Paso 3: cupos libres del médico en la fecha (consultarCupos(médico, fecha)).
    public function consultarCupos(int $idMedico, string $fecha): array
    {
        return (new HorarioAtencion())->consultarCupos($idMedico, $fecha);
    }

    // Inicio del paciente: próxima cita futura, directorio real y sus propias citas.
    public function datosInicio(): array
    {
        $paciente = $this->titular();
        $idPaciente = $paciente !== null ? (int) $paciente['id_paciente'] : 0;
        $citas = new Cita();

        return [
            'paciente'     => $paciente,
            'proxima'      => $idPaciente > 0 ? $citas->proximaDelPaciente($idPaciente) : null,
            'grupos'         => $idPaciente > 0 ? $this->misCitas($idPaciente) : [],
            'especialidades' => (new Especialidad())->catalogoReserva(),
            'avisos'         => $idPaciente > 0 ? (new Recordatorio())->avisosDelPaciente($idPaciente) : [],
        ];
    }

    // Paso 6: mis citas agrupadas (solo las del titular, PN10).
    public function misCitas(int $idPaciente): array
    {
        $citas = array_reverse((new Cita())->buscarPorPaciente($idPaciente));
        $grupos = array_fill_keys(array_keys(self::GRUPOS), []);
        foreach ($citas as $cita) {
            foreach (self::GRUPOS as $grupo => $estados) {
                if (in_array($cita['estado'], $estados, true)) {
                    $grupos[$grupo][] = $cita;
                }
            }
        }
        // Las pendientes y confirmadas, de la más próxima a la más lejana.
        $grupos['pendientes'] = array_reverse($grupos['pendientes']);
        $grupos['confirmadas'] = array_reverse($grupos['confirmadas']);

        return $grupos;
    }

    // CU18 mensaje reservar(datos): especialidad, médico, fecha y hora.
    public function reservar(array $datos): bool
    {
        if (!$this->validarAcceso()) {
            return $this->error('Solo los pacientes pueden reservar en línea.');
        }
        // Paso 4: validar paciente (titular de la sesión).
        $paciente = $this->titular();
        if ($paciente === null) {
            return $this->error('Complete su perfil antes de reservar una cita.');
        }

        $idEspecialidad = (int) ($datos['id_especialidad'] ?? 0);
        $idMedico = (int) ($datos['id_medico'] ?? 0);
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $hora = trim((string) ($datos['hora_inicio'] ?? ''));
        $motivo = preg_replace('/\s+/u', ' ', trim((string) ($datos['motivo'] ?? '')));
        if (!\App\Core\App::fechaIso($fecha) || $fecha < date('Y-m-d')) {
            return $this->error('Elija una fecha válida desde hoy.', 'fecha');
        }
        if (mb_strlen($motivo) > 200) {
            return $this->error('El motivo admite hasta 200 caracteres.', 'motivo');
        }
        $regla = CtrlCita::validarReglas($paciente, $idEspecialidad, $idMedico);
        if ($regla !== null) {
            return $this->error($regla, 'id_medico');
        }

        // Paso 4: validar cupo (consultarCupos(médico, fecha)).
        $cupo = null;
        foreach ($this->consultarCupos($idMedico, $fecha) as $c) {
            if ($c['hora_inicio'] === $hora) {
                $cupo = $c;
            }
        }
        if ($cupo === null) {
            return $this->error('El horario elegido ya no está disponible. Elija otro.', 'hora_inicio');
        }

        // Paso 5: registrar la reserva de forma atómica (reservarAtómicamente(datos)).
        $datosCita = [
            'id_paciente' => (int) $paciente['id_paciente'], 'id_medico' => $idMedico, 'id_especialidad' => $idEspecialidad,
            'id_usuario_registro' => (int) $this->usuarioActual['id_usuario'], 'fecha' => $fecha,
            'hora_inicio' => $cupo['hora_inicio'], 'hora_fin' => $cupo['hora_fin'], 'motivo' => $motivo !== '' ? $motivo : null,
        ];
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $nueva = (new Cita())->reservarAtomicamente($datosCita);
            (new HistorialEstadoCita())->registrarInicial($nueva['id_cita'], $datosCita['id_usuario_registro'], 'Reserva en línea');
            (new Recordatorio())->programar($nueva['id_cita'], $fecha, $cupo['hora_inicio'], $paciente['email']);
            (new Bitacora())->registrar($datosCita['id_usuario_registro'], 'RESERVAR', 'Portal', 'Reservó ' . $nueva['codigo']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            if (Cita::esCupoOcupado($e)) {
                // Alternativa: reserva simultánea o cupo ya ocupado.
                return $this->error('Otro paciente acaba de reservar ese horario. Elija otro.', 'hora_inicio');
            }
            throw $e;
        }

        $this->cita = (new Cita())->buscarPorId($nueva['id_cita']);
        $this->mensaje = 'Reserva ' . $nueva['codigo'] . ' registrada para el ' . date('d/m/Y', strtotime($fecha)) . ' a las ' . $cupo['hora_inicio'] . '.';

        return true;
    }

    // RF21: el paciente confirma o cancela una cita propia desde Mis citas.
    public function responderCita(string $codigo, string $respuesta): bool
    {
        $paciente = $this->titular();
        $cita = (new Cita())->buscarPorCodigo($codigo);
        if ($paciente === null || $cita === null || (int) $cita['id_paciente'] !== (int) $paciente['id_paciente']) {
            return $this->error('La cita no pertenece a su cuenta.');
        }
        $control = new CtrlRecordatorio();
        $ok = $control->aplicarRespuesta($cita, $respuesta, (int) $this->usuarioActual['id_usuario']);
        $this->mensaje = $control->mensaje;

        return $ok;
    }

    private function error(string $mensaje, ?string $campo = null): bool
    {
        $this->mensaje = $mensaje;
        $this->campoError = $campo;
        return false;
    }
}
