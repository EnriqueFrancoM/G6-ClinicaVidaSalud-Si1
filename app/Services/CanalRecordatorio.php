<?php

namespace App\Services;

use App\Core\App;

// Elige EMAIL, WHATSAPP o SISTEMA. Sin proveedor configurado no inventa un envío.
class CanalRecordatorio
{
    public static function whatsappListo(): bool
    {
        return trim((string) App::config('whatsapp.token', '')) !== ''
            && trim((string) App::config('whatsapp.phone_number_id', '')) !== '';
    }

    // Número en formato internacional, sin +. Un teléfono local de hasta 8 dígitos recibe el prefijo.
    public static function telefono(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono) ?? '';
        $prefijo = preg_replace('/\D+/', '', (string) App::config('whatsapp.prefijo', '591')) ?? '';
        if ($prefijo === '') {
            $prefijo = '591';
        }
        if ($digitos !== '' && strlen($digitos) <= 8) {
            $digitos = $prefijo . $digitos;
        }
        if (strlen($digitos) < 8 || strlen($digitos) > 15) {
            return null;
        }

        return $digitos;
    }

    public static function elegir(?string $email, ?string $telefono): string
    {
        $preferencia = (string) App::config('recordatorio.canal', 'auto');
        $correo = self::correoValido($email);
        $whatsapp = self::whatsappListo() && self::telefono($telefono) !== null;

        if ($preferencia === 'email') {
            return $correo ? 'EMAIL' : 'SISTEMA';
        }
        if ($preferencia === 'whatsapp') {
            return $whatsapp ? 'WHATSAPP' : ($correo ? 'EMAIL' : 'SISTEMA');
        }
        if ($whatsapp) {
            return 'WHATSAPP';
        }

        return $correo ? 'EMAIL' : 'SISTEMA';
    }

    // Mantiene el canal guardado si todavía se puede usar. Si no, vuelve a elegir.
    public static function efectivo(string $almacenado, ?string $email, ?string $telefono): array
    {
        $canal = self::viable($almacenado, $email, $telefono)
            ? $almacenado
            : self::elegir($email, $telefono);

        return [
            'canal'        => $canal,
            'destinatario' => self::destinatario($canal, $email, $telefono),
        ];
    }

    private static function viable(string $canal, ?string $email, ?string $telefono): bool
    {
        return match ($canal) {
            'SISTEMA'  => true,
            'EMAIL'    => self::correoValido($email),
            'WHATSAPP' => self::whatsappListo() && self::telefono($telefono) !== null,
            default    => false,
        };
    }

    private static function destinatario(string $canal, ?string $email, ?string $telefono): string
    {
        if ($canal === 'WHATSAPP') {
            return (string) self::telefono($telefono);
        }
        if ($canal === 'EMAIL') {
            return trim((string) $email);
        }

        return '';
    }

    private static function correoValido(?string $email): bool
    {
        return filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) !== false;
    }
}
