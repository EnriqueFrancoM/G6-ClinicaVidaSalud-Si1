<?php

namespace App\Controllers\Seguridad;

use App\Core\App;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Usuario;
use App\Services\CorreoBrevo;

// Recuperación con código de un solo uso. No reemplaza el desbloqueo del administrador.
class CtrlRecuperacion
{
    public string $mensaje = '';

    public function solicitarCodigo(string $usuario): bool
    {
        $correo = new CorreoBrevo();
        if (!$correo->configurado()) {
            $this->mensaje = 'Falta configurar Brevo. En el archivo .env complete BREVO_API_KEY y BREVO_SENDER_EMAIL.';
            return false;
        }

        $fila = (new Usuario())->buscarPorUsuario($usuario);
        if ($fila === null || trim((string) $fila['email']) === '') {
            $this->mensaje = 'No encontramos una cuenta con ese usuario o correo.';
            return false;
        }
        if (!$fila['estado']) {
            $this->mensaje = 'Su cuenta está bloqueada. Contacte al administrador.';
            return false;
        }

        $pendiente = Session::get('recuperacion');
        if (is_array($pendiente) && (int) ($pendiente['id_usuario'] ?? 0) === (int) $fila['id_usuario'] && time() - (int) ($pendiente['enviado'] ?? 0) < 60) {
            $this->mensaje = 'Ya enviamos un código. Espere un minuto para pedir otro.';
            return false;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutos = (int) App::config('security.codigo_minutos', 10);
        try {
            $correo->enviarCodigo($fila['email'], $codigo);
        } catch (\Throwable) {
            $this->mensaje = 'No se pudo enviar el correo. Revise la clave y el remitente de Brevo.';
            return false;
        }

        Session::set('recuperacion', [
            'id_usuario' => (int) $fila['id_usuario'],
            'correo'     => $this->enmascarar($fila['email']),
            'hash'       => password_hash($codigo, PASSWORD_DEFAULT),
            'expira'     => time() + ($minutos * 60),
            'intentos'   => 0,
            'enviado'    => time(),
            'verificado' => false,
        ]);
        (new Bitacora())->registrar((int) $fila['id_usuario'], 'RECUPERACION', 'Seguridad', 'Código enviado');
        $this->mensaje = 'Enviamos un código de un solo uso a su correo. Vence en ' . $minutos . ' minutos.';

        return true;
    }

    public function verificarCodigo(string $codigo): bool
    {
        $pendiente = $this->pendiente();
        if ($pendiente === null || ($pendiente['verificado'] ?? false) === true) {
            $this->mensaje = 'El código ya no es válido. Solicite uno nuevo.';
            return false;
        }
        if ((int) $pendiente['intentos'] >= 5) {
            Session::set('recuperacion', null);
            $this->mensaje = 'El código ya no es válido. Solicite uno nuevo.';
            return false;
        }
        if (!password_verify($codigo, (string) $pendiente['hash'])) {
            $pendiente['intentos']++;
            Session::set('recuperacion', $pendiente);
            $this->mensaje = 'El código no coincide.';
            return false;
        }

        $pendiente['verificado'] = true;
        $pendiente['hash'] = '';
        Session::set('recuperacion', $pendiente);

        return true;
    }

    public function definirClave(string $nueva, string $confirmacion): bool
    {
        $pendiente = $this->pendiente();
        if ($pendiente === null || ($pendiente['verificado'] ?? false) !== true) {
            $this->mensaje = 'El código ya no es válido. Solicite uno nuevo.';
            return false;
        }

        $usuarios = new Usuario();
        $politica = $usuarios->validarPolitica($nueva);
        if ($politica !== null) {
            $this->mensaje = $politica;
            return false;
        }
        if ($nueva !== $confirmacion) {
            $this->mensaje = 'La confirmación no coincide con la contraseña.';
            return false;
        }

        $fila = $usuarios->buscarPorId((int) $pendiente['id_usuario']);
        if ($fila === null || !$fila['estado']) {
            Session::set('recuperacion', null);
            $this->mensaje = 'Su cuenta está bloqueada. Contacte al administrador.';
            return false;
        }
        if (password_verify($nueva, $fila['password_hash'])) {
            $this->mensaje = 'Elija una contraseña distinta a la actual.';
            return false;
        }

        $usuarios->actualizarClave((int) $fila['id_usuario'], password_hash($nueva, PASSWORD_DEFAULT));
        (new Bitacora())->registrar((int) $fila['id_usuario'], 'CAMBIO_CLAVE', 'Seguridad', 'Recuperación por correo');
        Session::set('recuperacion', null);
        $this->mensaje = 'Contraseña actualizada. Ya puede iniciar sesión.';

        return true;
    }

    public function correoEnmascarado(): string
    {
        $pendiente = Session::get('recuperacion');

        return is_array($pendiente) ? (string) ($pendiente['correo'] ?? '') : '';
    }

    public function tieneCodigoPendiente(): bool
    {
        $pendiente = $this->pendiente();

        return $pendiente !== null && ($pendiente['verificado'] ?? false) !== true && ($pendiente['hash'] ?? '') !== '';
    }

    public function claveHabilitada(): bool
    {
        $pendiente = $this->pendiente();

        return $pendiente !== null && ($pendiente['verificado'] ?? false) === true;
    }

    private function pendiente(): ?array
    {
        $pendiente = Session::get('recuperacion');
        if (!is_array($pendiente) || (int) ($pendiente['expira'] ?? 0) < time()) {
            return null;
        }

        return $pendiente;
    }

    private function enmascarar(string $email): string
    {
        [$nombre, $dominio] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($nombre, 0, 1);

        return $visible . '***@' . $dominio;
    }
}
