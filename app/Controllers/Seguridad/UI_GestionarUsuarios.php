<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;

// CU03 boundary. Mensajes: abrirUsuarios(), registrarOModificar(datos), mostrarConfirmacion().
class UI_GestionarUsuarios extends Controller
{
    public function abrirUsuarios(): void
    {
        $nombre = (string) $this->input('buscar', '');
        $idRol = $this->input('rol', '') !== '' ? (int) $this->input('rol') : null;
        $estado = match ((string) $this->input('estado', '')) {
            '1'     => true,
            '0'     => false,
            default => null,
        };

        $datos = (new CtrlUsuario())->listarUsuarios($nombre, $idRol, $estado);

        // Formulario abierto: "Nuevo usuario", "Editar" o el reintento tras un error.
        $form = Session::get('_form_usuario');
        Session::set('_form_usuario', null);
        if ($form === null && $this->input('nuevo') !== null) {
            $form = ['id_usuario' => 0, 'username' => '', 'email' => '', 'id_rol' => '', 'estado' => '1', 'vinculo' => ''];
        }
        if ($form === null && ($editar = (int) $this->input('editar', 0)) > 0) {
            $form = $this->datosFormulario($datos['usuarios'], $editar);
        }

        $this->view('seguridad/UI_GestionarUsuarios', $datos + [
            'filtros'    => ['buscar' => $nombre, 'rol' => $idRol, 'estado' => $this->input('estado', '')],
            'form'       => $form,
            'campoError' => Session::flash('campo_error'),
            'idSesion'   => (int) (Session::user()['id_usuario'] ?? 0),
        ]);
    }

    public function registrarOModificar(): void
    {
        $datos = [
            'accion'     => (string) $this->input('accion', 'guardar'),
            'id_usuario' => (int) $this->input('id_usuario', 0),
            'username'   => (string) $this->input('username', ''),
            'email'      => (string) $this->input('email', ''),
            'id_rol'     => (string) $this->input('id_rol', ''),
            'estado'     => (string) $this->input('estado', '1'),
            'vinculo'    => (string) $this->input('vinculo', ''),
        ];

        $control = new CtrlUsuario();
        if (!$control->guardarUsuario($datos)) {
            Session::flash('error', $control->mensaje);
            if ($datos['accion'] !== 'estado') {
                Session::set('_form_usuario', $datos);
                Session::flash('campo_error', (string) $control->campoError);
            }
            $this->redirect('/usuarios');
        }

        $this->mostrarConfirmacion($control->mensaje);
    }

    public function mostrarConfirmacion(string $mensaje): void
    {
        Session::flash('success', $mensaje);
        $this->redirect('/usuarios');
    }

    private function datosFormulario(array $usuarios, int $idUsuario): ?array
    {
        foreach ($usuarios as $fila) {
            if ((int) $fila['id_usuario'] === $idUsuario) {
                $vinculo = $fila['id_medico'] !== null ? 'medico:' . $fila['id_medico']
                    : ($fila['id_paciente'] !== null ? 'paciente:' . $fila['id_paciente'] : '');

                return [
                    'id_usuario' => $idUsuario,
                    'username'   => $fila['username'],
                    'email'      => $fila['email'],
                    'id_rol'     => (string) $fila['id_rol'],
                    'estado'     => $fila['estado'] ? '1' : '0',
                    'vinculo'    => $vinculo,
                ];
            }
        }

        return null;
    }
}
