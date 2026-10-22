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

    public static function url(string $path = ''): string
    {
        return self::config('app.base_url', '') . '/' . ltrim($path, '/');
    }
}
