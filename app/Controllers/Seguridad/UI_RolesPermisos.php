<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Session;

// CU04 boundary. Mensajes: abrirRoles(), editarRol(nombre, permisos[]), actualizarMatriz().
class UI_RolesPermisos extends Controller
{
    public function abrirRoles(): void
    {
        $datos = (new CtrlRol())->listarRolesYPermisos();

        // Rol seleccionado: el reintento tras un error, "Nuevo" o el elegido en la lista.
        $form = Session::get('_form_rol');
        Session::set('_form_rol', null);
        if ($form === null) {
            $form = $this->input('nuevo') !== null
                ? ['id_rol' => 0, 'nombre' => '', 'descripcion' => '', 'estado' => '1', 'permisos' => []]
                : $this->datosFormulario($datos, (int) $this->input('rol', $datos['roles'][0]['id_rol'] ?? 0));
        }

        $this->view('seguridad/UI_RolesPermisos', $datos + [
            'form'       => $form,
            'campoError' => Session::flash('campo_error'),
        ]);
    }

    public function editarRol(): void
    {
        $datos = [
            'id_rol'      => (int) $this->input('id_rol', 0),
            'nombre'      => (string) $this->input('nombre', ''),
            'descripcion' => (string) $this->input('descripcion', ''),
            'estado'      => (string) $this->input('estado', '1'),
            'permisos'    => (array) ($_POST['permisos'] ?? []),
        ];

        $control = new CtrlRol();
        if (!$control->guardarRol($datos)) {
            Session::flash('error', $control->mensaje);
            Session::flash('campo_error', (string) $control->campoError);
            $datos['permisos'] = array_map('intval', $datos['permisos']);
            Session::set('_form_rol', $datos);
            $this->redirect('/roles' . ($datos['id_rol'] > 0 ? '?rol=' . $datos['id_rol'] : '?nuevo=1'));
        }

        $this->actualizarMatriz($control->idRol, $control->mensaje);
    }

    public function actualizarMatriz(int $idRol, string $mensaje): void
    {
        Session::flash('success', $mensaje);
        $this->redirect('/roles?rol=' . $idRol);
    }

    private function datosFormulario(array $datos, int $idRol): ?array
    {
        $idPorCodigo = [];
        foreach ($datos['permisos'] as $lista) {
            foreach ($lista as $permiso) {
                $idPorCodigo[$permiso['codigo']] = (int) $permiso['id_permiso'];
            }
        }

        foreach ($datos['roles'] as $rol) {
            if ((int) $rol['id_rol'] === $idRol) {
                return [
                    'id_rol'      => $idRol,
                    'nombre'      => $rol['nombre'],
                    'descripcion' => (string) $rol['descripcion'],
                    'estado'      => $rol['estado'] ? '1' : '0',
                    'permisos'    => array_values(array_intersect_key($idPorCodigo, array_flip($rol['permisos']))),
                ];
            }
        }

        return null;
    }
}
