<?php

namespace App\Controllers\Portal;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Recordatorio;

// CU18 boundary. Mensajes: solicitar(reservar), mostrarResultado(), mostrarError().
class UI_MisCitas extends Controller
{
    // Pasos 2 y 3: filtrar especialidad y médico, seleccionar horario. POST: reservar(datos)
    // o confirmar / cancelar una cita propia (RF21).
    public function solicitar(): void
    {
        $control = new CtrlReserva();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = (string) $this->input('accion', 'reservar');
            $ok = $accion === 'reservar'
                ? $control->reservar([
                    'id_especialidad' => $this->input('id_especialidad'),
                    'id_medico'       => $this->input('id_medico'),
                    'fecha'           => $this->input('fecha'),
                    'hora_inicio'     => $this->input('hora_inicio'),
                    'motivo'          => $this->input('motivo'),
                ])
                : $control->responderCita((string) $this->input('codigo', ''), $accion);
            $ok ? $this->mostrarResultado($control) : $this->mostrarError($control);
        }

        $paciente = $control->titular();
        $filtro = [
            'id_especialidad' => (int) $this->input('id_especialidad', 0),
            'id_medico'       => (int) $this->input('id_medico', 0),
            'fecha'           => (string) $this->input('fecha', date('Y-m-d')),
            'motivo'          => (string) $this->input('motivo', ''),
        ];
        $cupos = null;
        if ($paciente !== null && $filtro['id_medico'] > 0 && $filtro['fecha'] >= date('Y-m-d')
            && \DateTime::createFromFormat('!Y-m-d', $filtro['fecha'])?->format('Y-m-d') === $filtro['fecha']) {
            $cupos = $control->consultarCupos($filtro['id_medico'], $filtro['fecha']);
        }

        $this->view('portal/UI_MisCitas', [
            'paciente'       => $paciente,
            'filtro'         => $filtro,
            'cupos'          => $cupos,
            'grupos'         => $paciente !== null ? $control->misCitas((int) $paciente['id_paciente']) : [],
            'avisos'         => $paciente !== null ? (new Recordatorio())->avisosDelPaciente((int) $paciente['id_paciente']) : [],
            'especialidades' => (new Especialidad())->listarConMedicoActivo(),
            'medicos'        => (new Medico())->buscar(['estado' => true]),
            'campoError'     => Session::flash('campo_error'),
            'reservar'       => $this->input('reservar') !== null || $filtro['id_especialidad'] > 0,
        ]);
    }

    // Paso 6: mostrar código y estado en Mis citas.
    private function mostrarResultado(CtrlReserva $control): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect('/portal/citas');
    }

    // Alternativas: reserva simultánea o cupo ya ocupado.
    private function mostrarError(CtrlReserva $control): never
    {
        Session::flash('error', $control->mensaje);
        Session::flash('campo_error', (string) $control->campoError);
        $this->redirect('/portal/citas?' . http_build_query([
            'id_especialidad' => $this->input('id_especialidad', ''),
            'id_medico'       => $this->input('id_medico', ''),
            'fecha'           => $this->input('fecha', ''),
            'motivo'          => $this->input('motivo', ''),
        ]));
    }
}
