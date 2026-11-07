<?php

namespace App\Core;

// Dirección impredecible. El mismo dato sale distinto cada vez y no se lee el contenido sin la clave.
class EnlaceOpaco
{
    public static function sellar(string $uso, array $partes): string
    {
        if (!function_exists('openssl_encrypt')) {
            return '';
        }
        $plano = $uso . "\n" . implode("\n", array_map(static fn ($parte) => str_replace(["\n", "\r"], '', (string) $parte), $partes));
        $iv = random_bytes(12);
        $etiqueta = '';
        $cifra = openssl_encrypt($plano, 'aes-256-gcm', self::secreto(), OPENSSL_RAW_DATA, $iv, $etiqueta);
        if ($cifra === false || strlen($etiqueta) !== 16) {
            return '';
        }

        return self::codificar($iv . $etiqueta . $cifra);
    }

    public static function abrir(string $token): ?array
    {
        if ($token === '' || !function_exists('openssl_decrypt')) {
            return null;
        }
        $crudo = self::decodificar($token);
        if ($crudo === null || strlen($crudo) < 29) {
            return null;
        }
        $iv = substr($crudo, 0, 12);
        $etiqueta = substr($crudo, 12, 16);
        $cifra = substr($crudo, 28);
        $plano = openssl_decrypt($cifra, 'aes-256-gcm', self::secreto(), OPENSSL_RAW_DATA, $iv, $etiqueta);
        if ($plano === false) {
            return null;
        }
        $partes = explode("\n", $plano);
        $uso = array_shift($partes);
        if ($uso === null || $uso === '' || $partes === []) {
            return null;
        }

        return ['uso' => $uso, 'datos' => $partes];
    }

    private static function secreto(): string
    {
        $clave = (string) App::config('app.key', '');
        if ($clave === '') {
            $clave = hash('sha256', json_encode(App::config('db')));
        }

        return hash('sha256', 'enlace-opaco|' . $clave, true);
    }

    private static function codificar(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }

    private static function decodificar(string $texto): ?string
    {
        $texto = strtr($texto, '-_', '+/');
        $resto = strlen($texto) % 4;
        if ($resto !== 0) {
            $texto .= str_repeat('=', 4 - $resto);
        }
        $binario = base64_decode($texto, true);

        return $binario === false ? null : $binario;
    }
}
