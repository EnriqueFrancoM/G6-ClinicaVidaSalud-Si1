<?php

namespace App\Controllers\GestionClinica;

use App\Core\Controller;
use App\Core\Session;

// CU08 boundary. Mensajes: abrirMedicos(), registrarOModificar(datos, especialidades[]), mostrarConfirmacion().
class UI_Medicos extends Controller
{
    public function abrirMedicos(): void
    {
        // Filtros: por defecto, solo médicos activos (prototipo figura 20).
        $estado = (string) $this->input('estado', '1');
        $filtro = [
            'nombre'          => (string) $this->input('nombre', ''),
            'id_especialidad' => (int) $this->input('especialidad', 0) ?: null,
            'estado'          => $estado === '' ? null : $estado === '1',
        ];
        $datos = (new CtrlMedico())->listarMedicos($filtro);

        $form = Session::get('_form_medico');
        Session::set('_form_medico', null);
        if ($form === null && $this->input('nuevo') !== null) {
            $form = ['id_medico' => 0, 'codigo' => $datos['siguiente'], 'nro_registro_prof' => '', 'nombres' => '', 'apellidos' => '',
                'telefono' => '', 'email' => '', 'estado' => '1', 'especialidades' => [], 'crear_cuenta' => '0'];
        }
        if ($form === null && ($editar = (int) $this->input('editar', 0)) > 0) {
            foreach ((new CtrlMedico())->listarMedicos([])['medicos'] as $fila) {
                if ((int) $fila['id_medico'] === $editar) {
                    $form = [
                        'id_medico'         => $editar,
                        'codigo'            => $fila['codigo'],
                        'nro_registro_prof' => $fila['nro_registro_prof'],
                        'nombres'           => $fila['nombres'],
                        'apellidos'         => $fila['apellidos'],
                        'telefono'          => $fila['telefono'],
                        'email'             => (string) $fila['email'],
                        'estado'            => $fila['estado'] ? '1' : '0',
                        'especialidades'    => $fila['ids_especialidad'],
                        'crear_cuenta'      => '0',
                    ];
                }
            }
        }

        $this->view('gestion_clinica/UI_Medicos', $datos + [
            'filtros'    => ['nombre' => $filtro['nombre'], 'especialidad' => (int) $filtro['id_especialidad'], 'estado' => $estado],
            'form'       => $form,
            'campoError' => Session::flash('campo_error'),
        ]);
    }

    public function registrarOModificar(): void
    {
        $datos = [
            'accion'            => (string) $this->input('accion', 'guardar'),
            'id_medico'         => (int) $this->input('id_medico', 0),
            'codigo'            => (string) $this->input('codigo', ''),
            'nro_registro_prof' => (string) $this->input('nro_registro_prof', ''),
            'nombres'           => (string) $this->input('nombres', ''),
            'apellidos'         => (string) $this->input('apellidos', ''),
            'telefono'          => (string) $this->input('telefono', ''),
            'email'             => (string) $this->input('email', ''),
            'estado'            => (string) $this->input('estado', '1'),
            'especialidades'    => array_map('intval', (array) ($_POST['especialidades'] ?? [])),
            'crear_cuenta'      => (string) $this->input('crear_cuenta', '0'),
        ];

        $control = new CtrlMedico();
        if (!$control->guardarMedico($datos)) {
            Session::flash('error', $control->mensaje);
            if ($datos['accion'] !== 'estado') {
                Session::set('_form_medico', $datos);
                Session::flash('campo_error', (string) $control->campoError);
            }
            $this->redirect('/medicos');
        }

        $this->mostrarConfirmacion($control->mensaje);
    }

    public function mostrarConfirmacion(string $mensaje): void
    {
        Session::flash('success', $mensaje);
        $this->redirect('/medicos');
    }
}
