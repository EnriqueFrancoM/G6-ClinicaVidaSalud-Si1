<?php

namespace App\Controllers\Citas;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Cita;

// CU13 boundary. Mensajes: solicitar(cancelar), mostrarResultado(), mostrarError().
class UI_CancelarCita extends Controller
{
    // Pasos 1 y 2: buscar la cita y mostrar fecha, médico y paciente. Pasos 3 y 4 (POST): cancelar(datos).
    public function solicitar(): void
    {
        $control = new CtrlCita();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = ['codigo' => $this->input('codigo'), 'motivo' => $this->input('motivo')];
            if ($control->cancelar($datos)) {
                $this->mostrarResultado($control);
            }
            $this->mostrarError($control);
        }

        $codigo = (string) $this->input('codigo', '');
        $cita = $control->buscarCita($codigo);

        $this->view('citas/UI_CancelarCita', [
            'codigo'     => $codigo,
            'cita'       => $cita,
            'aviso'      => $codigo !== '' && $cita === null ? 'No existe una cita con ese código.' : null,
            'cancelable' => $cita !== null && in_array($cita['estado'], Cita::PENDIENTES, true),
            'motivo'     => (string) Session::flash('motivo_cancelacion'),
            'campoError' => Session::flash('campo_error'),
        ]);
    }

    // Paso 6: mostrar el cupo liberado.
    private function mostrarResultado(CtrlCita $control): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect('/citas/cancelar?codigo=' . rawurlencode($control->cita['codigo']));
    }

    // Alternativas: cita ya atendida/cancelada o motivo vacío.
    private function mostrarError(CtrlCita $control): never
    {
        Session::flash('error', $control->mensaje);
        Session::flash('campo_error', (string) $control->campoError);
        Session::flash('motivo_cancelacion', (string) $this->input('motivo', ''));
        $this->redirect('/citas/cancelar?codigo=' . rawurlencode((string) $this->input('codigo', '')));
    }
}
