<?php

namespace App\Controllers\Reportes;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Especialidad;
use App\Models\Medico;

// CU22 boundary. Mensajes: solicitar(calcularIndicadores), mostrarResultado(), mostrarError().
class UI_Indicadores extends Controller
{
    // Pasos 1 y 2: seleccionar período y filtros → calcularIndicadores(datos).
    public function solicitar(): void
    {
        $filtro = [
            'desde'           => (string) $this->input('desde', date('Y-m-01')),
            'hasta'           => (string) $this->input('hasta', date('Y-m-t')),
            'id_medico'       => (string) $this->input('id_medico', ''),
            'id_especialidad' => (string) $this->input('id_especialidad', ''),
        ];
        $control = new CtrlIndicador();
        $indicadores = $control->calcularIndicadores($filtro);
        if ($indicadores === null) {
            $this->mostrarError($control);
        }

        $this->mostrarResultado($filtro, $indicadores, $control);
    }

    // Paso 6: tarjetas y resumen.
    private function mostrarResultado(array $filtro, ?array $indicadores, CtrlIndicador $control): void
    {
        $this->view('reportes/UI_Indicadores', [
            'filtro'         => $filtro,
            'indicadores'    => $indicadores,
            'aviso'          => $indicadores !== null ? ($control->mensaje ?: null) : null,
            'medicos'        => (new Medico())->buscar(['estado' => null]),
            'especialidades' => (new Especialidad())->listar(),
        ]);
    }

    // Alternativas: período inválido o ausencia de datos.
    private function mostrarError(CtrlIndicador $control): void
    {
        Session::flash('error', $control->mensaje);
    }
}
