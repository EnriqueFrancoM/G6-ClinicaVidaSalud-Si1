<?php

namespace App\Controllers\Citas;

use App\Core\App;
use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Models\Cita;
use App\Models\Paciente;
use App\Services\GeneradorQR;

// RF24 Comprobante con código QR (mejora M4). El QR apunta a /citas/llegada: la recepción lo escanea
// para registrar la llegada del paciente (CU14).
class UI_Comprobante extends Controller
{
    public function mostrar(): void
    {
        $cita = (new Cita())->buscarPorCodigo((string) $this->input('codigo', ''));
        if ($cita === null || !$this->puedeVer($cita)) {
            http_response_code(404);
            View::render('errors/404', [], null);
            exit;
        }

        $enlace = $this->absoluta('/citas/llegada?codigo=' . rawurlencode($cita['codigo']));
        $this->view('citas/UI_Comprobante', [
            'cita'   => $cita,
            'qr'     => GeneradorQR::svg($enlace, 200),
            'enlace' => $enlace,
            'volver' => Session::hasPermission('POR_PACIENTE') ? '/portal/citas' : '/agenda?id_medico=' . (int) $cita['id_medico'] . '&fecha=' . $cita['fecha'],
        ]);
    }

    // Recepción y médicos ven cualquier comprobante; el paciente, solo los suyos.
    private function puedeVer(array $cita): bool
    {
        if (Session::hasPermission('CIT_GESTIONAR') || Session::hasPermission('AGE_CONSULTAR')) {
            return true;
        }
        $titular = (new Paciente())->validarTitular(Session::user() ?? []);

        return $titular !== null && (int) $titular['id_paciente'] === (int) $cita['id_paciente'];
    }

    // El QR necesita una URL completa para abrirse desde el lector del celular.
    private function absoluta(string $ruta): string
    {
        $url = App::url($ruta);
        if (preg_match('#^https?://#', $url)) {
            return $url;
        }
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $url;
    }
}
