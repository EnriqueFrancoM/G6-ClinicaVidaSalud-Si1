<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;

// CU06 boundary. Mensajes: abrirBitacora(), aplicarFiltros(usuario, módulo, fechas),
// exportar(), mostrarRegistros(lista).
class UI_Bitacora extends Controller
{
    // Paso 2: por defecto, los registros del día.
    public function abrirBitacora(): void
    {
        $hoy = date('Y-m-d');
        $this->consultarYMostrar(['id_usuario' => '', 'modulo' => '', 'desde' => $hoy, 'hasta' => $hoy]);
    }

    // Pasos 3 y 4: el administrador presiona "Filtrar".
    public function aplicarFiltros(): void
    {
        $this->consultarYMostrar($this->leerFiltros());
    }

    // Paso 5: "Exportar CSV" con los filtros actuales.
    public function exportar(): void
    {
        $filtros = $this->leerFiltros();
        $control = new CtrlBitacora();
        $csv = $control->exportarCSV($filtros);
        if ($csv === null) {
            Session::flash('error', $control->mensaje);
            $this->redirect('/bitacora/filtrar?' . http_build_query($filtros));
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bitacora_' . date('Ymd_His') . '.csv"');
        echo $csv;
    }

    public function mostrarRegistros(array $lista, array $filtros, int $pagina, ?string $campoError = null): void
    {
        $this->view('seguridad/UI_Bitacora', $lista + [
            'filtros'    => $filtros,
            'pagina'     => $pagina,
            'porPagina'  => CtrlBitacora::POR_PAGINA,
            'campoError' => $campoError,
        ]);
    }

    private function consultarYMostrar(array $filtros): void
    {
        $pagina = max(1, (int) $this->input('pagina', 1));
        $control = new CtrlBitacora();
        $lista = $control->consultar($filtros, $pagina);

        if ($lista === null) {
            // E2: se muestran los filtros para corregirlos, sin registros.
            Session::flash('error', $control->mensaje);
            $opciones = (new CtrlBitacora())->consultar([], 1);
            $lista = ['registros' => [], 'total' => 0] + $opciones;
        }

        $this->mostrarRegistros($lista, $filtros, $pagina, $control->campoError);
    }

    private function leerFiltros(): array
    {
        return [
            'id_usuario' => (string) $this->input('usuario', ''),
            'modulo'     => (string) $this->input('modulo', ''),
            'desde'      => (string) $this->input('desde', ''),
            'hasta'      => (string) $this->input('hasta', ''),
        ];
    }
}
