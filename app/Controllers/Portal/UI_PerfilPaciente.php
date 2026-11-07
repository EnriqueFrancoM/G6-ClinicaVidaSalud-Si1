<?php

namespace App\Controllers\Portal;

use App\Controllers\GestionClinica\CtrlPaciente;
use App\Core\Controller;
use App\Core\Session;

// CU17 boundary. Mensajes: solicitar(guardarPerfil), mostrarResultado(), mostrarError().
// Sin sesión muestra el registro del paciente; con sesión, su perfil.
class UI_PerfilPaciente extends Controller
{
    private const CAMPOS = ['ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'email', 'direccion',
        'tipo_sangre', 'alergias', 'enfermedades_cronicas', 'contacto_nombre', 'contacto_parentesco', 'contacto_telefono'];

    public function solicitar(): void
    {
        $registro = Session::user() === null;
        $control = new CtrlPerfil();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = ['username' => (string) $this->input('username', ''), 'clave' => (string) ($_POST['clave'] ?? ''),
                'confirmar' => (string) ($_POST['confirmar'] ?? '')];
            if (($id = $this->input('id_paciente')) !== null) {
                $datos['id_paciente'] = $id;
            }
            foreach (self::CAMPOS as $campo) {
                $datos[$campo] = (string) $this->input($campo, '');
            }
            if ($control->guardarPerfil($datos)) {
                $this->mostrarResultado($control, $registro);
            }
            unset($datos['clave'], $datos['confirmar']);
            $this->mostrarError($control, $datos, $registro);
        }

        $form = Session::get('_form_perfil');
        Session::set('_form_perfil', null);
        $perfil = $registro ? null : $control->obtenerPerfil();
        if ($form === null) {
            $form = array_fill_keys(self::CAMPOS, '') + ['username' => ''];
            foreach (self::CAMPOS as $campo) {
                $form[$campo] = (string) ($perfil['ficha'][$campo] ?? '');
            }
            if ($perfil !== null && $form['email'] === '') {
                $form['email'] = (string) $perfil['titular']['email'];
            }
        }

        $this->view('portal/UI_PerfilPaciente', [
            'registro'   => $registro,
            'perfil'     => $perfil,
            'form'       => $form,
            'sexos'      => CtrlPaciente::SEXOS,
            'campoError' => Session::flash('campo_error'),
        ], $registro ? 'auth' : 'main');
    }

    // Paso 6: mostrar confirmación.
    private function mostrarResultado(CtrlPerfil $control, bool $registro): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect($registro ? '/login' : '/portal/perfil');
    }

    // Alternativas: correo/CI duplicado o intento de editar otro perfil.
    private function mostrarError(CtrlPerfil $control, array $datos, bool $registro): never
    {
        Session::flash('error', $control->mensaje);
        Session::flash('campo_error', (string) $control->campoError);
        Session::set('_form_perfil', $datos);
        $this->redirect($registro ? '/registro' : '/portal/perfil');
    }
}
