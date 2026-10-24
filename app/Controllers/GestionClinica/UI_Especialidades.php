<?php

namespace App\Controllers\GestionClinica;

use App\Core\Controller;
use App\Core\Session;

// CU07 boundary. Mensajes: abrirEspecialidades(), registrarOModificar(nombre, descripción), mostrarConfirmacion().
class UI_Especialidades extends Controller
{
    public function abrirEspecialidades(): void
    {
        $especialidades = (new CtrlEspecialidad())->listar();

        // Formulario: reintento tras un error, "Editar" o, por defecto, "Nueva especialidad".
        $form = Session::get('_form_especialidad');
        Session::set('_form_especialidad', null);
        if ($form === null && ($editar = (int) $this->input('editar', 0)) > 0) {
            foreach ($especialidades as $fila) {
                if ((int) $fila['id_especialidad'] === $editar) {
                    $form = [
                        'id_especialidad' => $editar,
                        'nombre'          => $fila['nombre'],
                        'descripcion'     => (string) $fila['descripcion'],
                        'estado'          => $fila['estado'] ? '1' : '0',
                    ];
                }
            }
        }
        $form ??= ['id_especialidad' => 0, 'nombre' => '', 'descripcion' => '', 'estado' => '1'];

        $this->view('gestion_clinica/UI_Especialidades', [
            'especialidades' => $especialidades,
            'form'           => $form,
            'campoError'     => Session::flash('campo_error'),
        ]);
    }

    public function registrarOModificar(): void
    {
        $datos = [
            'accion'          => (string) $this->input('accion', 'guardar'),
            'id_especialidad' => (int) $this->input('id_especialidad', 0),
            'nombre'          => (string) $this->input('nombre', ''),
            'descripcion'     => (string) $this->input('descripcion', ''),
            'estado'          => (string) $this->input('estado', '1'),
        ];

        $control = new CtrlEspecialidad();
        if (!$control->guardar($datos)) {
            Session::flash('error', $control->mensaje);
            if ($datos['accion'] !== 'estado') {
                Session::set('_form_especialidad', $datos);
                Session::flash('campo_error', (string) $control->campoError);
            }
            $this->redirect('/especialidades');
        }

        $this->mostrarConfirmacion($control->mensaje);
    }

    public function mostrarConfirmacion(string $mensaje): void
    {
        Session::flash('success', $mensaje);
        $this->redirect('/especialidades');
    }
}
