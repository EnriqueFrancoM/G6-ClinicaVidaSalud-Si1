<?php

namespace App\Controllers\Reportes;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Recordatorio;

// CU20 boundary «servicio». Mensajes: solicitar(procesarPendientes), mostrarResultado(), mostrarError().
// El actor Tiempo lo dispara con scripts/enviar-recordatorios.php (cron); el seguimiento (PN12)
// permite revisar los envíos y ejecutarlo manualmente.
class ServicioRecordatorios extends Controller
{
    // GET: seguimiento de recordatorios. POST: ejecución manual de procesarPendientes(datos).
    public function solicitar(): void
    {
        $control = new CtrlRecordatorio();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$control->validarAcceso()) {
                $this->mostrarError('No tiene permiso para ejecutar los recordatorios.');
            }
            $resumen = $control->procesarPendientes(['instante' => date('Y-m-d H:i:s')]);
            $this->mostrarResultado($control->mensaje, $resumen['detalle']);
        }

        $estado = (string) $this->input('estado', '');
        $this->view('reportes/ServicioRecordatorios', [
            'recordatorios' => (new Recordatorio())->listar(isset(Recordatorio::ESTADOS[$estado]) ? $estado : null),
            'estado'        => $estado,
            'detalle'       => Session::get('_detalle_recordatorios') ?? [],
        ]);
        Session::set('_detalle_recordatorios', null);
    }

    // Ejecución por el actor Tiempo (sin sesión). Devuelve el resumen.
    public static function ejecutarProgramado(): array
    {
        return (new CtrlRecordatorio())->procesarPendientes(['instante' => date('Y-m-d H:i:s')]);
    }

    // RF21: enlace del recordatorio para confirmar o cancelar la cita.
    public function responder(): void
    {
        $control = new CtrlRecordatorio();
        $ok = $control->responder((int) $this->input('c', 0), (string) $this->input('r', ''), (string) $this->input('t', ''));
        $this->view('reportes/RespuestaRecordatorio', ['ok' => $ok, 'mensaje' => $control->mensaje], 'auth');
    }

    // Paso 6: envío registrado o fallo controlado.
    private function mostrarResultado(string $mensaje, array $detalle): never
    {
        Session::flash('success', $mensaje);
        Session::set('_detalle_recordatorios', $detalle);
        $this->redirect('/recordatorios');
    }

    private function mostrarError(string $mensaje): never
    {
        Session::flash('error', $mensaje);
        $this->redirect('/recordatorios');
    }
}
