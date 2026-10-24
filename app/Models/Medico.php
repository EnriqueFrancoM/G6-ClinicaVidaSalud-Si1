<?php

namespace App\Models;

use App\Core\Model;

// Entidad Medico. CU03: listar() para el vínculo con un usuario.
// CU08: buscar(filtro), existeRegistro(), insertar(), actualizar(), cambiarEstado().
class Medico extends Model
{
    public function listar(): array
    {
        return $this->fetchAll(
            'SELECT id_medico, id_usuario, codigo, nombres, apellidos
               FROM medico
              WHERE estado = TRUE
              ORDER BY apellidos, nombres'
        );
    }

    // CU08 paso 2: filtros por nombre, especialidad y estado.
    public function buscar(array $filtro = []): array
    {
        $where = [];
        $params = [];
        if (($filtro['nombre'] ?? '') !== '') {
            $where[] = "(m.nombres || ' ' || m.apellidos) ILIKE :nombre";
            $params['nombre'] = '%' . $filtro['nombre'] . '%';
        }
        if (!empty($filtro['id_especialidad'])) {
            $where[] = 'EXISTS (SELECT 1 FROM medico_especialidad x WHERE x.id_medico = m.id_medico AND x.id_especialidad = :id_especialidad)';
            $params['id_especialidad'] = (int) $filtro['id_especialidad'];
        }
        if (isset($filtro['estado']) && $filtro['estado'] !== null) {
            $where[] = 'm.estado = :estado';
            $params['estado'] = $filtro['estado'] ? 'true' : 'false';
        }

        $filas = $this->fetchAll(
            'SELECT m.id_medico, m.id_usuario, m.codigo, m.nro_registro_prof, m.nombres, m.apellidos,
                    m.telefono, m.email, m.estado,
                    STRING_AGG(e.nombre, \', \' ORDER BY e.nombre) AS especialidades,
                    STRING_AGG(e.id_especialidad::text, \',\') AS ids_especialidad
               FROM medico m
          LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
          LEFT JOIN especialidad e ON e.id_especialidad = me.id_especialidad
              ' . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . '
           GROUP BY m.id_medico
           ORDER BY m.codigo',
            $params
        );
        foreach ($filas as &$fila) {
            $fila['estado'] = in_array($fila['estado'], [true, 't', '1', 1], true);
            $fila['ids_especialidad'] = $fila['ids_especialidad'] === null ? [] : array_map('intval', explode(',', $fila['ids_especialidad']));
        }

        return $filas;
    }

    // CU08 E1: ¿el n.º de registro profesional ya está registrado en otro médico?
    public function existeRegistro(string $nroRegistro, ?int $excluirId = null): bool
    {
        $fila = $this->fetch(
            'SELECT 1 FROM medico WHERE UPPER(nro_registro_prof) = UPPER(:nro) AND id_medico <> COALESCE(:excluir, 0)',
            ['nro' => $nroRegistro, 'excluir' => $excluirId]
        );
        return $fila !== null;
    }

    // CU08 paso 4: código automático MED-001, MED-002, ...
    public function siguienteCodigo(): string
    {
        $fila = $this->fetch("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo FROM 5) AS INT)), 0) + 1 AS n FROM medico WHERE codigo ~ '^MED-[0-9]+$'");
        return sprintf('MED-%03d', (int) $fila['n']);
    }

    public function insertar(array $datos): int
    {
        $fila = $this->fetch(
            'INSERT INTO medico (codigo, nro_registro_prof, nombres, apellidos, telefono, email, estado)
             VALUES (:codigo, :nro, :nombres, :apellidos, :telefono, :email, :estado)
             RETURNING id_medico',
            [
                'codigo'    => $this->siguienteCodigo(),
                'nro'       => $datos['nro_registro_prof'],
                'nombres'   => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'telefono'  => $datos['telefono'],
                'email'     => $datos['email'],
                'estado'    => $datos['estado'] ? 'true' : 'false',
            ]
        );
        return (int) $fila['id_medico'];
    }

    public function actualizar(int $idMedico, array $datos): void
    {
        $this->execute(
            'UPDATE medico
                SET nro_registro_prof = :nro, nombres = :nombres, apellidos = :apellidos,
                    telefono = :telefono, email = :email, estado = :estado
              WHERE id_medico = :id',
            [
                'id'        => $idMedico,
                'nro'       => $datos['nro_registro_prof'],
                'nombres'   => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'telefono'  => $datos['telefono'],
                'email'     => $datos['email'],
                'estado'    => $datos['estado'] ? 'true' : 'false',
            ]
        );
    }

    // CU08 E3: el médico no se elimina; se desactiva (RN04) y conserva sus citas históricas.
    public function cambiarEstado(int $idMedico, bool $activo): void
    {
        $this->execute(
            'UPDATE medico SET estado = :estado WHERE id_medico = :id',
            ['id' => $idMedico, 'estado' => $activo ? 'true' : 'false']
        );
    }
}
