<?php

namespace App\Controllers\Citas;

use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\HistorialEstadoCita;
use App\Models\Medico;

// CU14 control. Mensajes: cambiarEstado(datos), validarAcceso().
// Entidades: Cita.validarTransición(código, estado), HistorialEstadoCita.registrarTransición(datos),
// Bitacora.registrarCambio(datos).
class CtrlEstadoCita
{
    // Permiso que exige cada estado destino: la recepción confirma o registra inasistencia;
    // el médico (o quien tenga CIT_ATENDER) marca la cita como atendida.
    public const PERMISO_POR_ESTADO = [
        'CONFIRMADA'   => 'CIT_GESTIONAR',
        'INASISTENCIA' => 'CIT_GESTIONAR',
        'ATENDIDA'     => 'CIT_ATENDER',
    ];

    public ?array $usuarioActual;

    public string $mensaje = '';

    public ?array $cita = null;

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    // Paso 3: comprobar rol. Sin estado, indica si puede usar el caso de uso; con estado, si puede aplicar ese.
    public function validarAcceso(?string $estado = null): bool
    {
        if ($estado === null) {
            return Session::hasPermission('CIT_GESTIONAR') || Session::hasPermission('CIT_ATENDER');
        }
        $permiso = self::PERMISO_POR_ESTADO[$estado] ?? null;

        return $permiso !== null && Session::hasPermission($permiso);
    }

    public function buscarCita(string $codigo): ?array
    {
        $cita = trim($codigo) === '' ? null : (new Cita())->buscarPorCodigo($codigo);
        if ($cita !== null && !$this->dentroDeAlcance($cita)) {
            return null;
        }
        return $cita;
    }

    // Estados a los que el usuario puede llevar la cita desde su estado actual.
    public function estadosPermitidos(array $cita): array
    {
        $destinos = [];
        foreach (Cita::TRANSICIONES[$cita['estado']] ?? [] as $estado) {
            if ($this->validarAcceso($estado) && $this->momentoValido($cita, $estado)) {
                $destinos[] = $estado;
            }
        }
        return $destinos;
    }

    // CU14 mensaje cambiarEstado(datos): código de cita, estado solicitado y observación.
    public function cambiarEstado(array $datos): bool
    {
        $codigo = (string) ($datos['codigo'] ?? '');
        $estado = strtoupper(trim((string) ($datos['estado'] ?? '')));
        $observacion = preg_replace('/\s+/u', ' ', trim((string) ($datos['observacion'] ?? '')));

        if (!isset(Cita::ESTADOS[$estado])) {
            return $this->error('Seleccione un estado válido.');
        }
        if (mb_strlen($observacion) > 200) {
            return $this->error('La observación admite hasta 200 caracteres.');
        }

        // Mensaje validarTransición(código, estado).
        ['cita' => $cita, 'valida' => $valida] = (new Cita())->validarTransicion($codigo, $estado);
        if ($cita === null || !$this->dentroDeAlcance($cita)) {
            return $this->error('No existe una cita con ese código en su agenda.');
        }
        if (!$valida) {
            // PN09: p. ej. ATENDIDA → PROGRAMADA se rechaza.
            return $this->error('Transición no permitida: ' . Cita::ESTADOS[$cita['estado']] . ' → ' . Cita::ESTADOS[$estado] . '.');
        }
        // Paso 3: rol autorizado para ese estado.
        if (!$this->validarAcceso($estado)) {
            return $this->error('Su rol no puede marcar una cita como ' . mb_strtolower(Cita::ESTADOS[$estado]) . '.');
        }
        if (!$this->momentoValido($cita, $estado)) {
            return $this->error('Una cita solo se marca como atendida o inasistencia a partir de su fecha y hora.');
        }

        return $this->aplicar($cita, $estado, $observacion !== '' ? $observacion : null);
    }

    // RF24: la recepción escanea el comprobante QR y registra la llegada (una cita programada queda confirmada).
    public function registrarLlegada(string $codigo): bool
    {
        if (!Session::hasPermission('CIT_GESTIONAR')) {
            return $this->error('No tiene permiso para registrar llegadas.');
        }
        $cita = (new Cita())->buscarPorCodigo($codigo);
        if ($cita === null) {
            return $this->error('El comprobante no corresponde a ninguna cita.');
        }
        if (!in_array($cita['estado'], Cita::PENDIENTES, true)) {
            return $this->error('La cita ' . $cita['codigo'] . ' está ' . mb_strtolower(Cita::ESTADOS[$cita['estado']]) . '; no se registra la llegada.');
        }
        if ($cita['fecha'] !== date('Y-m-d')) {
            return $this->error('La cita ' . $cita['codigo'] . ' es del ' . date('d/m/Y', strtotime($cita['fecha'])) . ', no de hoy.');
        }

        return $this->aplicar($cita, 'CONFIRMADA', 'Llegada registrada con el comprobante QR');
    }

    // Pasos 4 y 5: transición e historial en una transacción.
    private function aplicar(array $cita, string $estado, ?string $observacion): bool
    {
        $idUsuario = (int) $this->usuarioActual['id_usuario'];
        $db = Database::connection();
        $db->beginTransaction();
        try {
            if ($estado !== $cita['estado'] && !(new Cita())->actualizarEstado((int) $cita['id_cita'], $cita['estado'], $estado)) {
                $db->rollBack();
                return $this->error('La cita cambió de estado entretanto. Revísela nuevamente.');
            }
            // Mensaje registrarTransición(datos).
            (new HistorialEstadoCita())->registrarTransicion([
                'id_cita'         => (int) $cita['id_cita'],
                'id_usuario'      => $idUsuario,
                'estado_anterior' => $cita['estado'],
                'estado_nuevo'    => $estado,
                'observacion'     => $observacion,
            ]);
            // Mensaje registrarCambio(datos).
            (new Bitacora())->registrarCambio([
                'id_usuario'      => $idUsuario,
                'codigo'          => $cita['codigo'],
                'estado_anterior' => $cita['estado'],
                'estado_nuevo'    => $estado,
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->cita = (new Cita())->buscarPorId((int) $cita['id_cita']);
        $this->mensaje = $estado === $cita['estado']
            ? 'Llegada registrada para la cita ' . $cita['codigo'] . '.'
            : 'La cita ' . $cita['codigo'] . ' quedó como ' . Cita::ESTADOS[$estado] . '.';

        return true;
    }

    // El médico solo gestiona las citas de su agenda; recepción y administración, todas.
    private function dentroDeAlcance(array $cita): bool
    {
        if (Session::hasPermission('CIT_GESTIONAR')) {
            return true;
        }
        $medico = (new Medico())->obtenerPorUsuario((int) ($this->usuarioActual['id_usuario'] ?? 0));

        return $medico !== null && (int) $medico['id_medico'] === (int) $cita['id_medico'];
    }

    // Atendida e inasistencia recién a partir del inicio de la cita.
    private function momentoValido(array $cita, string $estado): bool
    {
        if (!in_array($estado, ['ATENDIDA', 'INASISTENCIA'], true)) {
            return true;
        }
        return strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) <= time();
    }

    private function error(string $mensaje): bool
    {
        $this->mensaje = $mensaje;
        return false;
    }
}
