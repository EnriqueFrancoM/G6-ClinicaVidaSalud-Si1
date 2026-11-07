<?php

namespace App\Controllers\Citas;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Cita;

// CU12 boundary. Mensajes: solicitar(reprogramar), mostrarResultado(), mostrarError().
class UI_ReprogramarCita extends Controller
{
    // Paso 1: buscar cita. Paso 2: seleccionar nuevo horario. POST: reprogramar(datos).
    public function solicitar(): void
    {
        $control = new CtrlCita();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = [
                'codigo'      => $this->input('codigo'),
                'fecha'       => $this->input('fecha'),
                'hora_inicio' => $this->input('hora_inicio'),
            ];
            if ($control->reprogramar($datos)) {
                $this->mostrarResultado($control);
            }
            $this->mostrarError($control);
        }

        $codigo = (string) $this->input('codigo', '');
        $cita = $control->buscarCita($codigo);
        $fecha = (string) $this->input('fecha', $cita['fecha'] ?? date('Y-m-d'));
        $fechaValida = \DateTime::createFromFormat('!Y-m-d', $fecha)?->format('Y-m-d') === $fecha && $fecha >= date('Y-m-d');

        $this->view('citas/UI_ReprogramarCita', [
            'codigo'     => $codigo,
            'cita'       => $cita,
            'aviso'      => $codigo !== '' && $cita === null ? 'No existe una cita con ese código.' : null,
            'editable'   => $cita !== null && in_array($cita['estado'], Cita::PENDIENTES, true),
            'fecha'      => $fecha,
            'cupos'      => $cita !== null && $fechaValida ? $control->cuposParaReprogramar($cita, $fecha) : null,
            'campoError' => Session::flash('campo_error'),
        ]);
    }

    // Paso 6: mostrar confirmación.
    private function mostrarResultado(CtrlCita $control): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect('/citas/reprogramar?codigo=' . rawurlencode($control->cita['codigo']));
    }

    // Alternativas: cita cancelada/atendida o nuevo cupo ocupado; se conserva el horario anterior.
    private function mostrarError(CtrlCita $control): never
    {
        Session::flash('error', $control->mensaje);
        Session::flash('campo_error', (string) $control->campoError);
        $this->redirect('/citas/reprogramar?' . http_build_query(['codigo' => $this->input('codigo', ''), 'fecha' => $this->input('fecha', '')]));
    }
}
