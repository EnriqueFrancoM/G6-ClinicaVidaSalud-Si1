<?php

namespace App\Controllers\Reportes;

use App\Controllers\Citas\CtrlCita;
use App\Controllers\Citas\CtrlListaEspera;
use App\Core\App;
use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\HistorialEstadoCita;
use App\Models\Recordatorio;
use App\Services\ProveedorNotificaciones;

// CU20 control. Mensajes: procesarPendientes(datos), validarAcceso().
// Entidades: Recordatorio.buscarPendientes(instante), Cita.validarCitaVigente(id),
// ProveedorNotificaciones.enviarMensaje(datos). RF21: el paciente confirma o cancela desde el enlace.
class CtrlRecordatorio
{
    public ?array $usuarioActual;

    public string $mensaje = '';

    public ?array $cita = null;

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    // El actor es el Tiempo; desde la aplicación solo lo ejecuta quien tiene el permiso de reportes.
    public function validarAcceso(): bool
    {
        return Session::hasPermission('REP_GENERAR');
    }

    // CU20 mensaje procesarPendientes(datos). datos: instante (Y-m-d H:i:s, por defecto ahora).
    // Devuelve el resumen de la ejecución: enviados, fallidos y descartados.
    public function procesarPendientes(array $datos = []): array
    {
        $instante = (string) ($datos['instante'] ?? date('Y-m-d H:i:s'));
        $resumen = ['enviados' => 0, 'fallidos' => 0, 'descartados' => 0, 'detalle' => []];
        $recordatorios = new Recordatorio();
        $proveedor = new ProveedorNotificaciones();

        $db = Database::connection();
        $db->beginTransaction();
        try {
            // Paso 2: buscarPendientes(instante). Solo PENDIENTE: un envío exitoso no se repite (PN12).
            foreach ($recordatorios->buscarPendientes($instante) as $r) {
                // Pasos 3 y 4: validarCitaVigente(id) descarta canceladas o ya pasadas; se verifica el contacto.
                $cita = (new Cita())->validarCitaVigente((int) $r['id_cita']);
                if ($cita === null) {
                    $recordatorios->marcarResultado((int) $r['id_recordatorio'], 'FALLIDO');
                    $resumen['descartados']++;
                    continue;
                }
                $canal = $r['canal'] === 'EMAIL' && $cita['paciente_email'] ? 'EMAIL' : 'SISTEMA';

                // Paso 5: enviarMensaje(datos). Paso 6: registrar el resultado del intento.
                $enviado = $proveedor->enviarMensaje([
                    'canal'        => $canal,
                    'destinatario' => $cita['paciente_email'],
                    'asunto'       => 'Recordatorio de su cita ' . $cita['codigo'] . ' – ' . App::config('app.name'),
                    'cuerpo'       => $this->cuerpoMensaje($cita),
                ]);
                $recordatorios->marcarResultado((int) $r['id_recordatorio'], $enviado ? 'ENVIADO' : 'FALLIDO');
                $resumen[$enviado ? 'enviados' : 'fallidos']++;
                $resumen['detalle'][] = $cita['codigo'] . ' (' . $canal . '): ' . $proveedor->detalle;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Las ofertas de la lista de espera con plazo vencido pasan al siguiente (RF22).
        $resumen['ofertas_vencidas'] = (new CtrlListaEspera())->revisarOfertasVencidas();

        (new Bitacora())->registrar(
            (int) ($this->usuarioActual['id_usuario'] ?? 0) ?: null,
            'RECORDATORIOS',
            'Reportes',
            sprintf('Enviados %d, fallidos %d, descartados %d', $resumen['enviados'], $resumen['fallidos'], $resumen['descartados'])
        );
        $this->mensaje = sprintf(
            'Ejecución completada: %d enviado(s), %d fallido(s) y %d descartado(s) por cita no vigente.',
            $resumen['enviados'], $resumen['fallidos'], $resumen['descartados']
        );

        return $resumen;
    }

    // RF21: firma del enlace de confirmar o cancelar (no se puede adivinar ni reutilizar para otra cita).
    public static function firmar(int $idCita): string
    {
        $clave = (string) App::config('app.key', '');
        if ($clave === '') {
            $clave = hash('sha256', json_encode(App::config('db')));
        }
        return substr(hash_hmac('sha256', 'cita:' . $idCita, $clave), 0, 32);
    }

    public static function enlace(int $idCita, string $respuesta): string
    {
        return App::url('/recordatorio/responder?' . http_build_query(['c' => $idCita, 'r' => $respuesta, 't' => self::firmar($idCita)]));
    }

    // RF21: respuesta del paciente desde el enlace del recordatorio.
    public function responder(int $idCita, string $respuesta, string $firma): bool
    {
        if (!hash_equals(self::firmar($idCita), $firma)) {
            return $this->error('El enlace no es válido.');
        }
        $cita = (new Cita())->buscarPorId($idCita);
        if ($cita === null) {
            return $this->error('La cita no existe.');
        }

        return $this->aplicarRespuesta($cita, $respuesta, $this->usuarioDelPaciente($cita));
    }

    // RF21 (también desde Mis citas): confirmar pasa PROGRAMADA → CONFIRMADA; cancelar libera el cupo.
    public function aplicarRespuesta(array $cita, string $respuesta, int $idUsuario): bool
    {
        $this->cita = $cita;
        if (!in_array($cita['estado'], Cita::PENDIENTES, true)) {
            return $this->error('La cita ' . $cita['codigo'] . ' ya está ' . mb_strtolower(Cita::ESTADOS[$cita['estado']]) . '.');
        }
        if (strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) <= time()) {
            return $this->error('La cita ' . $cita['codigo'] . ' ya pasó.');
        }

        if ($respuesta === 'confirmar') {
            if ($cita['estado'] === 'CONFIRMADA') {
                $this->mensaje = 'La cita ' . $cita['codigo'] . ' ya estaba confirmada. ¡Lo esperamos!';
                return true;
            }
            $db = Database::connection();
            $db->beginTransaction();
            if (!(new Cita())->actualizarEstado((int) $cita['id_cita'], 'PROGRAMADA', 'CONFIRMADA')) {
                $db->rollBack();
                return $this->error('La cita cambió de estado entretanto.');
            }
            (new HistorialEstadoCita())->registrarTransicion([
                'id_cita' => (int) $cita['id_cita'], 'id_usuario' => $idUsuario,
                'estado_anterior' => 'PROGRAMADA', 'estado_nuevo' => 'CONFIRMADA', 'observacion' => 'Confirmada por el paciente',
            ]);
            (new Bitacora())->registrar($idUsuario, 'CONFIRMAR', 'Portal', 'Paciente confirmó ' . $cita['codigo']);
            $db->commit();
            $this->mensaje = 'Cita ' . $cita['codigo'] . ' confirmada. ¡Lo esperamos!';
            return true;
        }

        if ($respuesta === 'cancelar') {
            $control = new CtrlCita();
            if (!$control->aplicarCancelacion($cita, 'Cancelada por el paciente', $idUsuario)) {
                return $this->error('La cita cambió de estado entretanto.');
            }
            $control->ofrecerLiberado($cita);
            $this->mensaje = 'Cita ' . $cita['codigo'] . ' cancelada. El horario quedó libre para otro paciente.';
            return true;
        }

        return $this->error('Respuesta no válida.');
    }

    private function cuerpoMensaje(array $cita): string
    {
        return "Hola {$cita['paciente']}:\n\n"
            . 'Le recordamos su cita ' . $cita['codigo'] . ' de ' . $cita['especialidad'] . ' con ' . $cita['medico']
            . ' el ' . date('d/m/Y', strtotime($cita['fecha'])) . ' a las ' . $cita['hora_inicio'] . ".\n\n"
            . 'Confirmar asistencia: ' . self::enlace((int) $cita['id_cita'], 'confirmar') . "\n"
            . 'Cancelar la cita: ' . self::enlace((int) $cita['id_cita'], 'cancelar') . "\n\n"
            . App::config('app.name');
    }

    // La respuesta por enlace se registra a nombre de la cuenta del paciente (o de quien registró la cita).
    private function usuarioDelPaciente(array $cita): int
    {
        $fila = Database::connection()->prepare(
            'SELECT COALESCE(p.id_usuario, c.id_usuario_registro) AS id FROM cita c JOIN paciente p ON p.id_paciente = c.id_paciente WHERE c.id_cita = :id'
        );
        $fila->execute(['id' => $cita['id_cita']]);

        return (int) $fila->fetchColumn();
    }

    private function error(string $mensaje): bool
    {
        $this->mensaje = $mensaje;
        return false;
    }
}
