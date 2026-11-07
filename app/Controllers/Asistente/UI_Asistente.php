<?php

namespace App\Controllers\Asistente;

use App\Core\Controller;
use App\Services\AsistenteOpenRouter;

// Módulo de conversación. La clave de OpenRouter no sale al navegador.
class UI_Asistente extends Controller
{
    public function abrir(): void
    {
        $this->view('asistente/UI_Asistente', [
            'listo' => (new AsistenteOpenRouter())->configurado(),
        ]);
    }

    public function conversar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
        if ($mensaje === '' || mb_strlen($mensaje) > 2000) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Escriba un mensaje de hasta 2000 caracteres.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $historial = json_decode((string) ($_POST['historial'] ?? '[]'), true);
        if (!is_array($historial)) {
            $historial = [];
        }

        $asistente = new AsistenteOpenRouter();
        if (!$asistente->configurado()) {
            http_response_code(503);
            echo json_encode([
                'ok' => false,
                'mensaje' => 'El asistente no está configurado. Agregue OPENROUTER_API_KEY en el archivo .env. La misma clave vale para todas las cuentas.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $respuesta = $asistente->responder($mensaje, $historial);
            echo json_encode(['ok' => true, 'mensaje' => $respuesta], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
