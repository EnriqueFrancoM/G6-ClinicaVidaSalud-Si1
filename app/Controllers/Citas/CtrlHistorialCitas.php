<?php

namespace App\Controllers\Citas;

use App\Core\Session;
use App\Models\Cita;
use App\Models\HistorialEstadoCita;
use App\Models\Paciente;

// CU16 control. Mensajes: consultarHistorial(datos), validarAcceso().
// Entidades: Paciente.buscarPaciente(criterio), Cita.buscarPorPaciente(id, período),
// HistorialEstadoCita.consultarCambios(citas).
class CtrlHistorialCitas
{
    public ?array $usuarioActual;

    public string $mensaje = '';

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    public function validarAcceso(): bool
    {
        return Session::hasPermission('CIT_GESTIONAR') || Session::hasPermission('PAC_GESTIONAR');
    }

    // CU16 mensaje consultarHistorial(datos): criterio (CI o código del paciente) y período.
    public function consultarHistorial(array $datos): ?array
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para consultar historiales.');
        }

        $criterio = trim((string) ($datos['criterio'] ?? ''));
        $desde = trim((string) ($datos['desde'] ?? ''));
        $hasta = trim((string) ($datos['hasta'] ?? ''));
        foreach (['desde' => $desde, 'hasta' => $hasta] as $valor) {
            if ($valor !== '' && !\App\Core\App::fechaIso($valor)) {
                return $this->error('Ingrese fechas válidas.');
            }
        }
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            return $this->error('La fecha "desde" debe ser anterior o igual a la fecha "hasta".');
        }

        // Mensaje buscarPaciente(criterio).
        $paciente = (new Paciente())->buscarPaciente($criterio);
        if ($paciente === null) {
            return $this->error('No existe un paciente con ese CI o código.');
        }

        // Mensajes buscarPorPaciente(id, período) y consultarCambios(citas): incluye canceladas y pasadas.
        $citas = (new Cita())->buscarPorPaciente((int) $paciente['id_paciente'], $desde ?: null, $hasta ?: null);
        $cambios = (new HistorialEstadoCita())->consultarCambios(array_column($citas, 'id_cita'));
        foreach ($citas as &$cita) {
            $cita['cambios'] = $cambios[(int) $cita['id_cita']] ?? [];
        }
        unset($cita);

        if ($citas === []) {
            $this->mensaje = 'El paciente no tiene citas en el período elegido.';
        }

        return ['paciente' => $paciente, 'citas' => $citas];
    }

    private function error(string $mensaje): null
    {
        $this->mensaje = $mensaje;
        return null;
    }
}
