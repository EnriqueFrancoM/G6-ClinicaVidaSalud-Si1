<?php

namespace App\Controllers\Portal;

use App\Core\App;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Recordatorio;

// CU18 boundary. Mensajes: solicitar(reservar), mostrarResultado(), mostrarError().
class UI_MisCitas extends Controller
{
    // Pasos 2 y 3: filtrar especialidad y médico, seleccionar horario. POST: reservar(datos)
    // o confirmar / cancelar una cita propia (RF21).
    public function solicitar(): void
    {
        $control = new CtrlReserva();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = (string) $this->input('accion', 'reservar');
            $ok = $accion === 'reservar'
                ? $control->reservar($this->datosReserva())
                : $control->responderCita((string) $this->input('codigo', ''), $accion);
            $ok ? $this->mostrarResultado($control) : $this->mostrarError($control);
        }

        $paciente = $control->titular();
        $filtro = [
            'id_especialidad' => (int) $this->input('id_especialidad', 0),
            'fecha'           => (string) $this->input('fecha', date('Y-m-d')),
            'motivo'          => (string) $this->input('motivo', ''),
        ];
        $fechaOk = \App\Core\App::fechaIso($filtro['fecha']);
        $cupos = null;
        $avisoFecha = null;
        $horarios = new HorarioAtencion();
        if ($paciente !== null && $filtro['id_especialidad'] > 0 && !$fechaOk) {
            $avisoFecha = 'Ingrese una fecha válida.';
        } elseif ($paciente !== null && $filtro['id_especialidad'] > 0 && $filtro['fecha'] < date('Y-m-d')) {
            $avisoFecha = 'No se pueden reservar citas en fechas pasadas.';
        } elseif ($paciente !== null && $filtro['id_especialidad'] > 0 && $fechaOk) {
            $cupos = $horarios->cuposDeEspecialidad($filtro['id_especialidad'], $filtro['fecha']);
        }

        $this->view('portal/UI_MisCitas', [
            'paciente'       => $paciente,
            'filtro'         => $filtro,
            'cupos'          => $cupos,
            'sinCupo'        => $cupos === [] ? $this->opcionesSinCupo($filtro, $horarios) : null,
            'avisoFecha'     => $avisoFecha,
            'grupos'         => $paciente !== null ? $control->misCitas((int) $paciente['id_paciente']) : [],
            'avisos'         => $paciente !== null ? (new Recordatorio())->avisosDelPaciente((int) $paciente['id_paciente']) : [],
            'especialidades' => (new Especialidad())->listarConMedicoActivo(),
            'campoError'     => Session::flash('campo_error'),
            'mensajeCampo'   => Session::flash('error_detalle'),
            'reservar'       => $this->input('reservar') !== null || $filtro['id_especialidad'] > 0,
        ]);
    }

    // El horario elegido trae el médico: "id|hora". No se elige el médico antes de ver los cupos.
    private function datosReserva(): array
    {
        $elegido = (string) $this->input('hora_inicio', '');
        $idMedico = 0;
        $hora = $elegido;
        if (str_contains($elegido, '|')) {
            [$idMedico, $hora] = explode('|', $elegido, 2);
        }

        return [
            'id_especialidad' => $this->input('id_especialidad'),
            'id_medico'       => (int) $idMedico,
            'fecha'           => $this->input('fecha'),
            'hora_inicio'     => $hora,
            'motivo'          => $this->input('motivo'),
        ];
    }

    private function opcionesSinCupo(array $filtro, HorarioAtencion $horarios): array
    {
        $idEspecialidad = (int) $filtro['id_especialidad'];
        $fechas = $horarios->otrasFechasDeEspecialidad($idEspecialidad, $filtro['fecha']);
        $base = ['reservar' => 1, 'id_especialidad' => $idEspecialidad, 'motivo' => $filtro['motivo']];
        foreach ($fechas as &$fecha) {
            $fecha['url'] = App::url('/portal/citas?' . http_build_query($base + [
                'fecha' => $fecha['fecha'],
            ]));
        }
        unset($fecha);

        return [
            'causa'           => $horarios->causaSinCupoEspecialidad($idEspecialidad, $filtro['fecha']),
            'etiqueta_fechas' => 'Otras fechas con cupo en esta especialidad:',
            'fechas'          => $fechas,
            'medicos'         => [],
            'espera'          => null,
        ];
    }

    // Paso 6: mostrar código y estado en Mis citas.
    private function mostrarResultado(CtrlReserva $control): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect('/portal/citas');
    }

    // Alternativas: reserva simultánea o cupo ya ocupado.
    private function mostrarError(CtrlReserva $control): never
    {
        Session::flash('error_detalle', $control->mensaje);
        Session::flash('campo_error', (string) $control->campoError);
        $this->redirect('/portal/citas?' . http_build_query([
            'reservar'        => 1,
            'id_especialidad' => $this->input('id_especialidad', ''),
            'fecha'           => $this->input('fecha', ''),
            'motivo'          => $this->input('motivo', ''),
        ]));
    }
}
