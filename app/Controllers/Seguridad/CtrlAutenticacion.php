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
    public string $mensajeFallo = 'Usuario o contraseña incorrectos.';
    public int $esperaSegundos = 0;

    public function autenticar(string $usuario, string $clave): bool
    {
        $usuarios = new Usuario();
        $fila = $usuarios->buscarPorUsuario($usuario);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($fila === null) {
            return false;
        }

        if (!$fila['estado']) {
            $this->mensajeFallo = 'Su cuenta está bloqueada. Contacte al administrador.';
            return false;
        }

        $espera = $usuarios->segundosDeEspera($fila);
        if ($espera > 0) {
            $this->esperaSegundos = $espera;
            $this->mensajeFallo = $this->mensajeEspera($espera);
            return false;
        }

        if (!$usuarios->verificarClave($fila, $clave)) {
            (new Bitacora())->registrar('LOGIN_FALLIDO', $ip, (int) $fila['id_usuario']);
            $actual = $usuarios->buscarPorUsuario($usuario) ?? $fila;
            if (!$actual['estado']) {
                $this->mensajeFallo = 'Su cuenta está bloqueada. Contacte al administrador.';
                return false;
            }
            $this->esperaSegundos = $usuarios->segundosDeEspera($actual);
            if ($this->esperaSegundos > 0) {
                $this->mensajeFallo = 'Usuario o contraseña incorrectos. ' . $this->mensajeEspera($this->esperaSegundos);
            }
            return false;
        }

        $permisos = (new Rol())->obtenerPermisos((int) $fila['id_rol']);
        (new Bitacora())->registrar('LOGIN', $ip, (int) $fila['id_usuario']);

        session_regenerate_id(true);
        Session::set('usuario', [
            'id_usuario' => $fila['id_usuario'],
            'username'   => $fila['username'],
            'id_rol'     => $fila['id_rol'],
            'rol'        => $fila['rol'],
        ]);
        Session::set('permisos', $permisos);

        (new UI_PanelPrincipal())->redirigir((string) $fila['rol']);
        return true;
    }

    private function mensajeEspera(int $segundos): string
    {
        $unidad = $segundos === 1 ? 'segundo' : 'segundos';

        return "Espere {$segundos} {$unidad} para volver a intentar.";
    }

    public function cerrarSesion(): void
    {
        $usuario = Session::user();
        if ($usuario !== null) {
            (new Bitacora())->registrar('LOGOUT', $_SERVER['REMOTE_ADDR'] ?? null, (int) $usuario['id_usuario']);
        }

        $pantalla = new UI_IniciarSesion();
        $pantalla->destruirSesion();
        $pantalla->mostrarMensaje('Sesión cerrada');
        $pantalla->redirigir();
    }
}
