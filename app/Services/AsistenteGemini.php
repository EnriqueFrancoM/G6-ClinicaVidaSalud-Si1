<?php

namespace App\Services;

use App\Core\App;

// Respaldo gratuito cuando OpenRouter se queda sin cupo. La clave es la del servidor.
class AsistenteGemini
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const RONDAS = 6;
    // Cada uno tiene su propio límite por minuto. El de 5 RPM se salta un minuto
    // cuando responde 429 y el turno sigue con el siguiente.
    private const LIBRES = [
        'gemini-3.5-flash-lite',
        'gemini-3.1-flash-lite',
        'gemini-3.1-flash-lite-preview',
        'gemini-2.5-flash-lite',
        'gemini-3.6-flash',
        'gemini-3-flash-preview',
        'gemini-3.5-flash',
        'gemini-2.5-flash',
        'gemma-4-26b-a4b-it',
        'gemma-4-31b-it',
        'gemini-3.7-flash',
        'gemini-3.8-flash',
    ];

    private static array $pausaHasta = [];

    public function __construct(private ?ClienteHttp $http = null)
    {
    }

    public function configurado(): bool
    {
        return $this->clave() !== '';
    }

    public function responder(string $mensaje, array $historial, bool $voz = false): string
    {
        $clave = $this->clave();
        if ($clave === '') {
            throw new \RuntimeException('Gemini no está configurado. Agregue GEMINI_API_KEY en el archivo .env.');
        }

        $herramientas = new HerramientasClinica();
        $definiciones = $herramientas->definiciones();
        $declaraciones = $this->declaraciones($definiciones);
        $contents = [];
        foreach (array_slice($historial, -12) as $item) {
            if (!is_array($item) || !in_array($item['role'] ?? '', ['user', 'assistant'], true)) {
                continue;
            }
            $texto = trim((string) ($item['content'] ?? ''));
            if ($texto === '') {
                continue;
            }
            $this->agregar($contents, $item['role'] === 'assistant' ? 'model' : 'user', [
                ['text' => mb_substr($texto, 0, 4000)],
            ]);
        }
        $this->agregar($contents, 'user', [['text' => $mensaje]]);

        $ultimoHecho = '';
        $sistema = AsistenteOpenRouter::instrucciones($definiciones, $voz);
        for ($ronda = 0; $ronda < self::RONDAS; $ronda++) {
            $respuesta = $this->completar($sistema, $contents, $declaraciones);
            $parts = is_array($respuesta['parts'] ?? null) ? $respuesta['parts'] : [];
            $llamadas = $this->llamadas($parts);
            $texto = $this->texto($parts);
            if ($llamadas === []) {
                return $texto !== '' ? $texto : ($ultimoHecho !== '' ? $ultimoHecho : 'No pude completar la respuesta.');
            }

            if (!isset($respuesta['role'])) {
                $respuesta['role'] = 'model';
            }
            $this->agregar($contents, 'model', is_array($respuesta['parts'] ?? null) ? $respuesta['parts'] : $parts);

            $respuestas = [];
            foreach ($llamadas as $llamada) {
                $nombre = (string) ($llamada['name'] ?? '');
                $args = is_array($llamada['args'] ?? null) ? $llamada['args'] : [];
                try {
                    $resultado = $herramientas->ejecutar($nombre, $args);
                } catch (\Throwable $e) {
                    error_log('[asistente] ' . $e->getMessage());
                    $resultado = '{"ok":false,"mensaje":"No se pudo completar la operación."}';
                }
                $decoded = json_decode(mb_substr($resultado, 0, 8000), true);
                if (is_array($decoded) && ($decoded['mensaje'] ?? '') !== '') {
                    $ultimoHecho = (string) $decoded['mensaje'];
                }
                if (!is_array($decoded)) {
                    $decoded = ['ok' => false, 'mensaje' => 'La operación devolvió un resultado demasiado largo.'];
                }
                $respuestas[] = [
                    'functionResponse' => [
                        'name' => $nombre,
                        'response' => $decoded,
                    ],
                ];
            }
            $this->agregar($contents, 'user', $respuestas);
        }

        return $ultimoHecho !== '' ? $ultimoHecho : 'La consulta necesitó demasiados pasos. Pida una parte más concreta.';
    }

    private function completar(string $sistema, array $contents, array $declaraciones): array
    {
        $modelos = $this->modelos();
        if ($modelos === []) {
            throw new \RuntimeException('Gemini no tiene cupo ahora. Espere un minuto: cada modelo gratuito tiene su propio límite.');
        }
        $ultimo = 'Gemini no tiene cupo ahora. Espere un minuto: cada modelo gratuito tiene su propio límite.';
        foreach ($modelos as $modelo) {
            try {
                $intento = $this->pedir($modelo, $sistema, $contents, $declaraciones);
            } catch (\RuntimeException $e) {
                $this->pausar($modelo, 600);
                $ultimo = $e->getMessage();
                continue;
            }
            if ($intento['ok'] === true) {
                return $intento['mensaje'];
            }
            $ultimo = $intento['aviso'];
            if ($intento['otro_modelo'] !== true) {
                throw new \RuntimeException($ultimo);
            }
            $this->pausar($modelo, (int) ($intento['pausa'] ?? 70));
        }

        throw new \RuntimeException($ultimo);
    }

    private function pedir(string $modelo, string $sistema, array $contents, array $declaraciones): array
    {
        $cuerpo = [
            'systemInstruction' => ['parts' => [['text' => $sistema]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1024,
            ],
        ];
        if ($declaraciones !== []) {
            $cuerpo['tools'] = [['functionDeclarations' => $declaraciones]];
            $cuerpo['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }
        $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'otro_modelo' => false, 'aviso' => 'No se pudo preparar la consulta.', 'mensaje' => []];
        }

        $respuesta = $this->http()->enviar('POST', self::URL . rawurlencode($modelo) . ':generateContent', $json, [
            'x-goog-api-key: ' . $this->clave(),
            'Content-Type: application/json',
        ], 18);

        $datos = json_decode($respuesta['cuerpo'], true);
        if (!is_array($datos) || $respuesta['codigo'] < 200 || $respuesta['codigo'] >= 300) {
            $crudo = is_array($datos) ? (string) ($datos['error']['message'] ?? '') : '';
            $aviso = $this->aviso($respuesta['codigo'], $crudo);

            return [
                'ok' => false,
                'otro_modelo' => $this->otroModelo($respuesta['codigo'], $crudo),
                'pausa' => $this->segundosPausa($respuesta['codigo'], $crudo),
                'aviso' => $aviso,
                'mensaje' => [],
            ];
        }

        $mensaje = $datos['candidates'][0]['content'] ?? null;
        if (!is_array($mensaje)) {
            return ['ok' => false, 'otro_modelo' => true, 'aviso' => 'Gemini no devolvió una respuesta.', 'mensaje' => []];
        }

        return ['ok' => true, 'otro_modelo' => false, 'aviso' => '', 'mensaje' => $mensaje];
    }

    private function modelos(): array
    {
        $ahora = time();
        $orden = self::LIBRES;
        $pedido = trim((string) App::config('gemini.model', ''));
        if ($pedido !== '' && $pedido !== 'libre' && $this->idValido($pedido) && !$this->esDePago($pedido)) {
            array_unshift($orden, $pedido);
        }
        $lista = [];
        foreach ($orden as $modelo) {
            if (in_array($modelo, $lista, true)) {
                continue;
            }
            if ((self::$pausaHasta[$modelo] ?? 0) > $ahora) {
                continue;
            }
            $lista[] = $modelo;
        }

        return $lista;
    }

    private function pausar(string $modelo, int $segundos): void
    {
        self::$pausaHasta[$modelo] = time() + max(1, $segundos);
    }

    private function idValido(string $modelo): bool
    {
        return preg_match('/\A[a-z0-9][a-z0-9.\-]{0,80}\z/', $modelo) === 1;
    }

    private function esDePago(string $modelo): bool
    {
        return preg_match('/argon|omni|-pro|pro-|image|imagen|tts|lyria|robot|computer|embed|nano-banana|deep-research|antigravity|transcribe/i', $modelo) === 1;
    }

    private function segundosPausa(int $codigo, string $aviso): int
    {
        if ($codigo === 404 || preg_match('/not found|not support|unknown model|function call/i', $aviso) === 1) {
            return 21600;
        }
        if ($codigo === 429 || preg_match('/quota|exhausted|rate|rpm/i', $aviso) === 1) {
            return 70;
        }

        return 60;
    }

    private function declaraciones(array $definiciones): array
    {
        $lista = [];
        foreach ($definiciones as $item) {
            $funcion = is_array($item) ? ($item['function'] ?? null) : null;
            if (!is_array($funcion) || ($funcion['name'] ?? '') === '') {
                continue;
            }
            $parametros = $funcion['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()];
            $lista[] = [
                'name' => (string) $funcion['name'],
                'description' => (string) ($funcion['description'] ?? ''),
                'parameters' => $parametros,
            ];
        }

        return $lista;
    }

    private function agregar(array &$contents, string $rol, array $parts): void
    {
        $ultimo = array_key_last($contents);
        if ($ultimo !== null && ($contents[$ultimo]['role'] ?? '') === $rol) {
            $contents[$ultimo]['parts'] = array_merge($contents[$ultimo]['parts'], $parts);
            return;
        }
        $contents[] = ['role' => $rol, 'parts' => $parts];
    }

    private function llamadas(array $parts): array
    {
        $lista = [];
        foreach ($parts as $parte) {
            if (is_array($parte) && is_array($parte['functionCall'] ?? null)) {
                $lista[] = $parte['functionCall'];
            }
        }

        return $lista;
    }

    private function texto(array $parts): string
    {
        $trozos = [];
        foreach ($parts as $parte) {
            if (!is_array($parte) || !empty($parte['thought']) || !is_string($parte['text'] ?? null)) {
                continue;
            }
            $trozos[] = $parte['text'];
        }

        return trim(implode("\n", $trozos));
    }

    private function otroModelo(int $codigo, string $aviso): bool
    {
        if ($codigo === 401) {
            return false;
        }
        if ($codigo === 403 && preg_match('/API key not valid|unregistered|invalid/i', $aviso) === 1) {
            return false;
        }
        if ($codigo === 400) {
            return preg_match('/not support|function call|unknown model|not found/i', $aviso) === 1;
        }

        return $codigo === 404 || $codigo === 429 || $codigo === 408 || $codigo >= 500 || $codigo === 403
            || preg_match('/not found|not support|unknown model|quota|exhausted|rate/i', $aviso) === 1;
    }

    private function aviso(int $codigo, string $aviso): string
    {
        $clave = $this->clave();
        if ($aviso === '' || ($clave !== '' && str_contains($aviso, $clave))) {
            return $this->avisoCodigo($codigo);
        }
        $aviso = preg_replace('#https?://\S+#', '', $aviso) ?? $aviso;
        $aviso = trim((string) preg_replace('/\s+/', ' ', $aviso));
        if ($aviso === '' || ($clave !== '' && str_contains($aviso, $clave))) {
            return $this->avisoCodigo($codigo);
        }
        if (preg_match('/limit|quota|credit|rate|exhausted|resource/i', $aviso)) {
            return 'Gemini no tiene cupo ahora.';
        }
        if ($codigo === 401 || $codigo === 403) {
            return 'La clave de Gemini no es válida.';
        }

        return mb_substr($aviso, 0, 240);
    }

    private function avisoCodigo(int $codigo): string
    {
        if ($codigo === 401 || $codigo === 403) {
            return 'La clave de Gemini no es válida.';
        }
        if ($codigo === 429 || $codigo === 402) {
            return 'Gemini no tiene cupo ahora.';
        }

        return 'Gemini respondió HTTP ' . $codigo . '.';
    }

    private function clave(): string
    {
        return trim((string) App::config('gemini.api_key', ''));
    }

    private function http(): ClienteHttp
    {
        return $this->http ??= new ClienteHttp();
    }
}
