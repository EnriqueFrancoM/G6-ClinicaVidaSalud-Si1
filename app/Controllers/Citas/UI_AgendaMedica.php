<?php

namespace App\Controllers\Citas;

use App\Core\Controller;
use App\Core\Session;

// CU15 boundary. Mensajes: solicitar(consultarAgenda), mostrarResultado(), mostrarError().
class UI_AgendaMedica extends Controller
{
    // Pasos 1 y 2: seleccionar médico, fecha y estado → consultarAgenda(datos).
    public function solicitar(): void
    {
        $control = new CtrlAgenda();
        $medicos = $control->medicosVisibles();
        $filtro = [
            'id_medico' => (int) $this->input('id_medico', $control->medicoPropio['id_medico'] ?? ($medicos[0]['id_medico'] ?? 0)),
            'fecha'     => (string) $this->input('fecha', date('Y-m-d')),
            'estado'    => (string) $this->input('estado', ''),
        ];
        $agenda = $control->consultarAgenda($filtro);
        if ($agenda === null) {
            $this->mostrarError($control);
        }

        $this->mostrarResultado($filtro, $medicos, $agenda, $control);
    }

    // Paso 6: mostrar agenda ordenada por hora o aviso sin resultados.
    private function mostrarResultado(array $filtro, array $medicos, ?array $agenda, CtrlAgenda $control): void
    {
        $this->view('citas/UI_AgendaMedica', [
            'filtro'      => $filtro,
            'medicos'     => $medicos,
            'agenda'      => $agenda,
            'aviso'       => $agenda === null ? $control->mensaje : ($agenda['citas'] === [] ? $control->mensaje : null),
            'esMedico'    => $control->medicoPropio !== null,
            'puedeGestionar' => Session::hasPermission('CIT_GESTIONAR'),
            'puedeAtender'   => Session::hasPermission('CIT_ATENDER'),
        ]);
    }

    // Alternativa: fecha inválida o médico fuera del alcance del usuario.
    private function mostrarError(CtrlAgenda $control): void
    {
        Session::flash('error', $control->mensaje);
    }
}
