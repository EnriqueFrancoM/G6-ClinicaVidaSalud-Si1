<?php

namespace App\Models;

use App\Core\Model;

// Entidad Cita. CU09: contarFuturasEnHorario(). CU10: historialPorPaciente().
// CU11: registrarSiDisponible(datos). CU12: buscarYValidarEstado(código). CU13: cancelarConMotivo(código, motivo).
// CU14: validarTransición(código, estado). CU15: buscarAgenda(médico, fecha, estado).
// CU16: buscarPorPaciente(id, período). CU18: reservarAtómicamente(datos). CU19: filtrarCitas(filtros).
// CU20: validarCitaVigente(id). CU21: asignarSiDisponible(datos). CU22: agruparPorEstado(filtros).
class Cita extends Model
{
    public const ESTADOS = [
        'PROGRAMADA'   => 'Programada',
        'CONFIRMADA'   => 'Confirmada',
        'ATENDIDA'     => 'Atendida',
        'CANCELADA'    => 'Cancelada',
        'INASISTENCIA' => 'Inasistencia',
    ];

    // RF14: transiciones válidas. ATENDIDA, CANCELADA e INASISTENCIA son finales.
    // La cancelación tiene su propio caso de uso (CU13).
    public const TRANSICIONES = [
        'PROGRAMADA' => ['CONFIRMADA', 'ATENDIDA', 'INASISTENCIA'],
        'CONFIRMADA' => ['ATENDIDA', 'INASISTENCIA'],
    ];

    // Estados que ocupan el horario del médico (RN09: las canceladas no).
    public const PENDIENTES = ['PROGRAMADA', 'CONFIRMADA'];

    private const SELECT_DETALLE = "SELECT c.id_cita, c.codigo, c.id_paciente, c.id_medico, c.id_especialidad,
                    c.fecha, TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio, TO_CHAR(c.hora_fin, 'HH24:MI') AS hora_fin,
                    c.motivo, c.estado, c.motivo_cancelacion, c.fecha_creacion,
                    p.codigo AS paciente_codigo, p.nombres || ' ' || p.apellidos AS paciente, p.ci AS paciente_ci,
                    p.fecha_nacimiento, p.telefono AS paciente_telefono, p.email AS paciente_email,
                    m.codigo AS medico_codigo, m.nombres || ' ' || m.apellidos AS medico,
                    e.nombre AS especialidad
               FROM cita c
               JOIN paciente p ON p.id_paciente = c.id_paciente
               JOIN medico m ON m.id_medico = c.id_medico
               JOIN especialidad e ON e.id_especialidad = c.id_especialidad";

    // Agenda del día para el panel. El médico ve las suyas; el paciente, las propias.
    public function delDia(int $idUsuario, string $rol): array
    {
        $filtro = '';
        $params = [];
        if ($rol === 'Médico') {
            $filtro = ' AND c.id_medico = (SELECT id_medico FROM medico WHERE id_usuario = :usuario)';
            $params['usuario'] = $idUsuario;
        } elseif ($rol === 'Paciente') {
            $filtro = ' AND c.id_paciente = (SELECT id_paciente FROM paciente WHERE id_usuario = :usuario)';
            $params['usuario'] = $idUsuario;
        }

        return $this->fetchAll(
            "SELECT c.codigo, TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora, c.estado, c.motivo,
                    p.nombres || ' ' || p.apellidos AS paciente,
                    m.nombres || ' ' || m.apellidos AS medico,
                    e.nombre AS especialidad
               FROM cita c
               JOIN paciente p ON p.id_paciente = c.id_paciente
               JOIN medico m ON m.id_medico = c.id_medico
               JOIN especialidad e ON e.id_especialidad = c.id_especialidad
              WHERE c.fecha = CURRENT_DATE{$filtro}
              ORDER BY c.hora_inicio",
            $params
        );
    }

    // CU18: la próxima cita del titular entre las que aún no ocurrieron, no solo las de hoy.
    public function proximaDelPaciente(int $idPaciente): ?array
    {
        return $this->fetch(
            "SELECT c.codigo, c.fecha::text AS fecha, TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora,
                    c.estado, c.motivo,
                    m.nombres || ' ' || m.apellidos AS medico,
                    e.nombre AS especialidad
               FROM cita c
               JOIN medico m ON m.id_medico = c.id_medico
               JOIN especialidad e ON e.id_especialidad = c.id_especialidad
              WHERE c.id_paciente = :id
                AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                AND (c.fecha > CURRENT_DATE
                     OR (c.fecha = CURRENT_DATE AND c.hora_inicio > LOCALTIME))
              ORDER BY c.fecha, c.hora_inicio
              LIMIT 1",
            ['id' => $idPaciente]
        );
    }

    // CU10 paso 9: historial de citas del paciente, de la más reciente a la más antigua.
    public function historialPorPaciente(int $idPaciente): array
    {
        return $this->fetchAll(
            "SELECT c.codigo, c.fecha, TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio, c.estado, c.motivo,
                    c.motivo_cancelacion, m.nombres || ' ' || m.apellidos AS medico, e.nombre AS especialidad
               FROM cita c
               JOIN medico m ON m.id_medico = c.id_medico
               JOIN especialidad e ON e.id_especialidad = c.id_especialidad
              WHERE c.id_paciente = :id
              ORDER BY c.fecha DESC, c.hora_inicio DESC",
            ['id' => $idPaciente]
        );
    }

    // CU09 E3: citas futuras (programadas o confirmadas) dentro de un horario que se modifica o desactiva.
    // Si se indica el nuevo rango, solo cuenta las que quedan fuera de él.
    public function contarFuturasEnHorario(array $horario, ?array $nuevo = null): int
    {
        $sql = "SELECT COUNT(*) AS total FROM cita
                 WHERE id_medico = :medico
                   AND estado IN ('PROGRAMADA', 'CONFIRMADA')
                   AND fecha >= CURRENT_DATE
                   AND EXTRACT(ISODOW FROM fecha) = :dia
                   AND hora_inicio >= CAST(:inicio AS TIME) AND hora_fin <= CAST(:fin AS TIME)";
        $params = [
            'medico' => $horario['id_medico'],
            'dia'    => $horario['dia_semana'],
            'inicio' => $horario['hora_inicio'],
            'fin'    => $horario['hora_fin'],
        ];
        if ($nuevo !== null) {
            $sql .= ' AND NOT (EXTRACT(ISODOW FROM fecha) = :nuevo_dia
                               AND hora_inicio >= CAST(:nuevo_inicio AS TIME) AND hora_fin <= CAST(:nuevo_fin AS TIME))';
            $params += ['nuevo_dia' => $nuevo['dia_semana'], 'nuevo_inicio' => $nuevo['hora_inicio'], 'nuevo_fin' => $nuevo['hora_fin']];
        }

        return (int) $this->fetch($sql, $params)['total'];
    }

    // CU11 mensaje registrarSiDisponible(datos): genera el código CM-0001 y registra la cita.
    // La exclusión ex_cita_sin_solape rechaza un cupo ocupado entretanto (PN06): ver esCupoOcupado().
    public function registrarSiDisponible(array $datos): array
    {
        // Un solo código por vez: dos registros simultáneos no pueden tomar el mismo número.
        $this->execute("SELECT pg_advisory_xact_lock(hashtext('cita_codigo'))");
        $codigo = $this->fetch(
            "SELECT 'CM-' || LPAD((COALESCE(MAX(CAST(SUBSTRING(codigo FROM 4) AS INT)), 0) + 1)::text, 4, '0') AS codigo
               FROM cita WHERE codigo ~ '^CM-[0-9]+$'"
        )['codigo'];

        $fila = $this->fetch(
            'INSERT INTO cita (codigo, id_paciente, id_medico, id_especialidad, id_usuario_registro, fecha, hora_inicio, hora_fin, motivo)
             VALUES (:codigo, :paciente, :medico, :especialidad, :usuario, :fecha, :inicio, :fin, :motivo)
             RETURNING id_cita',
            [
                'codigo'       => $codigo,
                'paciente'     => $datos['id_paciente'],
                'medico'       => $datos['id_medico'],
                'especialidad' => $datos['id_especialidad'],
                'usuario'      => $datos['id_usuario_registro'],
                'fecha'        => $datos['fecha'],
                'inicio'       => $datos['hora_inicio'],
                'fin'          => $datos['hora_fin'],
                'motivo'       => $datos['motivo'] ?? null,
            ]
        );

        return ['id_cita' => (int) $fila['id_cita'], 'codigo' => $codigo];
    }

    // CU18 mensaje reservarAtómicamente(datos): la reserva del portal usa el mismo registro atómico.
    public function reservarAtomicamente(array $datos): array
    {
        return $this->registrarSiDisponible($datos);
    }

    // CU21 mensaje asignarSiDisponible(datos): al aceptar una oferta se vuelve a validar el cupo.
    public function asignarSiDisponible(array $datos): array
    {
        return $this->registrarSiDisponible($datos);
    }

    // ¿La excepción es el rechazo de la exclusión de solapamientos (cupo ya ocupado)?
    public static function esCupoOcupado(\Throwable $e): bool
    {
        return $e instanceof \PDOException && $e->getCode() === '23P01';
    }

    public function buscarPorCodigo(string $codigo): ?array
    {
        $fila = $this->fetch(self::SELECT_DETALLE . ' WHERE UPPER(c.codigo) = UPPER(:codigo)', ['codigo' => trim($codigo)]);
        return $fila;
    }

    public function buscarPorId(int $idCita): ?array
    {
        return $this->fetch(self::SELECT_DETALLE . ' WHERE c.id_cita = :id', ['id' => $idCita]);
    }

    // CU12 mensaje buscarYValidarEstado(código): solo PROGRAMADA o CONFIRMADA se reprograman.
    // Devuelve la cita y si su estado lo permite.
    public function buscarYValidarEstado(string $codigo): array
    {
        $cita = $this->buscarPorCodigo($codigo);
        return ['cita' => $cita, 'valida' => $cita !== null && in_array($cita['estado'], self::PENDIENTES, true)];
    }

    // CU12 paso 4: actualiza el horario solo si la cita sigue programada o confirmada.
    // Si otra operación la canceló o la atendió, no cambia la fila (devuelve false).
    public function actualizarHorario(int $idCita, string $fecha, string $inicio, string $fin): bool
    {
        return $this->execute(
            "UPDATE cita SET fecha = :fecha, hora_inicio = :inicio, hora_fin = :fin
              WHERE id_cita = :id AND estado IN ('PROGRAMADA', 'CONFIRMADA')",
            ['id' => $idCita, 'fecha' => $fecha, 'inicio' => $inicio, 'fin' => $fin]
        ) === 1;
    }

    // CU13 mensaje cancelarConMotivo(código, motivo): la cita no se elimina; libera su cupo (RN09).
    // Devuelve la cita cancelada o null si ya no estaba pendiente.
    public function cancelarConMotivo(string $codigo, string $motivo): ?array
    {
        return $this->fetch(
            "UPDATE cita SET estado = 'CANCELADA', motivo_cancelacion = :motivo
              WHERE UPPER(codigo) = UPPER(:codigo) AND estado IN ('PROGRAMADA', 'CONFIRMADA')
          RETURNING id_cita, codigo, id_medico, id_especialidad, fecha,
                    TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio, TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin",
            ['codigo' => trim($codigo), 'motivo' => $motivo]
        );
    }

    // CU14 mensaje validarTransición(código, estado): comprueba el estado anterior y la transición pedida.
    public function validarTransicion(string $codigo, string $estado): array
    {
        $cita = $this->buscarPorCodigo($codigo);
        $valida = $cita !== null && in_array($estado, self::TRANSICIONES[$cita['estado']] ?? [], true);

        return ['cita' => $cita, 'valida' => $valida];
    }

    // CU14 paso 4: aplica la transición solo si el estado no cambió entretanto.
    public function actualizarEstado(int $idCita, string $anterior, string $nuevo): bool
    {
        return $this->execute(
            'UPDATE cita SET estado = :nuevo WHERE id_cita = :id AND estado = :anterior',
            ['id' => $idCita, 'anterior' => $anterior, 'nuevo' => $nuevo]
        ) === 1;
    }

    // CU15 mensaje buscarAgenda(médico, fecha, estado): citas del día ordenadas por hora.
    public function buscarAgenda(int $idMedico, string $fecha, ?string $estado = null): array
    {
        $params = ['medico' => $idMedico, 'fecha' => $fecha];
        $filtro = '';
        if ($estado !== null && $estado !== '') {
            $filtro = ' AND c.estado = :estado';
            $params['estado'] = $estado;
        }

        return $this->fetchAll(
            self::SELECT_DETALLE . " WHERE c.id_medico = :medico AND c.fecha = CAST(:fecha AS DATE){$filtro}
              ORDER BY c.hora_inicio",
            $params
        );
    }

    // Agenda del día de todos los médicos (recepción). Mismo detalle que buscarAgenda, ordenado por médico y hora.
    public function buscarAgendaDia(string $fecha, ?string $estado = null): array
    {
        $params = ['fecha' => $fecha];
        $filtro = '';
        if ($estado !== null && $estado !== '') {
            $filtro = ' AND c.estado = :estado';
            $params['estado'] = $estado;
        }

        return $this->fetchAll(
            self::SELECT_DETALLE . " WHERE c.fecha = CAST(:fecha AS DATE){$filtro}
              ORDER BY m.apellidos, m.nombres, c.hora_inicio",
            $params
        );
    }

    // CU16 mensaje buscarPorPaciente(id, período): incluye pasadas y canceladas, en orden cronológico.
    public function buscarPorPaciente(int $idPaciente, ?string $desde = null, ?string $hasta = null): array
    {
        $params = ['id' => $idPaciente];
        $filtro = '';
        if ($desde) {
            $filtro .= ' AND c.fecha >= CAST(:desde AS DATE)';
            $params['desde'] = $desde;
        }
        if ($hasta) {
            $filtro .= ' AND c.fecha <= CAST(:hasta AS DATE)';
            $params['hasta'] = $hasta;
        }

        return $this->fetchAll(
            self::SELECT_DETALLE . " WHERE c.id_paciente = :id{$filtro} ORDER BY c.fecha, c.hora_inicio",
            $params
        );
    }

    // CU19 mensaje filtrarCitas(filtros): período, médico, especialidad y estado.
    public function filtrarCitas(array $filtros): array
    {
        [$where, $params] = $this->condiciones($filtros);

        return $this->fetchAll(self::SELECT_DETALLE . " $where ORDER BY c.fecha, c.hora_inicio, m.apellidos", $params);
    }

    // CU20 mensaje validarCitaVigente(id): solo se recuerdan citas pendientes y futuras.
    public function validarCitaVigente(int $idCita): ?array
    {
        return $this->fetch(
            self::SELECT_DETALLE . " WHERE c.id_cita = :id AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                AND c.fecha + c.hora_inicio > LOCALTIMESTAMP",
            ['id' => $idCita]
        );
    }

    // CU22 mensaje agruparPorEstado(filtros): conteo por estado, médico y especialidad.
    public function agruparPorEstado(array $filtros): array
    {
        [$where, $params] = $this->condiciones($filtros);

        return $this->fetchAll(
            "SELECT c.estado, c.id_medico, m.nombres || ' ' || m.apellidos AS medico,
                    c.id_especialidad, e.nombre AS especialidad, COUNT(*) AS total
               FROM cita c
               JOIN paciente p ON p.id_paciente = c.id_paciente
               JOIN medico m ON m.id_medico = c.id_medico
               JOIN especialidad e ON e.id_especialidad = c.id_especialidad
               $where
           GROUP BY c.estado, c.id_medico, m.nombres, m.apellidos, c.id_especialidad, e.nombre",
            $params
        );
    }

    // Filtros comunes de CU19 y CU22: desde, hasta (Y-m-d), id_medico, id_especialidad, estado.
    private function condiciones(array $filtros): array
    {
        $where = [];
        $params = [];
        if (!empty($filtros['desde'])) {
            $where[] = 'c.fecha >= CAST(:desde AS DATE)';
            $params['desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'c.fecha <= CAST(:hasta AS DATE)';
            $params['hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['id_medico'])) {
            $where[] = 'c.id_medico = :medico';
            $params['medico'] = (int) $filtros['id_medico'];
        }
        if (!empty($filtros['id_especialidad'])) {
            $where[] = 'c.id_especialidad = :especialidad';
            $params['especialidad'] = (int) $filtros['id_especialidad'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'c.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }
}
