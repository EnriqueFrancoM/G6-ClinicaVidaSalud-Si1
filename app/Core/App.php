<?php

namespace App\Core;

// Contenedor mínimo de la configuración cargada.
class App
{
    private static array $config = [];

    public static function boot(array $config): void
    {
        self::$config = $config;
    }

    // createFromFormat devuelve false si el texto no es una fecha, y el operador ?-> no cubre false.
    public static function fechaIso(string $fecha): bool
    {
        $parseada = \DateTime::createFromFormat('!Y-m-d', $fecha);

        return $parseada instanceof \DateTime && $parseada->format('Y-m-d') === $fecha;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    // URL absoluta. Si el navegador entró por otro host (túnel, proxy), se usa ese host
    // para que CSS y enlaces no queden apuntando a localhost. No se fija ningún dominio temporal.
    public static function url(string $path = ''): string
    {
        $base = rtrim((string) self::config('app.base_url', ''), '/');
        $relativo = '/' . ltrim($path, '/');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return $base . $relativo;
        }

        $partes = $base !== '' ? parse_url($base) : [];
        $autoridad = ($partes['host'] ?? '') . (isset($partes['port']) ? ':' . $partes['port'] : '');
        if ($autoridad !== '' && strcasecmp($host, $autoridad) === 0) {
            return $base . $relativo;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $prefijo = rtrim((string) ($partes['path'] ?? ''), '/');

        return ($https ? 'https' : 'http') . '://' . $host . $prefijo . $relativo;
    }
}
