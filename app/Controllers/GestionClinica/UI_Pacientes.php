<?php

namespace App\Controllers\GestionClinica;

use App\Core\Controller;
use App\Core\Session;

// CU10 boundary. Mensajes: buscarPaciente(CI / nombre), registrarOModificar(datos), mostrarFicha().
class UI_Pacientes extends Controller
{
    private const CAMPOS = ['ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'email', 'direccion',
        'tipo_sangre', 'alergias', 'enfermedades_cronicas', 'contacto_nombre', 'contacto_parentesco', 'contacto_telefono'];

    // Pasos 2 y 3: buscador por CI, nombre o apellido (por defecto, pacientes activos).
    public function buscarPaciente(): void
    {
        $estado = (string) $this->input('estado', '1');
        $criterio = [
            'ci'     => (string) $this->input('ci', ''),
            'nombre' => (string) $this->input('nombre', ''),
            'estado' => $estado === '' ? null : $estado === '1',
        ];
        $control = new CtrlPaciente();

        // Pasos 4 y 5: "Nuevo paciente", "Editar" o el reintento tras un error.
        $form = Session::get('_form_paciente');
        Session::set('_form_paciente', null);
        if ($form === null && $this->input('nuevo') !== null) {
            $form = array_fill_keys(self::CAMPOS, '') + ['id_paciente' => 0, 'estado' => '1'];
        }
        if ($form === null && ($editar = (int) $this->input('editar', 0)) > 0 && ($ficha = $control->obtenerFicha($editar)) !== null) {
            $form = ['id_paciente' => $editar, 'estado' => $ficha['estado'] ? '1' : '0'];
            foreach (self::CAMPOS as $campo) {
                $form[$campo] = (string) ($ficha[$campo] ?? '');
            }
        }

        $this->view('gestion_clinica/UI_Pacientes', [
            'pacientes'  => $control->buscar($criterio),
            'filtros'    => ['ci' => $criterio['ci'], 'nombre' => $criterio['nombre'], 'estado' => $estado],
            'form'       => $form,
            'campoError' => Session::flash('campo_error'),
            'existente'  => Session::get('_paciente_existente'),
            'sexos'      => CtrlPaciente::SEXOS,
        ]);
        Session::set('_paciente_existente', null);
    }

    public function registrarOModificar(): void
    {
        $datos = ['accion' => (string) $this->input('accion', 'guardar'), 'id_paciente' => (int) $this->input('id_paciente', 0),
            'estado' => (string) $this->input('estado', '1')];
        foreach (self::CAMPOS as $campo) {
            $datos[$campo] = (string) $this->input($campo, '');
        }

        $control = new CtrlPaciente();
        if (!$control->guardarPaciente($datos)) {
            Session::flash('error', $control->mensaje);
            if ($datos['accion'] !== 'desactivar') {
                Session::set('_form_paciente', $datos);
                Session::flash('campo_error', (string) $control->campoError);
                Session::set('_paciente_existente', $control->existente);
            }
            $this->redirect('/pacientes');
        }

        Session::flash('success', $control->mensaje);
        $this->redirect('/pacientes/ficha?id=' . $control->idPaciente);
    }

    // Paso 9 / mensaje 11: ficha del paciente con la opción "Historial de citas".
    public function mostrarFicha(): void
    {
        $ficha = (new CtrlPaciente())->obtenerFicha((int) $this->input('id', 0));
        if ($ficha === null) {
            Session::flash('error', 'El paciente no existe.');
            $this->redirect('/pacientes');
        }

        $this->view('gestion_clinica/UI_FichaPaciente', [
            'paciente'      => $ficha,
            'sexos'         => CtrlPaciente::SEXOS,
            'verHistorial'  => $this->input('historial') !== null,
        ]);
    }
}
