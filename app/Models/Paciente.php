<?php

namespace App\Models;

use App\Core\Model;

// Entidad Paciente. CU03: buscar() para el vínculo con un usuario.
// CU10: buscar(criterio), existeCI(), insertar(), actualizar(), desactivar().
// CU11: validarPaciente(id). CU16: buscarPaciente(criterio). CU17: guardarDatosPropios(datos).
// CU18: validarTitular(sesión). CU19: contarPacientes(período).
class Paciente extends Model
{
    public const TIPOS_SANGRE = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    // CU10 paso 3: por CI, nombre o apellido y estado. Sin criterio devuelve los activos.
    public function buscar(array $criterio = []): array
    {
        $where = [];
        $params = [];
        if (($criterio['ci'] ?? '') !== '') {
            $where[] = 'p.ci ILIKE :ci';
            $params['ci'] = $criterio['ci'] . '%';
        }
        if (($criterio['nombre'] ?? '') !== '') {
            $where[] = "(p.nombres || ' ' || p.apellidos) ILIKE :nombre";
            $params['nombre'] = '%' . $criterio['nombre'] . '%';
        }
        if (!empty($criterio['id_paciente'])) {
            $where[] = 'p.id_paciente = :id';
            $params['id'] = (int) $criterio['id_paciente'];
        }
        $estado = array_key_exists('estado', $criterio) ? $criterio['estado'] : true;
        if ($estado !== null) {
            $where[] = 'p.estado = :estado';
            $params['estado'] = $estado ? 'true' : 'false';
        }

        $filas = $this->fetchAll(
            'SELECT p.*, c.nombre AS contacto_nombre, c.parentesco AS contacto_parentesco, c.telefono AS contacto_telefono
               FROM paciente p
          LEFT JOIN LATERAL (SELECT nombre, parentesco, telefono FROM contacto_emergencia
                              WHERE id_paciente = p.id_paciente ORDER BY id_contacto LIMIT 1) c ON TRUE
              ' . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . '
           ORDER BY p.apellidos, p.nombres',
            $params
        );
        foreach ($filas as &$fila) {
            $fila['estado'] = in_array($fila['estado'], [true, 't', '1', 1], true);
        }

        return $filas;
    }

    // CU10 paso 7 / E1: devuelve el paciente que ya tiene ese CI, o null.
    public function existeCI(string $ci, ?int $excluirId = null): ?array
    {
        return $this->fetch(
            'SELECT id_paciente, codigo FROM paciente WHERE UPPER(ci) = UPPER(:ci) AND id_paciente <> COALESCE(:excluir, 0)',
            ['ci' => $ci, 'excluir' => $excluirId]
        );
    }

    // CU10 paso 8: genera el código PAC-00001, PAC-00002, ...
    public function insertar(array $datos): array
    {
        $codigo = $this->fetch(
            "SELECT 'PAC-' || LPAD((COALESCE(MAX(CAST(SUBSTRING(codigo FROM 5) AS INT)), 0) + 1)::text, 5, '0') AS codigo
               FROM paciente WHERE codigo ~ '^PAC-[0-9]+$'"
        )['codigo'];

        $fila = $this->fetch(
            'INSERT INTO paciente (codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, email, direccion,
                                   tipo_sangre, alergias, enfermedades_cronicas)
             VALUES (:codigo, :ci, :nombres, :apellidos, :fecha_nacimiento, :sexo, :telefono, :email, :direccion,
                     :tipo_sangre, :alergias, :enfermedades_cronicas)
             RETURNING id_paciente',
            ['codigo' => $codigo] + $this->columnas($datos)
        );

        return ['id_paciente' => (int) $fila['id_paciente'], 'codigo' => $codigo];
    }

    public function actualizar(int $idPaciente, array $datos): void
    {
        $this->execute(
            'UPDATE paciente
                SET ci = :ci, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento,
                    sexo = :sexo, telefono = :telefono, email = :email, direccion = :direccion, tipo_sangre = :tipo_sangre,
                    alergias = :alergias, enfermedades_cronicas = :enfermedades_cronicas, estado = :estado
              WHERE id_paciente = :id',
            ['id' => $idPaciente, 'estado' => $datos['estado'] ? 'true' : 'false'] + $this->columnas($datos)
        );
    }

    // CU11 mensaje validarPaciente(id): el paciente existe y está activo. Devuelve sus datos o null.
    public function validarPaciente(int $idPaciente): ?array
    {
        $fila = $this->fetch('SELECT * FROM paciente WHERE id_paciente = :id AND estado = TRUE', ['id' => $idPaciente]);
        if ($fila !== null) {
            $fila['edad'] = self::edad($fila['fecha_nacimiento']);
        }

        return $fila;
    }

    // CU16 mensaje buscarPaciente(criterio): por CI o código (PAC-00001), activo o no.
    public function buscarPaciente(string $criterio): ?array
    {
        $criterio = trim($criterio);
        if ($criterio === '') {
            return null;
        }

        return $this->fetch(
            'SELECT * FROM paciente WHERE UPPER(ci) = UPPER(:c) OR UPPER(codigo) = UPPER(:c) ORDER BY estado DESC LIMIT 1',
            ['c' => $criterio]
        );
    }

    // CU18 mensaje validarTitular(sesión): el paciente vinculado al usuario de la sesión, si está activo.
    public function validarTitular(array $sesion): ?array
    {
        $fila = $this->fetch(
            'SELECT * FROM paciente WHERE id_usuario = :usuario AND estado = TRUE',
            ['usuario' => (int) ($sesion['id_usuario'] ?? 0)]
        );
        if ($fila !== null) {
            $fila['edad'] = self::edad($fila['fecha_nacimiento']);
        }

        return $fila;
    }

    // CU17 mensaje guardarDatosPropios(datos): el titular actualiza sus datos personales y médicos.
    // Si aún no tiene ficha (registro en el portal), se crea vinculada a su usuario.
    public function guardarDatosPropios(int $idUsuario, ?int $idPaciente, array $datos): int
    {
        if ($idPaciente === null) {
            $nuevo = $this->insertar($datos);
            $this->execute('UPDATE paciente SET id_usuario = :usuario WHERE id_paciente = :id', ['usuario' => $idUsuario, 'id' => $nuevo['id_paciente']]);
            return $nuevo['id_paciente'];
        }

        $this->execute(
            'UPDATE paciente
                SET ci = :ci, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento,
                    sexo = :sexo, telefono = :telefono, email = :email, direccion = :direccion, tipo_sangre = :tipo_sangre,
                    alergias = :alergias, enfermedades_cronicas = :enfermedades_cronicas
              WHERE id_paciente = :id AND id_usuario = :usuario',
            ['id' => $idPaciente, 'usuario' => $idUsuario] + $this->columnas($datos)
        );

        return $idPaciente;
    }

    // CU19 mensaje contarPacientes(período): pacientes registrados en el período (y el total activo).
    public function contarPacientes(?string $desde, ?string $hasta): array
    {
        return $this->fetch(
            'SELECT COUNT(*) FILTER (WHERE (CAST(:desde AS DATE) IS NULL OR fecha_registro >= CAST(:desde AS DATE))
                                       AND (CAST(:hasta AS DATE) IS NULL OR fecha_registro < CAST(:hasta AS DATE) + 1)) AS registrados,
                    COUNT(*) FILTER (WHERE estado) AS activos,
                    COUNT(*) AS total
               FROM paciente',
            ['desde' => $desde ?: null, 'hasta' => $hasta ?: null]
        );
    }

    // CU19: listado de pacientes registrados en el período.
    public function registradosEn(?string $desde, ?string $hasta): array
    {
        return $this->fetchAll(
            "SELECT codigo, ci, nombres || ' ' || apellidos AS paciente, fecha_nacimiento, telefono, email, estado,
                    TO_CHAR(fecha_registro, 'YYYY-MM-DD') AS fecha_registro
               FROM paciente
              WHERE (CAST(:desde AS DATE) IS NULL OR fecha_registro >= CAST(:desde AS DATE))
                AND (CAST(:hasta AS DATE) IS NULL OR fecha_registro < CAST(:hasta AS DATE) + 1)
              ORDER BY fecha_registro, codigo",
            ['desde' => $desde ?: null, 'hasta' => $hasta ?: null]
        );
    }

    public static function edad(string $fechaNacimiento): int
    {
        return (new \DateTime($fechaNacimiento))->diff(new \DateTime('today'))->y;
    }

    // CU10 E3: el paciente no se elimina; se desactiva y conserva su historial (RN11).
    public function desactivar(int $idPaciente): void
    {
        $this->execute('UPDATE paciente SET estado = FALSE WHERE id_paciente = :id', ['id' => $idPaciente]);
    }

    private function columnas(array $datos): array
    {
        $campos = ['ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'email', 'direccion',
            'tipo_sangre', 'alergias', 'enfermedades_cronicas'];
        return array_combine($campos, array_map(fn ($c) => $datos[$c], $campos));
    }
}
