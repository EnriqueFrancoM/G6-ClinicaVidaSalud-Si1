<?php

namespace App\Models;

use App\Core\Model;

class Usuario extends Model
{
    public function findByLogin(string $login): ?array
    {
        return $this->fetch(
            'SELECT u.*, r.nombre AS rol
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.username = :login OR u.email = :login',
            ['login' => $login]
        );
    }

    public function permisos(int $idRol): array
    {
        $rows = $this->fetchAll(
            'SELECT p.codigo
               FROM rol_permiso rp
               JOIN permiso p ON p.id_permiso = rp.id_permiso
              WHERE rp.id_rol = :id_rol',
            ['id_rol' => $idRol]
        );
        return array_column($rows, 'codigo');
    }

    public function registrarAccesoExitoso(int $idUsuario): void
    {
        $this->execute(
            'UPDATE usuario SET intentos_fallidos = 0, ultimo_acceso = CURRENT_TIMESTAMP WHERE id_usuario = :id',
            ['id' => $idUsuario]
        );
    }

    // Suma un intento fallido y desactiva la cuenta al llegar al máximo.
    public function registrarIntentoFallido(int $idUsuario, int $maxIntentos): void
    {
        $this->execute(
            'UPDATE usuario
                SET intentos_fallidos = intentos_fallidos + 1,
                    estado = CASE WHEN intentos_fallidos + 1 >= :max THEN FALSE ELSE estado END
              WHERE id_usuario = :id',
            ['id' => $idUsuario, 'max' => $maxIntentos]
        );
    }
}
