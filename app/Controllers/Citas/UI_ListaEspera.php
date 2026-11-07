<?php

namespace App\Controllers\Citas;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\SolicitudEspera;

// CU21 boundary. Mensajes: solicitar(registrarSolicitud), mostrarResultado(), mostrarError().
class UI_ListaEspera extends Controller
{
    private const CAMPOS = ['paciente', 'id_especialidad', 'id_medico', 'fecha_desde', 'fecha_hasta', 'preferencias'];

    // Pasos 1 y 2: paciente, especialidad y preferencias. POST: registrarSolicitud(datos)
    // (registrar, aceptar o rechazar una oferta, o retirar la solicitud).
    public function solicitar(): void
    {
        $control = new CtrlListaEspera();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = ['accion' => (string) $this->input('accion', 'registrar'), 'id_solicitud' => (int) $this->input('id_solicitud', 0)];
            foreach (self::CAMPOS as $campo) {
                $datos[$campo] = (string) $this->input($campo, '');
            }
            if ($control->registrarSolicitud($datos)) {
                $this->mostrarResultado($control);
            }
            $this->mostrarError($control, $datos);
        }

        $estado = (string) $this->input('estado', '');
        $form = Session::get('_form_espera');
        Session::set('_form_espera', null);

        $this->view('citas/UI_ListaEspera', [
            'solicitudes'    => $control->listar(isset(SolicitudEspera::ESTADOS[$estado]) ? $estado : null),
            'estado'         => $estado,
            'form'           => $form ?? array_fill_keys(self::CAMPOS, '') + ['paciente' => (string) $this->input('paciente', '')],
            'abrirForm'      => $form !== null || $this->input('nuevo') !== null,
            'especialidades' => (new Especialidad())->listarActivas(),
            'medicos'        => (new Medico())->buscar(['estado' => true]),
            'campoError'     => Session::flash('campo_error'),
        ]);
    }

    // Paso 4 / 6: solicitud en cola u oferta resuelta.
    private function mostrarResultado(CtrlListaEspera $control): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect('/lista-espera');
    }

    // Alternativas: duplicado o cupo ocupado al aceptar la oferta.
    private function mostrarError(CtrlListaEspera $control, array $datos): never
    {
        Session::flash('error', $control->mensaje);
        if ($datos['accion'] === 'registrar') {
            Session::set('_form_espera', $datos);
            Session::flash('campo_error', (string) $control->campoError);
        }
        $this->redirect('/lista-espera');
    }
}
