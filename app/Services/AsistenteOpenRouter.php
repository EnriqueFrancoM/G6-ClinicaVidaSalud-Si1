<?php

namespace App\Services;

use App\Core\App;
use App\Core\Session;

// Llama a OpenRouter con las herramientas de la clínica. La clave es la del servidor, no la de cada cuenta.
class AsistenteOpenRouter
{
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const RONDAS = 6;
    // Elige un modelo gratuito que esté disponible y acepte herramientas. No gasta crédito.
    private const MODELO_LIBRE = 'openrouter/free';

    public function configurado(): bool
    {
        return $this->clave() !== '';
    }

    public function responder(string $mensaje, array $historial, bool $voz = false): string
    {
        $clave = $this->clave();
        if ($clave === '') {
            throw new \RuntimeException('El asistente no está configurado. Agregue OPENROUTER_API_KEY en el archivo .env.');
        }

        $herramientas = new HerramientasClinica();
        $definiciones = $herramientas->definiciones();
        $mensajes = [$this->sistema($definiciones, $voz)];
        foreach (array_slice($historial, -12) as $item) {
            if (!is_array($item) || !in_array($item['role'] ?? '', ['user', 'assistant'], true)) {
                continue;
            }
            $texto = trim((string) ($item['content'] ?? ''));
            if ($texto !== '') {
                $mensajes[] = ['role' => $item['role'], 'content' => mb_substr($texto, 0, 4000)];
            }
        }
        $mensajes[] = ['role' => 'user', 'content' => $mensaje];

        $ultimoHecho = '';
        $modelo = $this->modelo();
        for ($ronda = 0; $ronda < self::RONDAS; $ronda++) {
            $respuesta = $this->completar($clave, $mensajes, $definiciones, $modelo);
            $llamadas = $respuesta['tool_calls'] ?? [];
            $texto = trim((string) ($respuesta['content'] ?? ''));
            if ($llamadas === []) {
                return $texto !== '' ? $texto : ($ultimoHecho !== '' ? $ultimoHecho : 'No pude completar la respuesta.');
            }

            $mensajes[] = [
                'role' => 'assistant',
                'content' => $texto,
                'tool_calls' => $llamadas,
            ];
            foreach ($llamadas as $llamada) {
                $nombre = (string) ($llamada['function']['name'] ?? '');
                $args = $this->argumentos($llamada['function']['arguments'] ?? '{}');
                try {
                    $resultado = $herramientas->ejecutar($nombre, $args);
                } catch (\Throwable $e) {
                    error_log('[asistente] ' . $e->getMessage());
                    $resultado = '{"ok":false,"mensaje":"No se pudo completar la operación."}';
                }
                $decoded = json_decode($resultado, true);
                if (is_array($decoded) && ($decoded['mensaje'] ?? '') !== '') {
                    $ultimoHecho = (string) $decoded['mensaje'];
                }
                $mensajes[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($llamada['id'] ?? ''),
                    'content' => mb_substr($resultado, 0, 8000),
                ];
            }
        }

        return $ultimoHecho !== '' ? $ultimoHecho : 'La consulta necesitó demasiados pasos. Pida una parte más concreta.';
    }

    private function modelo(): string
    {
        $pedido = trim((string) App::config('openrouter.model', self::MODELO_LIBRE));
        if ($pedido === '' || $pedido === 'openai/gpt-4o-mini') {
            return self::MODELO_LIBRE;
        }

        return $pedido;
    }

    private function completar(string $clave, array $mensajes, array $definiciones, string $modelo): array
    {
        $modelos = [$modelo];
        if ($modelo !== self::MODELO_LIBRE) {
            $modelos[] = self::MODELO_LIBRE;
        }

        foreach ($modelos as $candidato) {
            $intento = $this->pedir($clave, $mensajes, $definiciones, $candidato);
            if ($intento['ok'] === true) {
                return $intento['mensaje'];
            }
            if ($intento['reintentar'] !== true) {
                throw new \RuntimeException($intento['aviso']);
            }
        }

        throw new \RuntimeException('Ningún modelo gratuito está disponible ahora. El cupo se renueva a la medianoche UTC.');
    }

    private function pedir(string $clave, array $mensajes, array $definiciones, string $modelo): array
    {
        $cuerpo = [
            'model' => $modelo,
            'messages' => $mensajes,
            'temperature' => 0.2,
        ];
        if ($definiciones !== []) {
            $cuerpo['tools'] = $definiciones;
            $cuerpo['tool_choice'] = 'auto';
        }
        $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'reintentar' => false, 'aviso' => 'No se pudo preparar la consulta.', 'mensaje' => []];
        }

        $respuesta = (new ClienteHttp())->enviar('POST', self::URL, $json, [
            'Authorization: Bearer ' . $clave,
            'Content-Type: application/json',
            'HTTP-Referer: ' . ((string) App::config('app.base_url', '') ?: 'http://127.0.0.1'),
            'X-Title: Clinica Vida Salud',
        ], 45);

        $datos = json_decode($respuesta['cuerpo'], true);
        if (!is_array($datos) || $respuesta['codigo'] < 200 || $respuesta['codigo'] >= 300) {
            $crudo = is_array($datos) ? (string) ($datos['error']['message'] ?? '') : '';
            $aviso = $this->aviso($respuesta['codigo'], $crudo, $clave);

            return ['ok' => false, 'reintentar' => $this->reintentar($respuesta['codigo'], $crudo), 'aviso' => $aviso, 'mensaje' => []];
        }

        $mensaje = $datos['choices'][0]['message'] ?? null;
        if (!is_array($mensaje)) {
            return ['ok' => false, 'reintentar' => true, 'aviso' => 'OpenRouter no devolvió una respuesta.', 'mensaje' => []];
        }

        return ['ok' => true, 'reintentar' => false, 'aviso' => '', 'mensaje' => $mensaje];
    }

    private function reintentar(int $codigo, string $aviso): bool
    {
        if ($codigo === 401) {
            return false;
        }
        if (preg_match('/limit|quota|credit|rate|endpoint|unavailable|provider|model/i', $aviso)) {
            return true;
        }

        return $codigo === 402 || $codigo === 404 || $codigo === 408 || $codigo === 429 || $codigo >= 500;
    }

    private function aviso(int $codigo, string $aviso, string $clave): string
    {
        if ($aviso === '' || ($clave !== '' && str_contains($aviso, $clave))) {
            return 'OpenRouter respondió HTTP ' . $codigo . '.';
        }
        $aviso = preg_replace('#https?://\S+#', '', $aviso) ?? $aviso;
        $aviso = trim((string) preg_replace('/\s+/', ' ', $aviso));
        if (preg_match('/limit|quota|credit|rate/i', $aviso)) {
            return 'Ese modelo no tiene cupo. Se usa un modelo gratuito disponible.';
        }

        return mb_substr($aviso, 0, 240);
    }

    private function sistema(array $definiciones, bool $voz): array
    {
        $usuario = Session::user() ?? [];
        $nombres = array_map(static fn (array $item) => $item['function']['name'], $definiciones);
        $herramientas = $nombres === [] ? 'ninguna' : implode(', ', $nombres);

        return [
            'role' => 'system',
            'content' => 'Eres el asistente de Clínica Vida Salud. Hoy es ' . date('Y-m-d') . ' en America/La_Paz. '
                . 'La sesión es de ' . ($usuario['username'] ?? 'un usuario') . ', rol ' . ($usuario['rol'] ?? 'sin rol') . '. '
                . 'Puedes usar solo estas operaciones: ' . $herramientas . '. '
                . 'Crea, edita, consulta o da de baja únicamente cuando la persona lo pide y con los datos que falten preguntados antes. '
                . 'Si piden una cita y falta un dato, pregunta una sola cosa y espera la respuesta: primero el día y la hora, después la especialidad, después el motivo y, si el cupo no lo trae, el médico. '
                . 'No uses reservar_mi_cita ni programar_cita hasta tener especialidad, médico, fecha y hora de un cupo libre. Consulta los cupos antes, resume lo que vas a registrar y espera un sí. '
                . ($voz ? 'Es una llamada: responde en una o dos frases, sin listas ni asteriscos. ' : '')
                . 'Médicos, pacientes, especialidades y horarios no se borran: se desactivan y el historial queda. '
                . 'Un día festivo es un bloqueo de alcance general, para todos los médicos. Un bloqueo de un médico usa alcance medico. Esos registros sí se quitan con quitar_dia_bloqueado. '
                . 'Si una herramienta dice que hay citas futuras afectadas, pregunta una vez y, si la persona acepta, repite con confirmado true. '
                . 'No inventes ids, horarios ni pacientes. No pidas ni muestres la clave de OpenRouter: es una sola clave del servidor para todas las cuentas. '
                . 'Responde en español, breve, con el resultado de la herramienta.',
        ];
    }

    private function argumentos(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        $datos = json_decode((string) $valor, true);

        return is_array($datos) ? $datos : [];
    }

    private function clave(): string
    {
        return trim((string) App::config('openrouter.api_key', ''));
    }
}
