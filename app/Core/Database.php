<?php

namespace App\Core;

use PDO;

// Conexión única a PostgreSQL mediante PDO (pdo_pgsql).
class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $db = App::config('db');
            // client_encoding fijo: el texto viaja en UTF-8 sin importar la configuración del servidor.
            $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname={$db['name']};options='--client_encoding=UTF8'";
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Misma zona horaria que PHP, para que fecha_hora y "hoy" coincidan (CU06).
            self::$pdo->exec("SET TIME ZONE '" . App::config('app.timezone', 'America/La_Paz') . "'");
        }
        return self::$pdo;
    }
}
