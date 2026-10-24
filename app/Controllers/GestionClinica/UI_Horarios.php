<?php

namespace App\Controllers\GestionClinica;

use App\Core\Controller;
use App\Core\Session;

// CU09 boundary. Mensajes: seleccionarMedico(id), definirHorario(día, inicio, fin, duración), mostrarAgendaSemanal().
// «extend» Registrar bloqueo de agenda: registrarBloqueo(fecha, motivo), quitarBloqueo(id).
class UI_Horarios extends Controller
{
    public function seleccionarMedico(): void
    {
        $idMedico = (int) $this->input('medico', 0);
        $datos = (new CtrlHorario())->obtenerHorarios($idMedico);
        if ($idMedico === 0 && $datos['medicos'] !== []) {
            $idMedico = (int) $datos['medicos'][0]['id_medico'];
            $datos = (new CtrlHorario())->obtenerHorarios($idMedico);
        }

        // Formulario: reintento (error o confirmación pendiente), "Editar" o "Agregar horario".
        $form = Session::get('_form_horario');
        Session::set('_form_horario', null);
        if ($form === null && ($editar = (int) $this->input('editar', 0)) > 0) {
            foreach ($datos['horarios'] as $fila) {
                if ((int) $fila['id_horario'] === $editar) {
                    $form = $fila + ['accion' => 'guardar'];
                }
            }
        }
        $form ??= ['id_horario' => 0, 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '12:00', 'duracion_cita_min' => 30, 'accion' => 'guardar'];

        $this->mostrarAgendaSemanal($datos + [
            'idMedico'     => $idMedico,
            'form'         => $form,
            'campoError'   => Session::flash('campo_error'),
            'confirmacion' => Session::flash('confirmacion'),
            'formBloqueo'  => $this->formularioBloqueo(),
        ]);
    }

    private function formularioBloqueo(): array
    {
        $form = Session::get('_form_bloqueo');
        Session::set('_form_bloqueo', null);
        return $form ?? ['alcance' => 'medico', 'fecha' => date('Y-m-d'), 'motivo' => ''];
    }

    public function definirHorario(): void
    {
        $datos = [
            'accion'            => (string) $this->input('accion', 'guardar'),
            'id_medico'         => (int) $this->input('id_medico', 0),
            'id_horario'        => (int) $this->input('id_horario', 0),
            'dia_semana'        => (int) $this->input('dia_semana', 0),
            'hora_inicio'       => (string) $this->input('hora_inicio', ''),
            'hora_fin'          => (string) $this->input('hora_fin', ''),
            'duracion_cita_min' => (int) $this->input('duracion_cita_min', 0),
            'confirmado'        => (string) $this->input('confirmado', '0'),
        ];
        $volver = '/horarios?medico=' . $datos['id_medico'];

        $control = new CtrlHorario();
        if (!$control->guardarHorario($datos)) {
            Session::flash($control->citasAfectadas > 0 ? 'confirmacion' : 'error', $control->mensaje);
            Session::set('_form_horario', $datos);
            Session::flash('campo_error', (string) $control->campoError);
            $this->redirect($volver);
        }

        Session::flash('success', $control->mensaje);
        $this->redirect($volver);
    }

    // «extend» Registrar bloqueo de agenda (feriado general o ausencia del médico).
    public function registrarBloqueo(): void
    {
        $datos = [
            'id_medico' => (int) $this->input('id_medico', 0),
            'alcance'   => (string) $this->input('alcance', 'medico'),
            'fecha'     => (string) $this->input('fecha', ''),
            'motivo'    => (string) $this->input('motivo', ''),
        ];
        $control = new CtrlHorario();
        if (!$control->registrarBloqueo($datos)) {
            Session::flash('error', $control->mensaje);
            Session::set('_form_bloqueo', $datos);
            Session::flash('campo_error', 'bloqueo_' . $control->campoError);
        } else {
            Session::flash('success', $control->mensaje);
        }
        $this->redirect('/horarios?medico=' . $datos['id_medico']);
    }

    public function quitarBloqueo(): void
    {
        $control = new CtrlHorario();
        Session::flash($control->quitarBloqueo((int) $this->input('id_bloqueo', 0)) ? 'success' : 'error', $control->mensaje);
        $this->redirect('/horarios?medico=' . (int) $this->input('id_medico', 0));
    }

    public function mostrarAgendaSemanal(array $datos): void
    {
        $this->view('gestion_clinica/UI_Horarios', $datos + ['dias' => CtrlHorario::DIAS, 'duraciones' => CtrlHorario::DURACIONES]);
    }
}
