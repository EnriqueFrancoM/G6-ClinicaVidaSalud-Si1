<?php

namespace App\Models;

use App\Core\Model;

// Entidad Paciente. CU03: buscar() para el vínculo con un usuario.
// CU10: buscar(criterio), existeCI(), insertar(), actualizar(), desactivar().
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
