<?php

namespace App\Controllers\Citas;

use App\Core\Session;
use App\Models\BloqueoAgenda;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Paciente;

// CU15 control. Mensajes: consultarAgenda(datos), validarAcceso().
// Entidades: Medico.validarMédico(id), HorarioAtencion.consultarHorario(fecha), Cita.buscarAgenda(médico, fecha, estado).
class CtrlAgenda
{
    // RF23 (prioridad pediátrica): las citas de Pediatría de menores de 5 años se marcan como prioritarias.
    public const EDAD_PRIORITARIA = 5;

    public ?array $usuarioActual;

    public string $mensaje = '';

    // Médico del usuario si su rol es Médico (solo ve su propia agenda).
    public ?array $medicoPropio = null;

    public function __construct()
    {
        $this->usuarioActual = Session::user();
        if (!Session::hasPermission('CIT_GESTIONAR')) {
            $this->medicoPropio = (new Medico())->obtenerPorUsuario((int) ($this->usuarioActual['id_usuario'] ?? 0));
        }
    }

    public function validarAcceso(): bool
    {
        return Session::hasPermission('AGE_CONSULTAR');
    }

    // Médicos cuya agenda puede consultar el usuario.
    public function medicosVisibles(): array
    {
        if ($this->medicoPropio !== null) {
            return [['id_medico' => $this->medicoPropio['id_medico'], 'codigo' => $this->medicoPropio['codigo'], 'nombre' => $this->medicoPropio['nombre']]];
        }
        if (!Session::hasPermission('CIT_GESTIONAR')) {
            return [];
        }

        return array_map(
            fn ($m) => ['id_medico' => $m['id_medico'], 'codigo' => $m['codigo'], 'nombre' => $m['nombres'] . ' ' . $m['apellidos']],
            (new Medico())->buscar(['estado' => null])
        );
    }

    // CU15 mensaje consultarAgenda(datos): médico, fecha y estado. Devuelve null si no se puede consultar.
    public function consultarAgenda(array $datos): ?array
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para consultar agendas.');
        }

        $idMedico = (int) ($datos['id_medico'] ?? 0);
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $estado = strtoupper(trim((string) ($datos['estado'] ?? '')));

        // Paso 3: validar alcance del usuario.
        if ($this->medicoPropio !== null) {
            if ($idMedico !== 0 && $idMedico !== (int) $this->medicoPropio['id_medico']) {
                return $this->error('Solo puede consultar su propia agenda.');
            }
            $idMedico = (int) $this->medicoPropio['id_medico'];
        } elseif (!Session::hasPermission('CIT_GESTIONAR')) {
            return $this->error('Su usuario no está vinculado a un médico.');
        }
        if (!\App\Core\App::fechaIso($fecha)) {
            return $this->error('Ingrese una fecha válida.');
        }
        if ($estado !== '' && !isset(Cita::ESTADOS[$estado])) {
            return $this->error('Seleccione un estado válido.');
        }
        // id 0: todos los médicos. Solo recepción o administración (CIT_GESTIONAR y sin médico propio).
        if ($idMedico === 0 && $this->medicoPropio === null && Session::hasPermission('CIT_GESTIONAR')) {
            return $this->agendaDeTodos($fecha, $estado);
        }
        if ($idMedico <= 0) {
            return $this->error('Seleccione un médico.');
        }

        // Mensaje validarMédico(id).
        $medico = (new Medico())->validarMedico($idMedico);
        if ($medico === null) {
            return $this->error('El médico no existe.');
        }

        // Mensajes consultarHorario(fecha) y buscarAgenda(médico, fecha, estado), ordenada por hora.
        $citas = (new Cita())->buscarAgenda($idMedico, $fecha, $estado !== '' ? $estado : null);
        foreach ($citas as &$cita) {
            $cita['prioritaria'] = mb_strtolower($cita['especialidad']) === 'pediatría'
                && Paciente::edad($cita['fecha_nacimiento']) < self::EDAD_PRIORITARIA
                && in_array($cita['estado'], Cita::PENDIENTES, true);
        }
        unset($cita);

        if ($citas === []) {
            $this->mensaje = 'Sin citas para el médico en la fecha y estado elegidos.';
        }

        return [
            'medico'    => $medico,
            'horario'   => (new HorarioAtencion())->consultarHorario($idMedico, $fecha),
            'bloqueado' => (new BloqueoAgenda())->existeBloqueo($idMedico, $fecha),
            'citas'     => $citas,
        ];
    }

    private function agendaDeTodos(string $fecha, string $estado): array
    {
        $citas = (new Cita())->buscarAgendaDia($fecha, $estado !== '' ? $estado : null);
        foreach ($citas as &$cita) {
            $cita['prioritaria'] = mb_strtolower($cita['especialidad']) === 'pediatría'
                && Paciente::edad($cita['fecha_nacimiento']) < self::EDAD_PRIORITARIA
                && in_array($cita['estado'], Cita::PENDIENTES, true);
        }
        unset($cita);

        $conCita = [];
        foreach ($citas as $cita) {
            $conCita[(int) $cita['id_medico']] = true;
        }
        $sinCitas = [];
        $horarios = new HorarioAtencion();
        $bloqueos = new BloqueoAgenda();
        foreach ($this->medicosVisibles() as $medico) {
            $id = (int) $medico['id_medico'];
            if (isset($conCita[$id])) {
                continue;
            }
            $sinCitas[] = [
                'nombre'    => $medico['nombre'],
                'codigo'    => $medico['codigo'],
                'bloqueado' => $bloqueos->existeBloqueo($id, $fecha),
                'horario'   => $horarios->consultarHorario($id, $fecha),
            ];
        }
        if ($citas === []) {
            $this->mensaje = 'Sin citas para la fecha y el estado elegidos.';
        }

        return [
            'medico'    => ['id_medico' => 0, 'codigo' => '', 'nombre' => 'Todos los médicos'],
            'horario'   => [],
            'bloqueado' => false,
            'citas'     => $citas,
            'todos'     => true,
            'sin_citas' => $sinCitas,
        ];
    }

    private function error(string $mensaje): null
    {
        $this->mensaje = $mensaje;
        return null;
    }
}
