<?php

namespace App\Models;

use App\Core\App;
use App\Core\Model;

// Entidad Usuario. CU01: buscarPorUsuario(usuario), verificarClave(clave).
class Usuario extends Model
{
    public function buscarPorUsuario(string $usuario): ?array
    {
        $fila = $this->fetch(
            'SELECT u.*, r.nombre AS rol
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.username = :usuario OR u.email = :usuario',
            ['usuario' => $usuario]
        );
        if ($fila === null) {
            return null;
        }
        $fila['estado'] = self::activo($fila['estado']);

        return $fila;
    }

    // CU01 paso 6 / E1: si coincide reinicia los intentos; si no, los incrementa.
    public function verificarClave(array $usuario, string $clave): bool
    {
        if (!password_verify($clave, $usuario['password_hash'])) {
            $this->registrarIntentoFallido((int) $usuario['id_usuario']);
            return false;
        }

        $this->registrarAccesoExitoso((int) $usuario['id_usuario']);
        return true;
    }

    // CU01 E1: incrementa los intentos fallidos.
    // CU01 E2: si supera el máximo de intentos, la cuenta se desactiva.
    public function registrarIntentoFallido(int $idUsuario): void
    {
        $this->execute(
            'UPDATE usuario
                SET intentos_fallidos = intentos_fallidos + 1,
                    estado = CASE WHEN intentos_fallidos + 1 > :max THEN FALSE ELSE estado END
              WHERE id_usuario = :id',
            ['id' => $idUsuario, 'max' => (int) App::config('security.max_intentos_login', 5)]
        );
    }

    private function registrarAccesoExitoso(int $idUsuario): void
    {
        $this->execute(
            'UPDATE usuario SET intentos_fallidos = 0, ultimo_acceso = CURRENT_TIMESTAMP WHERE id_usuario = :id',
            ['id' => $idUsuario]
        );
    }

    private static function activo(mixed $estado): bool
    {
        return $estado === true || $estado === 't' || $estado === '1' || $estado === 1;
    }
}
