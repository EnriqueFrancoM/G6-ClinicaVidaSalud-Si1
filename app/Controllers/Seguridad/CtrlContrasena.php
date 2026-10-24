<?php

namespace App\Controllers\Seguridad;

use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Usuario;

// CU05 control. Mensaje: cambiarContrasena(datos).
class CtrlContrasena
{
    public ?array $usuarioSesion;

    public string $mensaje = '';

    public ?string $campoError = null;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU05 mensaje 2 → 3, 4, 5, 6.
    public function cambiarContrasena(array $datos): bool
    {
        $actual = (string) ($datos['actual'] ?? '');
        $nueva = (string) ($datos['nueva'] ?? '');
        $confirmacion = (string) ($datos['confirmacion'] ?? '');

        if ($actual === '' || $nueva === '' || $confirmacion === '') {
            return $this->invalido($actual === '' ? 'actual' : ($nueva === '' ? 'nueva' : 'confirmacion'), 'Complete todos los campos.');
        }

        $usuarios = new Usuario();
        $fila = $usuarios->buscarPorUsuario((string) ($this->usuarioSesion['username'] ?? ''));
        if ($fila === null) {
            $this->mensaje = 'No se encontró la cuenta de la sesión.';
            return false;
        }

        // Mensaje 3: verificarClave(actual). E1: la contraseña actual es incorrecta.
        if (!$usuarios->verificarClave($fila, $actual)) {
            return $this->invalido('actual', 'La contraseña actual es incorrecta.');
        }

        // Mensaje 4: validarPolitica(nueva). E2: no cumple la política o no coincide.
        if (!$usuarios->validarPolitica($nueva)) {
            return $this->invalido('nueva', 'La nueva contraseña debe tener mínimo 8 caracteres, al menos una mayúscula, un número y un símbolo.');
        }
        if ($nueva !== $confirmacion) {
            return $this->invalido('confirmacion', 'La confirmación no coincide con la nueva contraseña.');
        }

        // Mensaje 5: actualizarClave(password_hash).
        $usuarios->actualizarClave((int) $fila['id_usuario'], password_hash($nueva, PASSWORD_DEFAULT));
        // Mensaje 6: registrar("CAMBIO_CLAVE").
        (new Bitacora())->registrar((int) $fila['id_usuario'], 'CAMBIO_CLAVE', 'Seguridad');

        $this->usuarioSesion['primer_ingreso'] = false;
        Session::set('usuario', $this->usuarioSesion);
        $this->mensaje = 'Contraseña actualizada correctamente.';

        return true;
    }

    private function invalido(string $campo, string $mensaje): bool
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return false;
    }
}
