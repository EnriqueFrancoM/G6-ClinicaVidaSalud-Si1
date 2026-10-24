<?php

namespace App\Controllers\GestionClinica;

use App\Core\Session;
use App\Models\Bitacora;
use App\Models\BloqueoAgenda;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Medico;

// CU09 control. Mensajes: obtenerHorarios(id_medico), guardarHorario(datos).
// «extend» Registrar bloqueo de agenda: registrarBloqueo(datos), quitarBloqueo(id).
class CtrlHorario
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    public const DURACIONES = [15, 20, 30, 45, 60];

    public ?array $usuarioSesion;

    public string $mensaje = '';

    public ?string $campoError = null;

    // E3: cantidad de citas futuras afectadas que requieren confirmación.
    public int $citasAfectadas = 0;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU09 mensaje 2 → 3: listarPorMedico().
    public function obtenerHorarios(int $idMedico): array
    {
        return [
            'medicos'   => (new Medico())->buscar(),
            'horarios'  => $idMedico > 0 ? (new HorarioAtencion())->listarPorMedico($idMedico) : [],
            'bloqueos'  => $idMedico > 0 ? (new BloqueoAgenda())->listarPorMedico($idMedico) : [],
        ];
    }

    // «extend» Registrar bloqueo de agenda: feriado general (todos los médicos) o ausencia de un médico.
    public function registrarBloqueo(array $datos): bool
    {
        $idMedico = ($datos['alcance'] ?? 'medico') === 'general' ? null : (int) ($datos['id_medico'] ?? 0);
        $fecha = (string) ($datos['fecha'] ?? '');
        $motivo = preg_replace('/\s+/u', ' ', trim((string) ($datos['motivo'] ?? '')));

        if (\DateTime::createFromFormat('!Y-m-d', $fecha)?->format('Y-m-d') !== $fecha) {
            $this->invalido('fecha', 'Ingrese una fecha válida.');
            return false;
        }
        if ($fecha < date('Y-m-d')) {
            $this->invalido('fecha', 'No se pueden registrar bloqueos en fechas pasadas.');
            return false;
        }
        if ($motivo === '' || mb_strlen($motivo) > 120) {
            $this->invalido('motivo', 'Ingrese el motivo (máximo 120 caracteres).');
            return false;
        }
        if ($idMedico !== null && $idMedico <= 0) {
            $this->mensaje = 'Seleccione un médico.';
            return false;
        }

        $bloqueos = new BloqueoAgenda();
        if ($bloqueos->existeBloqueo($idMedico, $fecha)) {
            $this->invalido('fecha', $idMedico === null ? 'Ya existe un bloqueo registrado para esa fecha.' : 'Esa fecha ya está bloqueada para el médico.');
            return false;
        }

        $bloqueos->insertar($idMedico, $fecha, $motivo);
        $quien = $idMedico === null ? 'general' : $this->codigoMedico($idMedico);
        $this->registrar("Registró bloqueo $quien $fecha: $motivo");
        $this->mensaje = 'Bloqueo de agenda registrado. Las citas ya programadas ese día no se eliminan.';

        return true;
    }

    public function quitarBloqueo(int $idBloqueo): bool
    {
        $eliminado = (new BloqueoAgenda())->eliminar($idBloqueo);
        if ($eliminado === null) {
            $this->mensaje = 'El bloqueo no existe.';
            return false;
        }

        $quien = $eliminado['id_medico'] === null ? 'general' : $this->codigoMedico((int) $eliminado['id_medico']);
        $this->registrar("Quitó bloqueo $quien {$eliminado['fecha']}");
        $this->mensaje = 'Bloqueo de agenda quitado.';

        return true;
    }

    private function codigoMedico(int $idMedico): string
    {
        foreach ((new Medico())->buscar() as $fila) {
            if ((int) $fila['id_medico'] === $idMedico) {
                return $fila['codigo'];
            }
        }
        return '';
    }

    // CU09 mensaje 5 → 6, 7, 8, 9, 10.
    public function guardarHorario(array $datos): bool
    {
        $horarios = new HorarioAtencion();
        $idMedico = (int) ($datos['id_medico'] ?? 0);
        $idHorario = (int) ($datos['id_horario'] ?? 0);
        $confirmado = ($datos['confirmado'] ?? '0') === '1';

        $actual = null;
        foreach ($horarios->listarPorMedico($idMedico) as $fila) {
            if ((int) $fila['id_horario'] === $idHorario) {
                $actual = $fila;
            }
        }
        if ($idHorario > 0 && $actual === null) {
            $this->mensaje = 'El horario no existe.';
            return false;
        }

        // Desactivar un horario (E3: advierte citas futuras y pide confirmación).
        if (($datos['accion'] ?? '') === 'desactivar') {
            if (!$this->confirmarCitasAfectadas($actual, null, $confirmado)) {
                return false;
            }
            $horarios->desactivar($idHorario);
            $this->registrar('Desactivó ' . $this->describir($actual));
            $this->mensaje = 'Horario desactivado. Las citas existentes no se eliminaron.';
            return true;
        }

        // Mensaje 6: verificarActivo(). E4: médico inactivo, no se permiten nuevos horarios.
        if ($actual === null && !(new Medico())->verificarActivo($idMedico)) {
            $this->mensaje = 'El médico está inactivo; no se permiten nuevos horarios.';
            return false;
        }

        $limpio = $this->validar($datos, $idMedico);
        if ($limpio === null) {
            return false;
        }

        // Mensaje 7: existeSolapamiento(). E2: se cruza con otro horario del mismo día.
        if ($horarios->existeSolapamiento($idMedico, $limpio['dia_semana'], $limpio['hora_inicio'], $limpio['hora_fin'], $idHorario ?: null)) {
            $this->campoError = 'hora_inicio';
            $this->mensaje = 'El horario se solapa con otro horario del mismo médico ese día.';
            return false;
        }

        // Mensaje 8: contarFuturasEnHorario(). E3 al modificar.
        if ($actual !== null && !$this->confirmarCitasAfectadas($actual, $limpio, $confirmado)) {
            return false;
        }

        // Mensaje 9: insertar() / actualizar(). Mensaje 10: registrar("HORARIO").
        if ($actual === null) {
            $horarios->insertar($limpio);
            $this->registrar('Registró ' . $this->describir($limpio));
            $this->mensaje = 'Horario registrado correctamente.';
        } else {
            $horarios->actualizar($idHorario, $limpio);
            $this->registrar('Modificó ' . $this->describir($limpio));
            $this->mensaje = 'Horario actualizado correctamente.';
        }

        return true;
    }

    private function confirmarCitasAfectadas(array $horario, ?array $nuevo, bool $confirmado): bool
    {
        if ($confirmado || !$horario['estado']) {
            return true;
        }
        $this->citasAfectadas = (new Cita())->contarFuturasEnHorario($horario, $nuevo);
        if ($this->citasAfectadas === 0) {
            return true;
        }
        $this->mensaje = "Este cambio afecta {$this->citasAfectadas} cita(s) futura(s) programada(s). Las citas no se eliminarán. ¿Desea continuar?";
        return false;
    }

    // E1 (paso 4): la hora fin debe ser posterior y el rango múltiplo de la duración.
    private function validar(array $datos, int $idMedico): ?array
    {
        $dia = (int) ($datos['dia_semana'] ?? 0);
        $inicio = (string) ($datos['hora_inicio'] ?? '');
        $fin = (string) ($datos['hora_fin'] ?? '');
        $duracion = (int) ($datos['duracion_cita_min'] ?? 0);

        if (!isset(self::DIAS[$dia])) {
            return $this->invalido('dia_semana', 'Seleccione el día de la semana.');
        }
        if (!in_array($duracion, self::DURACIONES, true)) {
            return $this->invalido('duracion_cita_min', 'Seleccione una duración de cita válida.');
        }
        $minutos = fn (string $h) => preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $h, $m) ? (int) $m[1] * 60 + (int) $m[2] : null;
        $ini = $minutos($inicio);
        $fn = $minutos($fin);
        if ($ini === null) {
            return $this->invalido('hora_inicio', 'Ingrese una hora de inicio válida.');
        }
        if ($fn === null) {
            return $this->invalido('hora_fin', 'Ingrese una hora de fin válida.');
        }
        if ($fn <= $ini) {
            return $this->invalido('hora_fin', 'La hora fin debe ser posterior a la hora inicio');
        }
        if (($fn - $ini) % $duracion !== 0) {
            return $this->invalido('hora_fin', "El rango debe ser múltiplo de la duración de la cita ($duracion min).");
        }

        return [
            'id_medico'         => $idMedico,
            'dia_semana'        => $dia,
            'hora_inicio'       => $inicio,
            'hora_fin'          => $fin,
            'duracion_cita_min' => $duracion,
        ];
    }

    private function describir(array $horario): string
    {
        $codigo = '';
        foreach ((new Medico())->buscar() as $fila) {
            if ((int) $fila['id_medico'] === (int) $horario['id_medico']) {
                $codigo = $fila['codigo'];
            }
        }
        return sprintf('%s %s %s-%s', $codigo, mb_substr(self::DIAS[(int) $horario['dia_semana']], 0, 3), $horario['hora_inicio'], $horario['hora_fin']);
    }

    private function registrar(string $detalle): void
    {
        (new Bitacora())->registrar((int) ($this->usuarioSesion['id_usuario'] ?? 0), 'HORARIO', 'Horarios', $detalle);
    }

    private function invalido(string $campo, string $mensaje): null
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return null;
    }
}
