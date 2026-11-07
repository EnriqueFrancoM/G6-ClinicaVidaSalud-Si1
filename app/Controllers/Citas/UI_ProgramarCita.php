<?php

namespace App\Controllers\Citas;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;

// CU11 boundary. Mensajes: solicitar(programar), mostrarResultado(), mostrarError().
class UI_ProgramarCita extends Controller
{
    // Pasos 1 a 4: seleccionar paciente, especialidad, médico y fecha; consultar horarios libres.
    // POST (paso 5): programar(datos).
    public function solicitar(): void
    {
        $control = new CtrlCita();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = [
                'id_paciente'     => $this->input('id_paciente'),
                'id_especialidad' => $this->input('id_especialidad'),
                'id_medico'       => $this->input('id_medico'),
                'fecha'           => $this->input('fecha'),
                'hora_inicio'     => $this->input('hora_inicio'),
                'motivo'          => $this->input('motivo'),
            ];
            if ($control->programar($datos)) {
                $this->mostrarResultado($control);
            }
            $this->mostrarError($control);
        }

        $filtro = [
            'paciente'        => (string) $this->input('paciente', ''),
            'id_especialidad' => (int) $this->input('id_especialidad', 0),
            'id_medico'       => (int) $this->input('id_medico', 0),
            'fecha'           => (string) $this->input('fecha', date('Y-m-d')),
            'motivo'          => (string) $this->input('motivo', ''),
        ];

        $paciente = null;
        $avisoPaciente = null;
        if ($filtro['paciente'] !== '') {
            $paciente = (new Paciente())->buscarPaciente($filtro['paciente']);
            if ($paciente === null) {
                $avisoPaciente = 'No existe un paciente con ese CI o código.';
            } elseif (!in_array($paciente['estado'], [true, 't', '1', 1], true)) {
                $avisoPaciente = 'El paciente está inactivo; no puede recibir nuevas citas.';
                $paciente = null;
            }
        }

        // Paso 3: horarios disponibles (solo con médico y fecha elegidos).
        $cupos = null;
        if ($paciente !== null && $filtro['id_medico'] > 0 && $filtro['fecha'] >= date('Y-m-d')
            && \DateTime::createFromFormat('!Y-m-d', $filtro['fecha'])?->format('Y-m-d') === $filtro['fecha']) {
            $cupos = $control->consultarDisponibilidad($filtro['id_medico'], $filtro['fecha']);
        }

        $this->view('citas/UI_ProgramarCita', [
            'filtro'         => $filtro,
            'paciente'       => $paciente,
            'avisoPaciente'  => $avisoPaciente,
            'especialidades' => (new Especialidad())->listarConMedicoActivo(),
            'medicos'        => (new Medico())->buscar(['estado' => true]),
            'cupos'          => $cupos,
            'registrada'     => ($codigo = (string) $this->input('registrada', '')) !== '' ? $control->buscarCita($codigo) : null,
            'campoError'     => Session::flash('campo_error'),
        ]);
    }

    // Paso 6: mostrar el código de la cita.
    private function mostrarResultado(CtrlCita $control): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect('/citas/programar?registrada=' . rawurlencode($control->cita['codigo']));
    }

    // Alternativas: cupo ocupado, fecha pasada o médico inactivo; no se aplican cambios parciales.
    private function mostrarError(CtrlCita $control): never
    {
        Session::flash('error', $control->mensaje);
        Session::flash('campo_error', (string) $control->campoError);
        $this->redirect('/citas/programar?' . http_build_query([
            'paciente'        => $this->input('paciente', ''),
            'id_especialidad' => $this->input('id_especialidad', ''),
            'id_medico'       => $this->input('id_medico', ''),
            'fecha'           => $this->input('fecha', ''),
            'motivo'          => $this->input('motivo', ''),
        ]));
    }
}
