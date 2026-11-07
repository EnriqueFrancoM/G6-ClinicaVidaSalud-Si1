<?php

namespace App\Controllers\Reportes;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Services\ExportadorReporte;

// CU19 boundary. Mensajes: solicitar(generarReporte), mostrarResultado(), mostrarError().
class UI_Reportes extends Controller
{
    // Pasos 1 y 2: elegir reporte e ingresar filtros → generarReporte(datos).
    // Con "formato" (pdf o xlsx) descarga ReporteCitas.pdf / ReporteCitas.xlsx.
    public function solicitar(): void
    {
        $filtro = [
            'tipo'            => (string) $this->input('tipo', 'citas'),
            'desde'           => (string) $this->input('desde', date('Y-m-01')),
            'hasta'           => (string) $this->input('hasta', date('Y-m-t')),
            'id_medico'       => (string) $this->input('id_medico', ''),
            'id_especialidad' => (string) $this->input('id_especialidad', ''),
            'estado'          => (string) $this->input('estado', ''),
            'formato'         => (string) $this->input('formato', ''),
        ];

        $control = new CtrlReporte();
        $resultado = $control->generarReporte($filtro);
        if ($resultado === null) {
            $this->mostrarError($control);
        } elseif (isset($resultado['archivo'])) {
            $this->descargar($resultado['archivo']);
        }

        $this->mostrarResultado($filtro, $resultado, $control);
    }

    // Paso 5: vista filtrada (el archivo exportado contiene el mismo conjunto, PN11).
    private function mostrarResultado(array $filtro, ?array $resultado, CtrlReporte $control): void
    {
        $this->view('reportes/UI_Reportes', [
            'filtro'         => $filtro,
            'resultado'      => $resultado,
            'aviso'          => $resultado !== null && $resultado['filas'] === [] ? $control->mensaje : null,
            'tipos'          => CtrlReporte::TIPOS,
            'formatos'       => ExportadorReporte::FORMATOS,
            'estados'        => Cita::ESTADOS,
            'medicos'        => (new Medico())->buscar(['estado' => null]),
            'especialidades' => (new Especialidad())->listar(),
        ]);
    }

    // Alternativas: sin resultados, rango inválido o exportador no disponible.
    private function mostrarError(CtrlReporte $control): void
    {
        Session::flash('error', $control->mensaje);
    }

    private function descargar(array $archivo): never
    {
        header('Content-Type: ' . $archivo['tipo']);
        header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
        header('Content-Length: ' . strlen($archivo['contenido']));
        header('Cache-Control: no-store');
        echo $archivo['contenido'];
        exit;
    }
}
