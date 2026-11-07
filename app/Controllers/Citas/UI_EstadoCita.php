<?php

namespace App\Controllers\Citas;

use App\Core\Controller;
use App\Core\EnlaceOpaco;
use App\Core\Session;
use App\Core\View;
use App\Models\Cita;

// CU14 boundary. Mensajes: solicitar(cambiarEstado), mostrarResultado(), mostrarError().
// RF24: llegada por comprobante QR (registrarLlegada).
class UI_EstadoCita extends Controller
{
    // Paso 1: consultar la cita. Paso 2 (POST): seleccionar estado → cambiarEstado(datos).
    public function solicitar(): void
    {
        $control = new CtrlEstadoCita();
        if (!$control->validarAcceso()) {
            $this->sinPermiso();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = [
                'codigo'      => $this->input('codigo'),
                'estado'      => $this->input('estado'),
                'observacion' => $this->input('observacion'),
            ];
            if ($control->cambiarEstado($datos)) {
                $this->mostrarResultado($control);
            }
            $this->mostrarError($control);
        }

        $codigo = (string) $this->input('codigo', '');
        $cita = $control->buscarCita($codigo);

        $this->view('citas/UI_EstadoCita', [
            'codigo'   => $codigo,
            'cita'     => $cita,
            'aviso'    => $codigo !== '' && $cita === null ? 'No existe una cita con ese código en su agenda.' : null,
            'destinos' => $cita !== null ? $control->estadosPermitidos($cita) : [],
        ]);
    }

    // RF24: el QR abre esta pantalla. GET solo muestra la cita; la llegada se confirma con POST.
    public function registrarLlegada(): void
    {
        $control = new CtrlEstadoCita();
        $token = (string) $this->input('t', '');
        $codigo = $this->codigoLlegada($token);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($control->registrarLlegada($codigo)) {
                $this->mostrarResultado($control);
            }
            Session::flash('error_detalle', $control->mensaje);
            $this->redirect($this->rutaLlegada($codigo));
        }

        $cita = $codigo !== '' ? (new Cita())->buscarPorCodigo($codigo) : null;
        $this->view('citas/UI_Llegada', [
            'codigo'  => $codigo,
            'cita'    => $cita,
            'mensaje' => Session::flash('error_detalle'),
            'invalido' => $token !== '' && $codigo === '',
            'puede'   => $cita !== null
                && in_array($cita['estado'], Cita::PENDIENTES, true)
                && $cita['fecha'] === date('Y-m-d'),
        ]);
    }

    // Paso 6: mostrar estado actualizado.
    private function mostrarResultado(CtrlEstadoCita $control): never
    {
        Session::flash('success', $control->mensaje);
        $this->redirect('/citas/estado?codigo=' . rawurlencode($control->cita['codigo']));
    }

    // Alternativas: transición inválida o usuario sin permiso.
    private function mostrarError(CtrlEstadoCita $control): never
    {
        Session::flash('error', $control->mensaje);
        $this->redirect('/citas/estado?codigo=' . rawurlencode((string) $this->input('codigo', '')));
    }

    private function codigoLlegada(string $token): string
    {
        if ($token !== '') {
            $abierto = EnlaceOpaco::abrir($token);
            if ($abierto !== null && ($abierto['uso'] ?? '') === 'L') {
                return (string) ($abierto['datos'][0] ?? '');
            }

            return '';
        }

        return (string) $this->input('codigo', '');
    }

    private function rutaLlegada(string $codigo): string
    {
        $token = $codigo !== '' ? EnlaceOpaco::sellar('L', [$codigo]) : '';
        if ($token !== '') {
            return '/citas/llegada?t=' . rawurlencode($token);
        }

        return '/citas/llegada?codigo=' . rawurlencode($codigo);
    }

    private function sinPermiso(): never
    {
        http_response_code(403);
        View::render('errors/403', [], null);
        exit;
    }
}
