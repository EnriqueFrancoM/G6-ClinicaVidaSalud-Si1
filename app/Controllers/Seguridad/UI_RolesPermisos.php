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

        // El diálogo de nombre solo se abre en Nuevo, al editar datos o tras un error.
        $form = Session::get('_form_rol');
        Session::set('_form_rol', null);
        $editarMeta = $form !== null || $this->input('nuevo') !== null || $this->input('datos') !== null;
        if ($form === null && $editarMeta) {
            $form = $this->input('nuevo') !== null
                ? ['id_rol' => 0, 'nombre' => '', 'descripcion' => '', 'estado' => '1', 'permisos' => []]
                : $this->datosFormulario($datos, (int) $this->input('datos', 0));
        }

        $this->view('seguridad/UI_RolesPermisos', $datos + [
            'form'       => $form,
            'editarMeta' => $editarMeta && $form !== null,
            'campoError' => Session::flash('campo_error'),
        ]);
    }

    public function editarRol(): void
    {
        if ((string) $this->input('accion') === 'matriz') {
            $this->guardarMatriz((array) ($_POST['matriz'] ?? []));
            return;
        }

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
            $this->redirect('/roles' . ($datos['id_rol'] > 0 ? '?datos=' . $datos['id_rol'] : '?nuevo=1'));
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
                    'estado'      => ($rol['estado'] === true || $rol['estado'] === 't' || $rol['estado'] === '1' || $rol['estado'] === 1) ? '1' : '0',
                    'permisos'    => array_values(array_intersect_key($idPorCodigo, array_flip($rol['permisos']))),
                ];
            }
        }

        return null;
    }

    // La matriz envía matriz[id_rol][] y cada rol cambiado pasa por guardarRol().
    private function guardarMatriz(array $matriz): void
    {
        $control = new CtrlRol();
        $datos = $control->listarRolesYPermisos();
        $idPorCodigo = [];
        foreach ($datos['permisos'] as $lista) {
            foreach ($lista as $permiso) {
                $idPorCodigo[$permiso['codigo']] = (int) $permiso['id_permiso'];
            }
        }

        $hubo = false;
        foreach ($datos['roles'] as $rol) {
            $id = (int) $rol['id_rol'];
            $marcados = array_values(array_unique(array_map('intval', (array) ($matriz[$id] ?? []))));
            sort($marcados);
            $actuales = [];
            foreach ($rol['permisos'] as $codigo) {
                if (isset($idPorCodigo[$codigo])) {
                    $actuales[] = $idPorCodigo[$codigo];
                }
            }
            sort($actuales);
            if ($marcados === $actuales) {
                continue;
            }

            $activo = $rol['estado'] === true || $rol['estado'] === 't' || $rol['estado'] === '1' || $rol['estado'] === 1;
            if (!$control->guardarRol([
                'id_rol'      => $id,
                'nombre'      => $rol['nombre'],
                'descripcion' => (string) $rol['descripcion'],
                'estado'      => $activo ? '1' : '0',
                'permisos'    => $marcados,
            ])) {
                Session::flash('error', $rol['nombre'] . ': ' . $control->mensaje);
                $this->redirect('/roles');
                return;
            }
            $hubo = true;
        }

        Session::flash('success', $hubo
            ? 'Permisos actualizados. Se aplicarán en el siguiente inicio de sesión.'
            : 'Los permisos ya estaban así.');
        $this->redirect('/roles');
    }
}
