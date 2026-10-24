<?php

namespace App\Core;

// Manejo de sesión, mensajes flash y token CSRF.
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function user(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    // Marca la última actividad de la sesión (CU02 E1).
    public static function tocar(): void
    {
        $_SESSION['_ultima_actividad'] = time();
    }

    public static function expirada(int $minutos): bool
    {
        $ultima = $_SESSION['_ultima_actividad'] ?? null;
        return $ultima !== null && time() - (int) $ultima > $minutos * 60;
    }

    public static function hasPermission(string $codigo): bool
    {
        return in_array($codigo, $_SESSION['permisos'] ?? [], true);
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
