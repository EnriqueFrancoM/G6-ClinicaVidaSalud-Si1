<?php

namespace App\Controllers\Citas;

use App\Core\Controller;
use App\Core\Session;

// CU16 boundary. Mensajes: solicitar(consultarHistorial), mostrarResultado(), mostrarError().
class UI_HistorialCitas extends Controller
{
    // Pasos 1 y 2: buscar paciente y seleccionar período → consultarHistorial(datos).
    public function solicitar(): void
    {
        $filtro = [
            'criterio' => (string) $this->input('criterio', ''),
            'desde'    => (string) $this->input('desde', ''),
            'hasta'    => (string) $this->input('hasta', ''),
        ];
        $control = new CtrlHistorialCitas();
        $historial = null;
        if ($filtro['criterio'] !== '') {
            $historial = $control->consultarHistorial($filtro);
            if ($historial === null) {
                $this->mostrarError($control);
            }
        }

        $this->mostrarResultado($filtro, $historial, $control);
    }

    // Paso 6: mostrar historial (incluye canceladas y pasadas) o sin resultados.
    private function mostrarResultado(array $filtro, ?array $historial, CtrlHistorialCitas $control): void
    {
        $this->view('citas/UI_HistorialCitas', [
            'filtro'    => $filtro,
            'historial' => $historial,
            'aviso'     => $historial !== null && $historial['citas'] === [] ? $control->mensaje : null,
        ]);
    }

    // Alternativas: paciente inexistente o período inválido.
    private function mostrarError(CtrlHistorialCitas $control): void
    {
        Session::flash('error', $control->mensaje);
    }
}
