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

    // CU06 mensaje 5: nombre de usuario para mostrar en la bitácora.
    public function obtenerNombre(int $idUsuario): ?string
    {
        $fila = $this->fetch('SELECT username FROM usuario WHERE id_usuario = :id', ['id' => $idUsuario]);
        return $fila['username'] ?? null;
    }

    // CU05 paso 5: mínimo 8 caracteres, una mayúscula, un número y un símbolo.
    public function validarPolitica(string $nueva): bool
    {
        return mb_strlen($nueva) >= 8
            && preg_match('/[A-Z]/', $nueva) === 1
            && preg_match('/[0-9]/', $nueva) === 1
            && preg_match('/[^A-Za-z0-9]/', $nueva) === 1;
    }

    // CU05 paso 6: guarda la contraseña cifrada y desactiva el indicador de primer ingreso.
    public function actualizarClave(int $idUsuario, string $passwordHash): void
    {
        $this->execute(
            'UPDATE usuario SET password_hash = :hash, primer_ingreso = FALSE WHERE id_usuario = :id',
            ['hash' => $passwordHash, 'id' => $idUsuario]
        );
    }

    // CU03 paso 2: lista con rol, estado y vínculo, filtrada por nombre, rol y estado.
    public function listar(string $nombre = '', ?int $idRol = null, ?bool $estado = null): array
    {
        $sql = 'SELECT u.id_usuario, u.id_rol, u.username, u.email, u.estado, r.nombre AS rol,
                       m.id_medico, p.id_paciente
                  FROM usuario u
                  JOIN rol r ON r.id_rol = u.id_rol
             LEFT JOIN medico m ON m.id_usuario = u.id_usuario
             LEFT JOIN paciente p ON p.id_usuario = u.id_usuario
                 WHERE 1 = 1';
        $params = [];
        if ($nombre !== '') {
            $sql .= ' AND (u.username ILIKE :nombre OR u.email ILIKE :nombre)';
            $params['nombre'] = '%' . $nombre . '%';
        }
        if ($idRol !== null) {
            $sql .= ' AND u.id_rol = :id_rol';
            $params['id_rol'] = $idRol;
        }
        if ($estado !== null) {
            $sql .= ' AND u.estado = :estado';
            $params['estado'] = $estado ? 'true' : 'false';
        }
        $sql .= ' ORDER BY u.username';

        $filas = $this->fetchAll($sql, $params);
        foreach ($filas as &$fila) {
            $fila['estado'] = self::activo($fila['estado']);
        }

        return $filas;
    }

    // CU03 E1: devuelve el campo duplicado ('username' o 'email') o null si ambos están libres.
    public function existeUsuarioOEmail(string $username, string $email, ?int $excluirId = null): ?string
    {
        $fila = $this->fetch(
            'SELECT
                 BOOL_OR(LOWER(username) = LOWER(:username)) AS username,
                 BOOL_OR(LOWER(email) = LOWER(:email))       AS email
               FROM usuario
              WHERE id_usuario <> COALESCE(:excluir, 0)',
            ['username' => $username, 'email' => $email, 'excluir' => $excluirId]
        );
        if (self::activo($fila['username'] ?? false)) {
            return 'username';
        }
        if (self::activo($fila['email'] ?? false)) {
            return 'email';
        }

        return null;
    }

    // CU03 paso 7: guarda la contraseña temporal ya cifrada y marca el primer ingreso.
    public function insertar(array $datos, string $passwordHash): int
    {
        $this->db->beginTransaction();
        $fila = $this->fetch(
            'INSERT INTO usuario (id_rol, username, email, password_hash, estado, primer_ingreso)
             VALUES (:id_rol, :username, :email, :hash, :estado, TRUE)
             RETURNING id_usuario',
            [
                'id_rol'   => $datos['id_rol'],
                'username' => $datos['username'],
                'email'    => $datos['email'],
                'hash'     => $passwordHash,
                'estado'   => $datos['estado'] ? 'true' : 'false',
            ]
        );
        $idUsuario = (int) $fila['id_usuario'];
        $this->vincular($idUsuario, $datos['vinculo']);
        $this->db->commit();

        return $idUsuario;
    }

    public function actualizar(int $idUsuario, array $datos): void
    {
        $this->db->beginTransaction();
        $this->execute(
            'UPDATE usuario
                SET id_rol = :id_rol, username = :username, email = :email, estado = :estado
              WHERE id_usuario = :id',
            [
                'id'       => $idUsuario,
                'id_rol'   => $datos['id_rol'],
                'username' => $datos['username'],
                'email'    => $datos['email'],
                'estado'   => $datos['estado'] ? 'true' : 'false',
            ]
        );
        $this->vincular($idUsuario, $datos['vinculo']);
        $this->db->commit();
    }

    // CU03 E3: los usuarios no se eliminan, se activan o desactivan.
    public function cambiarEstado(int $idUsuario, bool $activo): void
    {
        $this->execute(
            $activo
                ? 'UPDATE usuario SET estado = TRUE, intentos_fallidos = 0 WHERE id_usuario = :id'
                : 'UPDATE usuario SET estado = FALSE WHERE id_usuario = :id',
            ['id' => $idUsuario]
        );
    }

    public function buscarPorId(int $idUsuario): ?array
    {
        $fila = $this->fetch(
            'SELECT u.id_usuario, u.id_rol, u.username, u.email, u.estado, m.id_medico, p.id_paciente
               FROM usuario u
          LEFT JOIN medico m ON m.id_usuario = u.id_usuario
          LEFT JOIN paciente p ON p.id_usuario = u.id_usuario
              WHERE u.id_usuario = :id',
            ['id' => $idUsuario]
        );
        if ($fila === null) {
            return null;
        }
        $fila['estado'] = self::activo($fila['estado']);

        return $fila;
    }

    // Vínculo opcional con un médico o paciente: ['tipo' => 'medico'|'paciente'|null, 'id' => int].
    private function vincular(int $idUsuario, array $vinculo): void
    {
        $this->execute('UPDATE medico SET id_usuario = NULL WHERE id_usuario = :id', ['id' => $idUsuario]);
        $this->execute('UPDATE paciente SET id_usuario = NULL WHERE id_usuario = :id', ['id' => $idUsuario]);

        if ($vinculo['tipo'] === 'medico') {
            $this->execute('UPDATE medico SET id_usuario = :usuario WHERE id_medico = :id', ['usuario' => $idUsuario, 'id' => $vinculo['id']]);
        } elseif ($vinculo['tipo'] === 'paciente') {
            $this->execute('UPDATE paciente SET id_usuario = :usuario WHERE id_paciente = :id', ['usuario' => $idUsuario, 'id' => $vinculo['id']]);
        }
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
