<?php

namespace App\Controllers\Citas;

use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HistorialEstadoCita;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Recordatorio;

// Control de CU11, CU12 y CU13. Mensajes: programar(datos), reprogramar(datos), cancelar(datos), validarAcceso().
// CU11: Paciente.validarPaciente(id), HorarioAtencion.consultarDisponibilidad(médico, fecha), Cita.registrarSiDisponible(datos).
// CU12: Cita.buscarYValidarEstado(código), HorarioAtencion.validarNuevoCupo(datos), HistorialEstadoCita.registrarReprogramación(datos).
// CU13: Cita.cancelarConMotivo(código, motivo), HistorialEstadoCita.registrarCancelación(datos), HorarioAtencion.consultarCupoLiberado(datos).
class CtrlCita
{
    // RN16: Pediatría atiende de 0 a 14 años.
    public const EDAD_MAXIMA_PEDIATRIA = 14;

    public ?array $usuarioActual;

    public string $mensaje = '';

    public ?string $campoError = null;

    // Cita registrada o modificada (para mostrar el resultado).
    public ?array $cita = null;

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    public function validarAcceso(): bool
    {
        return Session::hasPermission('CIT_GESTIONAR');
    }

    public function buscarCita(string $codigo): ?array
    {
        return trim($codigo) === '' ? null : (new Cita())->buscarPorCodigo($codigo);
    }

    // CU11 paso 3 / mensaje consultarDisponibilidad(médico, fecha).
    public function consultarDisponibilidad(int $idMedico, string $fecha): array
    {
        return (new HorarioAtencion())->consultarDisponibilidad($idMedico, $fecha);
    }

    // CU12 paso 2: cupos libres para el nuevo horario (la propia cita no ocupa el suyo).
    public function cuposParaReprogramar(array $cita, string $fecha): array
    {
        $cupos = (new HorarioAtencion())->cuposDelDia((int) $cita['id_medico'], $fecha, (int) $cita['id_cita']);
        return array_values(array_filter($cupos, fn ($c) => $c['libre']));
    }

    // CU11 mensaje programar(datos): paciente, especialidad, médico, fecha, hora y motivo.
    public function programar(array $datos): bool
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para programar citas.');
        }
        $limpio = $this->validarSolicitud($datos);
        if ($limpio === null) {
            return false;
        }

        // Mensaje validarPaciente(id): paciente activo.
        $paciente = (new Paciente())->validarPaciente($limpio['id_paciente']);
        if ($paciente === null) {
            return $this->error('El paciente no existe o está inactivo.', 'paciente');
        }
        $regla = self::validarReglas($paciente, $limpio['id_especialidad'], $limpio['id_medico']);
        if ($regla !== null) {
            return $this->error($regla, 'id_medico');
        }

        // Mensaje consultarDisponibilidad(médico, fecha): el cupo pertenece al horario y está libre (RN05, RN07, RN08).
        $cupo = $this->cupoElegido($this->consultarDisponibilidad($limpio['id_medico'], $limpio['fecha']), $limpio['hora_inicio']);
        if ($cupo === null) {
            return $this->error('El horario elegido ya no está disponible. Consulte nuevamente los horarios libres.', 'hora_inicio');
        }

        // Paso 5: validar nuevamente el cupo y registrar (mensaje registrarSiDisponible(datos)).
        $datosCita = $limpio + ['hora_fin' => $cupo['hora_fin'], 'id_usuario_registro' => (int) $this->usuarioActual['id_usuario']];
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $nueva = (new Cita())->registrarSiDisponible($datosCita);
            (new HistorialEstadoCita())->registrarInicial($nueva['id_cita'], $datosCita['id_usuario_registro'], 'Registro inicial');
            (new Recordatorio())->programar($nueva['id_cita'], $limpio['fecha'], $limpio['hora_inicio'], $paciente['email']);
            $this->bitacora('PROGRAMAR', 'Programó ' . $nueva['codigo'] . ' para ' . $paciente['codigo']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            if (Cita::esCupoOcupado($e)) {
                // PN06: dos solicitudes simultáneas al mismo cupo; la segunda se rechaza.
                return $this->error('El horario fue ocupado por otra reserva mientras lo elegía. Seleccione otro.', 'hora_inicio');
            }
            throw $e;
        }

        $this->cita = (new Cita())->buscarPorId($nueva['id_cita']);
        $this->mensaje = 'Cita ' . $nueva['codigo'] . ' registrada para el ' . date('d/m/Y', strtotime($limpio['fecha'])) . ' a las ' . $limpio['hora_inicio'] . '.';

        return true;
    }

    // CU12 mensaje reprogramar(datos): código de cita y nuevo horario.
    public function reprogramar(array $datos): bool
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para reprogramar citas.');
        }

        // Mensaje buscarYValidarEstado(código): solo PROGRAMADA o CONFIRMADA.
        ['cita' => $cita, 'valida' => $valida] = (new Cita())->buscarYValidarEstado((string) ($datos['codigo'] ?? ''));
        if ($cita === null) {
            return $this->error('No existe una cita con ese código.', 'codigo');
        }
        if (!$valida) {
            return $this->error('Solo se reprograman citas programadas o confirmadas; la cita ' . $cita['codigo'] . ' está ' . mb_strtolower(Cita::ESTADOS[$cita['estado']]) . '.', 'codigo');
        }

        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $hora = trim((string) ($datos['hora_inicio'] ?? ''));
        if (!$this->fechaValida($fecha) || $fecha < date('Y-m-d')) {
            return $this->error('Elija una fecha válida desde hoy (RN06).', 'fecha');
        }
        if ($fecha === $cita['fecha'] && $hora === $cita['hora_inicio']) {
            return $this->error('Elija un horario distinto al actual.', 'hora_inicio');
        }
        $medico = (new Medico())->validarMedico((int) $cita['id_medico']);
        if ($medico === null || !in_array($medico['estado'], [true, 't', '1', 1], true)) {
            return $this->error('El médico de la cita está inactivo; no se puede reprogramar con él (RN04).');
        }

        // Mensaje validarNuevoCupo(datos).
        $cupo = (new HorarioAtencion())->validarNuevoCupo(['id_medico' => $cita['id_medico'], 'fecha' => $fecha, 'hora_inicio' => $hora, 'id_cita' => $cita['id_cita']]);
        if ($cupo === null) {
            // PN07: nuevo horario ocupado → se rechaza y se conserva el anterior.
            return $this->error('El nuevo horario no está disponible. La cita conserva su horario anterior.', 'hora_inicio');
        }

        $anterior = ['id_medico' => (int) $cita['id_medico'], 'fecha' => $cita['fecha'], 'hora_inicio' => $cita['hora_inicio'], 'hora_fin' => $cita['hora_fin']];
        $db = Database::connection();
        $db->beginTransaction();
        try {
            // Paso 4: actualizar horario en transacción. Paso 5: guardar historial (registrarReprogramación(datos)).
            if (!(new Cita())->actualizarHorario((int) $cita['id_cita'], $fecha, $cupo['hora_inicio'], $cupo['hora_fin'])) {
                $db->rollBack();
                return $this->error('La cita cambió de estado mientras se reprogramaba. Revísela nuevamente.', 'codigo');
            }
            (new HistorialEstadoCita())->registrarReprogramacion([
                'id_cita'     => (int) $cita['id_cita'],
                'id_usuario'  => (int) $this->usuarioActual['id_usuario'],
                'estado'      => $cita['estado'],
                'observacion' => 'Reprogramada del ' . $this->fechaHora($cita['fecha'], $cita['hora_inicio']) . ' al ' . $this->fechaHora($fecha, $cupo['hora_inicio']),
            ]);
            (new Recordatorio())->reprogramar((int) $cita['id_cita'], $fecha, $cupo['hora_inicio']);
            $this->bitacora('REPROGRAMAR', 'Reprogramó ' . $cita['codigo'] . ' al ' . $this->fechaHora($fecha, $cupo['hora_inicio']));
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            if (Cita::esCupoOcupado($e)) {
                return $this->error('El nuevo horario fue ocupado entretanto. La cita conserva su horario anterior.', 'hora_inicio');
            }
            throw $e;
        }

        // RN17: el horario anterior liberado se ofrece a la lista de espera.
        $oferta = $this->ofrecerLiberado($anterior);
        $this->cita = (new Cita())->buscarPorId((int) $cita['id_cita']);
        $this->mensaje = 'Cita ' . $cita['codigo'] . ' reprogramada al ' . $this->fechaHora($fecha, $cupo['hora_inicio']) . '. El horario anterior quedó libre'
            . ($oferta ? ' y se ofreció a la lista de espera.' : '.');

        return true;
    }

    // CU13 mensaje cancelar(datos): código de cita y motivo.
    public function cancelar(array $datos): bool
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para cancelar citas.');
        }
        $codigo = (string) ($datos['codigo'] ?? '');
        $motivo = preg_replace('/\s+/u', ' ', trim((string) ($datos['motivo'] ?? '')));
        $cita = $this->buscarCita($codigo);
        if ($cita === null) {
            return $this->error('No existe una cita con ese código.', 'codigo');
        }
        if (!in_array($cita['estado'], Cita::PENDIENTES, true)) {
            return $this->error('La cita ' . $cita['codigo'] . ' ya está ' . mb_strtolower(Cita::ESTADOS[$cita['estado']]) . '; no se puede cancelar.', 'codigo');
        }
        if ($motivo === '' || mb_strlen($motivo) > 200) {
            return $this->error('Ingrese el motivo de la cancelación (máximo 200 caracteres).', 'motivo');
        }

        if (!$this->aplicarCancelacion($cita, $motivo, (int) $this->usuarioActual['id_usuario'])) {
            return $this->error('La cita cambió de estado mientras se cancelaba. Revísela nuevamente.');
        }

        $oferta = $this->ofrecerLiberado($cita);
        $this->cita = (new Cita())->buscarPorId((int) $cita['id_cita']);
        $this->mensaje = 'Cita ' . $cita['codigo'] . ' cancelada. El horario del ' . $this->fechaHora($cita['fecha'], $cita['hora_inicio']) . ' quedó libre'
            . ($oferta ? ' y se ofreció al primero de la lista de espera.' : '.');

        return true;
    }

    // Cancelación en una transacción: cancelarConMotivo(código, motivo) + registrarCancelación(datos).
    // También la usa el paciente desde el portal o el enlace del recordatorio (RF21).
    public function aplicarCancelacion(array $cita, string $motivo, int $idUsuario): bool
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $cancelada = (new Cita())->cancelarConMotivo($cita['codigo'], $motivo);
            if ($cancelada === null) {
                $db->rollBack();
                return false;
            }
            (new HistorialEstadoCita())->registrarCancelacion([
                'id_cita'         => (int) $cita['id_cita'],
                'id_usuario'      => $idUsuario,
                'estado_anterior' => $cita['estado'],
                'motivo'          => $motivo,
            ]);
            (new Bitacora())->registrar($idUsuario, 'CANCELAR', 'Citas', 'Canceló ' . $cita['codigo'] . ': ' . $motivo);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    // Mensaje consultarCupoLiberado(datos) y oferta a la lista de espera (RN17).
    public function ofrecerLiberado(array $cita): bool
    {
        $cupo = (new HorarioAtencion())->consultarCupoLiberado([
            'id_medico' => $cita['id_medico'], 'fecha' => $cita['fecha'], 'hora_inicio' => $cita['hora_inicio'],
        ]);

        return $cupo !== null && (new CtrlListaEspera())->ofrecerCupoLiberado($cupo) !== null;
    }

    // Reglas de negocio comunes a programar (CU11), reservar (CU18) y lista de espera (CU21).
    // Devuelve el mensaje de error o null si se cumplen.
    public static function validarReglas(array $paciente, int $idEspecialidad, int $idMedico): ?string
    {
        $especialidad = (new Especialidad())->buscarPorId($idEspecialidad);
        if ($especialidad === null || !in_array($especialidad['estado'], [true, 't', '1', 1], true)) {
            return 'La especialidad no está disponible (RN15).';
        }
        if (!(new Medico())->atiendeEspecialidad($idMedico, $idEspecialidad)) {
            return 'El médico no está activo o no atiende esa especialidad (RN04).';
        }
        if (mb_strtolower($especialidad['nombre']) === 'pediatría' && Paciente::edad($paciente['fecha_nacimiento']) > self::EDAD_MAXIMA_PEDIATRIA) {
            return 'Pediatría atiende a pacientes de 0 a 14 años (RN16).';
        }

        return null;
    }

    // CU11 paso 4: datos de entrada y RN06 (sin fechas pasadas).
    private function validarSolicitud(array $datos): ?array
    {
        $limpio = [
            'id_paciente'     => (int) ($datos['id_paciente'] ?? 0),
            'id_especialidad' => (int) ($datos['id_especialidad'] ?? 0),
            'id_medico'       => (int) ($datos['id_medico'] ?? 0),
            'fecha'           => trim((string) ($datos['fecha'] ?? '')),
            'hora_inicio'     => trim((string) ($datos['hora_inicio'] ?? '')),
            'motivo'          => preg_replace('/\s+/u', ' ', trim((string) ($datos['motivo'] ?? ''))),
        ];
        if ($limpio['id_paciente'] <= 0) {
            return $this->invalido('paciente', 'Seleccione un paciente.');
        }
        if ($limpio['id_especialidad'] <= 0) {
            return $this->invalido('id_especialidad', 'Seleccione una especialidad.');
        }
        if ($limpio['id_medico'] <= 0) {
            return $this->invalido('id_medico', 'Seleccione un médico.');
        }
        if (!$this->fechaValida($limpio['fecha'])) {
            return $this->invalido('fecha', 'Ingrese una fecha válida.');
        }
        if ($limpio['fecha'] < date('Y-m-d')) {
            return $this->invalido('fecha', 'No se pueden registrar citas en fechas pasadas (RN06).');
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $limpio['hora_inicio'])) {
            return $this->invalido('hora_inicio', 'Seleccione un horario disponible.');
        }
        if (mb_strlen($limpio['motivo']) > 200) {
            return $this->invalido('motivo', 'El motivo admite hasta 200 caracteres.');
        }
        $limpio['motivo'] = $limpio['motivo'] !== '' ? $limpio['motivo'] : null;

        return $limpio;
    }

    private function cupoElegido(array $cupos, string $hora): ?array
    {
        foreach ($cupos as $cupo) {
            if ($cupo['hora_inicio'] === $hora) {
                return $cupo;
            }
        }
        return null;
    }

    private function fechaValida(string $fecha): bool
    {
        return \App\Core\App::fechaIso($fecha);
    }

    private function fechaHora(string $fecha, string $hora): string
    {
        return date('d/m/Y', strtotime($fecha)) . ' ' . $hora;
    }

    private function bitacora(string $accion, string $detalle): void
    {
        (new Bitacora())->registrar((int) $this->usuarioActual['id_usuario'], $accion, 'Citas', $detalle);
    }

    private function invalido(string $campo, string $mensaje): null
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return null;
    }

    private function error(string $mensaje, ?string $campo = null): bool
    {
        $this->mensaje = $mensaje;
        $this->campoError = $campo;
        return false;
    }
}
