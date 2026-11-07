<?php

namespace App\Controllers\Citas;

use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HistorialEstadoCita;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Recordatorio;
use App\Models\SolicitudEspera;

// CU21 control. Mensajes: registrarSolicitud(datos), validarAcceso().
// Entidades: SolicitudEspera.registrarSinDuplicado(datos), HorarioAtencion.buscarCupoCompatible(datos),
// Cita.asignarSiDisponible(datos). RN17: un cupo liberado se ofrece primero a la lista de espera.
class CtrlListaEspera
{
    public ?array $usuarioActual;

    public string $mensaje = '';

    public ?string $campoError = null;

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    public function validarAcceso(): bool
    {
        return Session::hasPermission('CIT_GESTIONAR');
    }

    // Cola vigente (o filtrada por estado). Antes se devuelven a la cola las ofertas vencidas.
    public function listar(?string $estado = null): array
    {
        $this->revisarOfertasVencidas();
        return (new SolicitudEspera())->listar($estado);
    }

    // CU21 mensaje registrarSolicitud(datos). accion: registrar | aceptar | rechazar | cancelar.
    public function registrarSolicitud(array $datos): bool
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para gestionar la lista de espera.');
        }

        return match ($datos['accion'] ?? 'registrar') {
            'aceptar'  => $this->aceptarOferta((int) $datos['id_solicitud']),
            'rechazar' => $this->rechazarOferta((int) $datos['id_solicitud']),
            'cancelar' => $this->cancelarSolicitud((int) $datos['id_solicitud']),
            default    => $this->registrar($datos),
        };
    }

    // Pasos 1 a 4: paciente, especialidad y preferencias; sin duplicados; se guarda el orden de ingreso.
    // Paso 5: si ya hay un cupo compatible libre, se ofrece de inmediato.
    private function registrar(array $datos): bool
    {
        $paciente = (new Paciente())->buscarPaciente((string) ($datos['paciente'] ?? ''));
        if ($paciente === null || !in_array($paciente['estado'], [true, 't', '1', 1], true)) {
            return $this->error('No existe un paciente activo con ese CI o código.', 'paciente');
        }
        $idEspecialidad = (int) ($datos['id_especialidad'] ?? 0);
        $especialidad = (new Especialidad())->buscarPorId($idEspecialidad);
        if ($especialidad === null || !in_array($especialidad['estado'], [true, 't', '1', 1], true)) {
            return $this->error('Seleccione una especialidad activa.', 'id_especialidad');
        }
        $idMedico = (int) ($datos['id_medico'] ?? 0) ?: null;
        if ($idMedico !== null && !(new Medico())->atiendeEspecialidad($idMedico, $idEspecialidad)) {
            return $this->error('El médico elegido no está activo o no atiende esa especialidad.', 'id_medico');
        }
        $desde = $this->fecha($datos['fecha_desde'] ?? '');
        $hasta = $this->fecha($datos['fecha_hasta'] ?? '');
        if ($desde === false || $hasta === false) {
            return $this->error('Ingrese fechas válidas.', 'fecha_desde');
        }
        if ($hasta !== null && $hasta < date('Y-m-d')) {
            return $this->error('La fecha "hasta" no puede ser pasada.', 'fecha_hasta');
        }
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            return $this->error('La fecha "desde" debe ser anterior o igual a la fecha "hasta".', 'fecha_desde');
        }
        $preferencias = trim((string) ($datos['preferencias'] ?? ''));
        if (mb_strlen($preferencias) > 200) {
            return $this->error('Las preferencias admiten hasta 200 caracteres.', 'preferencias');
        }

        $solicitud = [
            'id_paciente'         => (int) $paciente['id_paciente'],
            'id_especialidad'     => $idEspecialidad,
            'id_medico'           => $idMedico,
            'fecha_desde'         => $desde,
            'fecha_hasta'         => $hasta,
            'preferencias'        => $preferencias !== '' ? $preferencias : null,
            'id_usuario_registro' => (int) $this->usuarioActual['id_usuario'],
        ];

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $id = (new SolicitudEspera())->registrarSinDuplicado($solicitud);
            if ($id === null) {
                $db->rollBack();
                return $this->error('El paciente ya tiene una solicitud vigente en la lista de espera de ' . $especialidad['nombre'] . '.', 'paciente');
            }
            $this->bitacora('Inscribió a ' . $paciente['codigo'] . ' en espera de ' . $especialidad['nombre']);

            // Paso 5: buscarCupoCompatible(datos).
            $cupo = (new HorarioAtencion())->buscarCupoCompatible($solicitud);
            if ($cupo !== null) {
                (new SolicitudEspera())->ofertar($id, $cupo);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->mensaje = $paciente['nombres'] . ' ' . $paciente['apellidos'] . ' quedó en la lista de espera de ' . $especialidad['nombre'] . '.'
            . ($cupo !== null ? ' Hay un cupo compatible: se le ofreció el ' . date('d/m/Y', strtotime($cupo['fecha'])) . ' a las ' . $cupo['hora_inicio'] . '.' : '');

        return true;
    }

    // Paso 6: confirmar aceptación y volver a validar disponibilidad con asignarSiDisponible(datos).
    // La oferta se bloquea (FOR UPDATE) y el cupo se revalida dentro de la transacción:
    // horario vigente, sin bloqueo y sin otra cita. PN13 si el cupo ya no sirve.
    private function aceptarOferta(int $idSolicitud): bool
    {
        $db = Database::connection();
        $db->beginTransaction();
        $solicitudes = new SolicitudEspera();
        try {
            $s = $solicitudes->bloquearOfertada($idSolicitud);
            if ($s === null) {
                $db->rollBack();
                return $this->error('La solicitud no tiene una oferta vigente.');
            }
            if (strtotime((string) $s['oferta_hasta']) < time()) {
                $solicitudes->volverACola($idSolicitud);
                $db->commit();
                return $this->error('El plazo de la oferta venció; la solicitud volvió a la cola.');
            }
            $paciente = (new Paciente())->validarPaciente((int) $s['id_paciente']);
            if ($paciente === null) {
                $db->rollBack();
                return $this->error('El paciente ya no está activo.');
            }

            $datos = [
                'id_paciente'         => (int) $s['id_paciente'],
                'id_medico'           => (int) $s['oferta_id_medico'],
                'id_especialidad'     => (int) $s['id_especialidad'],
                'id_usuario_registro' => (int) $this->usuarioActual['id_usuario'],
                'fecha'               => $s['oferta_fecha'],
                'hora_inicio'         => $s['oferta_inicio'],
                'hora_fin'            => $s['oferta_fin'],
                'motivo'              => 'Asignada desde la lista de espera',
            ];
            $cupo = (new HorarioAtencion())->validarNuevoCupo([
                'id_medico'   => $datos['id_medico'],
                'fecha'       => $datos['fecha'],
                'hora_inicio' => $datos['hora_inicio'],
            ]);
            if ($cupo === null) {
                $solicitudes->volverACola($idSolicitud);
                $db->commit();
                return $this->error('El cupo ofrecido ya no está disponible (horario, bloqueo o cupo ocupado). La solicitud volvió a la cola conservando su orden.');
            }
            $datos['hora_fin'] = $cupo['hora_fin'];

            $cita = (new Cita())->asignarSiDisponible($datos);
            (new HistorialEstadoCita())->registrarInicial($cita['id_cita'], $datos['id_usuario_registro'], 'Asignada desde la lista de espera');
            (new Recordatorio())->programar($cita['id_cita'], $datos['fecha'], $datos['hora_inicio'], $paciente['email']);
            if (!$solicitudes->marcarAsignada($idSolicitud, $cita['id_cita'])) {
                $db->rollBack();
                return $this->error('La oferta ya no estaba disponible.');
            }
            $this->bitacora('Asignó ' . $cita['codigo'] . ' a ' . $s['paciente_codigo'] . ' desde la lista de espera');
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (!Cita::esCupoOcupado($e)) {
                throw $e;
            }
            $solicitudes->volverACola($idSolicitud);
            return $this->error('El cupo ofrecido ya fue ocupado. La solicitud volvió a la cola conservando su orden.');
        }

        $this->mensaje = "Cita {$cita['codigo']} asignada a {$s['paciente']} desde la lista de espera.";

        return true;
    }

    // El paciente no acepta: vuelve a la cola y el cupo pasa al siguiente compatible.
    private function rechazarOferta(int $idSolicitud): bool
    {
        $solicitudes = new SolicitudEspera();
        $s = $solicitudes->buscarPorId($idSolicitud);
        if ($s === null || $s['estado'] !== 'OFERTADA') {
            return $this->error('La solicitud no tiene una oferta vigente.');
        }
        $cupo = ['id_medico' => (int) $s['oferta_id_medico'], 'fecha' => $s['oferta_fecha'],
            'hora_inicio' => $s['oferta_inicio'], 'hora_fin' => $s['oferta_fin']];

        $db = Database::connection();
        $db->beginTransaction();
        $solicitudes->volverACola($idSolicitud);
        $siguiente = $this->ofrecerA($cupo, [$idSolicitud]);
        $db->commit();

        $this->mensaje = 'Oferta rechazada; la solicitud sigue en la cola.'
            . ($siguiente !== null ? ' El cupo se ofreció al siguiente de la lista.' : '');

        return true;
    }

    private function cancelarSolicitud(int $idSolicitud): bool
    {
        if (!(new SolicitudEspera())->cancelar($idSolicitud)) {
            return $this->error('La solicitud ya no está vigente.');
        }
        $this->bitacora('Retiró la solicitud de espera #' . $idSolicitud);
        $this->mensaje = 'Solicitud retirada de la lista de espera.';

        return true;
    }

    // RN17 (desde CU12 y CU13): el cupo liberado se ofrece al primero de la cola compatible.
    // Se llama después de confirmar la cancelación o reprogramación. Devuelve la solicitud ofertada o null.
    public function ofrecerCupoLiberado(array $cupo): ?array
    {
        $db = Database::connection();
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        $solicitud = $this->ofrecerA($cupo);
        if ($propia) {
            $db->commit();
        }

        return $solicitud;
    }

    // Ofertas con plazo vencido: vuelven a la cola y su cupo, si sigue libre, pasa al siguiente.
    public function revisarOfertasVencidas(): int
    {
        $db = Database::connection();
        $db->beginTransaction();
        $vencidas = (new SolicitudEspera())->ofertasVencidas();
        foreach ($vencidas as $v) {
            (new SolicitudEspera())->volverACola((int) $v['id_solicitud']);
            if ((new HorarioAtencion())->consultarCupoLiberado($v) !== null) {
                $this->ofrecerA($v, [(int) $v['id_solicitud']]);
            }
        }
        $db->commit();

        return count($vencidas);
    }

    private function ofrecerA(array $cupo, array $excluir = []): ?array
    {
        $solicitud = (new SolicitudEspera())->primeraCompatible($cupo, $excluir);
        if ($solicitud === null) {
            return null;
        }
        (new SolicitudEspera())->ofertar((int) $solicitud['id_solicitud'], $cupo);

        return $solicitud;
    }

    // null = vacío, false = inválida.
    private function fecha(string $valor): string|null|false
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        return \App\Core\App::fechaIso($valor) ? $valor : false;
    }

    private function bitacora(string $detalle): void
    {
        (new Bitacora())->registrar((int) ($this->usuarioActual['id_usuario'] ?? 0) ?: null, 'LISTA_ESPERA', 'Citas', $detalle);
    }

    private function error(string $mensaje, ?string $campo = null): bool
    {
        $this->mensaje = $mensaje;
        $this->campoError = $campo;
        return false;
    }
}
