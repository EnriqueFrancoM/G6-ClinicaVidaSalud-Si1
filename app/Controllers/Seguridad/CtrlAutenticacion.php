<?php

namespace App\Controllers\Seguridad;

use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Usuario;
use App\Core\Session;

// CU01 control. Mensaje: autenticar(usuario, clave).
// CU02: cerrarSesion().
class CtrlAutenticacion
{
    // CU01 E1: mensaje genérico para cualquier fallo de credenciales.
    public const MENSAJE_FALLO = 'Usuario o contraseña incorrectos.';

    public ?array $usuarioSesion = null;

    public function autenticar(string $usuario, string $clave): bool
    {
        $usuarios = new Usuario();
        $bitacora = new Bitacora();
        $fila = $usuarios->buscarPorUsuario($usuario);

        // E1: la cuenta no existe.
        if ($fila === null) {
            $bitacora->registrar(null, 'LOGIN_FALLIDO', 'Seguridad', $usuario);
            return false;
        }

        // E1: la cuenta está inactiva.
        if (!$fila['estado']) {
            $usuarios->registrarIntentoFallido((int) $fila['id_usuario']);
            $bitacora->registrar((int) $fila['id_usuario'], 'LOGIN_FALLIDO', 'Seguridad');
            return false;
        }

        // E1: la contraseña no coincide (verificarClave incrementa los intentos).
        if (!$usuarios->verificarClave($fila, $clave)) {
            $bitacora->registrar((int) $fila['id_usuario'], 'LOGIN_FALLIDO', 'Seguridad');
            return false;
        }

        $permisos = (new Rol())->obtenerPermisos((int) $fila['id_rol']);
        $bitacora->registrar((int) $fila['id_usuario'], 'LOGIN', 'Seguridad');

        session_regenerate_id(true);
        $this->usuarioSesion = [
            'id_usuario' => $fila['id_usuario'],
            'username'   => $fila['username'],
            'id_rol'     => $fila['id_rol'],
            'rol'        => $fila['rol'],
            // CU05 E3: en el primer ingreso se obliga a cambiar la contraseña.
            'primer_ingreso' => in_array($fila['primer_ingreso'], [true, 't', '1', 1], true),
        ];
        Session::set('usuario', $this->usuarioSesion);
        Session::set('permisos', $permisos);
        Session::tocar();

        (new UI_PanelPrincipal())->redirigir((string) $fila['rol']);
        return true;
    }

    public function cerrarSesion(): void
    {
        $usuario = Session::user();
        if ($usuario !== null) {
            (new Bitacora())->registrar((int) $usuario['id_usuario'], 'LOGOUT', 'Seguridad');
        }

        $pantalla = new UI_IniciarSesion();
        $pantalla->destruirSesion();
        $pantalla->mostrarMensaje('Sesión cerrada');
        $pantalla->redirigir();
    }
}
