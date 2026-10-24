<?php

namespace App\Controllers\Seguridad;

use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\RolPermiso;

// CU04 control. Mensajes: listarRolesYPermisos(), guardarRol(datos).
class CtrlRol
{
    // CU04 E1: permisos de seguridad que el rol Administrador no puede perder.
    private const ROL_ADMINISTRADOR = 'Administrador';
    private const PERMISOS_SEGURIDAD = ['USU_GESTIONAR', 'ROL_GESTIONAR', 'BIT_CONSULTAR'];

    public ?array $usuarioSesion;

    public string $mensaje = '';

    public ?string $campoError = null;

    public int $idRol = 0;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU04 mensaje 2 → 3: listar(), 4: listarPorModulo().
    public function listarRolesYPermisos(): array
    {
        $rol = new Rol();
        $roles = $rol->listar();
        foreach ($roles as &$fila) {
            $fila['permisos'] = $rol->obtenerPermisos((int) $fila['id_rol']);
        }

        return [
            'roles'    => $roles,
            'permisos' => (new Permiso())->listarPorModulo(),
        ];
    }

    // CU04 mensaje 6 → 7, 8, 9, 10.
    public function guardarRol(array $datos): bool
    {
        $roles = new Rol();
        $this->idRol = (int) ($datos['id_rol'] ?? 0);
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $estado = ($datos['estado'] ?? '1') === '1';
        $marcados = array_map('intval', (array) ($datos['permisos'] ?? []));

        $actual = null;
        if ($this->idRol > 0) {
            $actual = $roles->obtenerRol($this->idRol);
            if ($actual === null) {
                $this->mensaje = 'El rol no existe.';
                return false;
            }
        }

        if ($nombre === '' || mb_strlen($nombre) > 40) {
            return $this->invalido('nombre', 'Ingrese el nombre del rol (máximo 40 caracteres).');
        }
        if (mb_strlen($descripcion) > 150) {
            return $this->invalido('descripcion', 'La descripción admite hasta 150 caracteres.');
        }

        // CU04 mensaje 7: existeNombre(). E2: el nombre ya existe.
        if ($roles->existeNombre($nombre, $this->idRol ?: null)) {
            return $this->invalido('nombre', 'Ya existe un rol con ese nombre.');
        }

        $grupos = (new Permiso())->listarPorModulo();
        $porId = [];
        foreach ($grupos as $lista) {
            foreach ($lista as $permiso) {
                $porId[(int) $permiso['id_permiso']] = $permiso['codigo'];
            }
        }
        $marcados = array_values(array_intersect($marcados, array_keys($porId)));

        // E1: el rol Administrador conserva sus permisos de seguridad y no se desactiva.
        if ($actual !== null && $actual['nombre'] === self::ROL_ADMINISTRADOR) {
            $codigos = array_map(fn ($id) => $porId[$id], $marcados);
            if (array_diff(self::PERMISOS_SEGURIDAD, $codigos) !== []) {
                return $this->invalido('permisos', 'No se pueden quitar al rol Administrador los permisos de seguridad.');
            }
            if ($nombre !== self::ROL_ADMINISTRADOR || !$estado) {
                return $this->invalido('nombre', 'El rol Administrador no puede renombrarse ni desactivarse.');
            }
        }

        // Paso 6: el rol y sus permisos se guardan en una transacción.
        $rolPermiso = new RolPermiso();
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $datosRol = ['nombre' => $nombre, 'descripcion' => $descripcion !== '' ? $descripcion : null, 'estado' => $estado];
            if ($actual === null) {
                $this->idRol = $roles->insertar($datosRol);
                $anteriores = [];
            } else {
                $roles->actualizar($this->idRol, $datosRol);
                $anteriores = array_keys(array_intersect($porId, $roles->obtenerPermisos($this->idRol)));
            }

            // CU04 mensaje 9: asignar() / revocar().
            foreach (array_diff($marcados, $anteriores) as $idPermiso) {
                $rolPermiso->asignar($this->idRol, $idPermiso);
            }
            foreach (array_diff($anteriores, $marcados) as $idPermiso) {
                $rolPermiso->revocar($this->idRol, $idPermiso);
            }

            // CU04 mensaje 10: registrar("ROL", detalle).
            (new Bitacora())->registrar(
                (int) ($this->usuarioSesion['id_usuario'] ?? 0),
                'ROL',
                'Seguridad',
                ($actual === null ? 'Registró ' : 'Modificó ') . $nombre . ' (' . count($marcados) . ' permisos)'
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->mensaje = $actual === null
            ? 'Rol registrado correctamente.'
            : 'Cambios guardados. Se aplicarán en el siguiente inicio de sesión de los usuarios con este rol.';

        return true;
    }

    private function invalido(string $campo, string $mensaje): bool
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return false;
    }
}
