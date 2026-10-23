<?php

namespace App\Models;

use App\Core\Model;

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

    public function verificarClave(array $usuario, string $clave): bool
    {
        if (!password_verify($clave, $usuario['password_hash'])) {
            if ($usuario['estado']) {
                $this->registrarIntentoFallido((int) $usuario['id_usuario'], (int) \App\Core\App::config('security.max_intentos_login', 5));
            }
            return false;
        }

        $this->registrarAccesoExitoso((int) $usuario['id_usuario']);
        return true;
    }

    public function segundosDeEspera(array $usuario): int
    {
        $penalizaciones = \App\Core\App::config('security.penalizaciones', []);
        $espera = (int) ($penalizaciones[(int) $usuario['intentos_fallidos']] ?? 0);
        if ($espera === 0) {
            return 0;
        }
        $transcurrido = (new Bitacora())->segundosDesde('LOGIN_FALLIDO', (int) $usuario['id_usuario']);
        if ($transcurrido === null) {
            return 0;
        }

        return max(0, $espera - $transcurrido);
    }

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

    public function validarPolitica(string $nueva): ?string
    {
        $cumple = strlen($nueva) >= 8
            && preg_match('/[A-Z]/', $nueva) === 1
            && preg_match('/[a-z]/', $nueva) === 1
            && preg_match('/[0-9]/', $nueva) === 1
            && preg_match('/[^A-Za-z0-9]/', $nueva) === 1;

        return $cumple
            ? null
            : 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial.';
    }

    public function actualizarClave(int $idUsuario, string $hash): void
    {
        $this->execute(
            'UPDATE usuario
                SET password_hash = :hash, intentos_fallidos = 0, primer_ingreso = FALSE
              WHERE id_usuario = :id',
            ['hash' => $hash, 'id' => $idUsuario]
        );
    }

    public function listar(): array
    {
        $filas = $this->fetchAll(
            'SELECT u.id_usuario, u.username, u.email, u.estado, u.intentos_fallidos, r.nombre AS rol
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              ORDER BY u.estado, u.username'
        );
        foreach ($filas as &$fila) {
            $fila['estado'] = self::activo($fila['estado']);
        }

        return $filas;
    }

    public function buscarPorId(int $idUsuario): ?array
    {
        $fila = $this->fetch(
            'SELECT u.*, r.nombre AS rol
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = :id',
            ['id' => $idUsuario]
        );
        if ($fila === null) {
            return null;
        }
        $fila['estado'] = self::activo($fila['estado']);

        return $fila;
    }

    public function cambiarEstado(int $idUsuario, bool $activo): void
    {
        if ($activo) {
            $this->execute(
                'UPDATE usuario SET estado = TRUE, intentos_fallidos = 0 WHERE id_usuario = :id',
                ['id' => $idUsuario]
            );
            return;
        }

        $this->execute(
            'UPDATE usuario SET estado = FALSE WHERE id_usuario = :id',
            ['id' => $idUsuario]
        );
    }

    private static function activo(mixed $estado): bool
    {
        return $estado === true || $estado === 't' || $estado === '1' || $estado === 1;
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
