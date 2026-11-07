<?php

namespace App\Services;

use App\Core\App;

// CU20 ProveedorNotificaciones. Mensaje: enviarMensaje(datos).
// EMAIL: SMTP (MAIL_* o relay de Brevo) o, si no hay servidor, la API HTTPS de Brevo.
// WHATSAPP: Meta Cloud API. SISTEMA: aviso dentro del portal del paciente.
// Sin proveedor configurado el envío falla de forma controlada (el recordatorio queda FALLIDO).
class ProveedorNotificaciones
{
    public string $detalle = '';

    // datos: canal, destinatario, asunto, cuerpo y, en WhatsApp, parametros de la plantilla.
    public function enviarMensaje(array $datos): bool
    {
        $canal = (string) ($datos['canal'] ?? '');
        if ($canal === 'SISTEMA') {
            $this->detalle = 'Aviso disponible en el portal del paciente.';
            return true;
        }
        if ($canal === 'WHATSAPP') {
            return $this->whatsapp($datos);
        }

        $destino = (string) ($datos['destinatario'] ?? '');
        if (filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
            $this->detalle = 'Destinatario inválido.';
            return false;
        }

        $smtp = App::config('mail');
        if (!empty($smtp['host'])) {
            try {
                $this->smtp($smtp, $destino, (string) $datos['asunto'], (string) $datos['cuerpo']);
                $this->detalle = 'Correo aceptado por el servidor SMTP.';
                return true;
            } catch (\Throwable $e) {
                error_log('[ProveedorNotificaciones] ' . $e->getMessage());
                $this->detalle = 'El servidor de correo rechazó el envío.';
                return false;
            }
        }

        return $this->brevoApi($destino, (string) ($datos['asunto'] ?? ''), (string) ($datos['cuerpo'] ?? ''));
    }

    private function brevoApi(string $destino, string $asunto, string $cuerpo): bool
    {
        $clave = trim((string) App::config('brevo.api_key', ''));
        $remitente = trim((string) App::config('brevo.sender_email', ''));
        if ($clave === '' || filter_var($remitente, FILTER_VALIDATE_EMAIL) === false) {
            $this->detalle = 'Proveedor de correo no configurado.';
            return false;
        }
        $nombre = trim((string) App::config('brevo.sender_name', ''));
        if ($nombre === '') {
            $nombre = (string) App::config('app.name', 'Clínica Vida Salud');
        }
        try {
            $respuesta = (new ClienteHttp())->enviar(
                'POST',
                'https://api.brevo.com/v3/smtp/email',
                (string) json_encode([
                    'sender'      => ['name' => $nombre, 'email' => $remitente],
                    'to'          => [['email' => $destino]],
                    'subject'     => $asunto,
                    'textContent' => $cuerpo,
                ], JSON_UNESCAPED_UNICODE),
                ['api-key: ' . $clave, 'accept: application/json', 'Content-Type: application/json']
            );
        } catch (\Throwable $e) {
            error_log('[ProveedorNotificaciones] ' . $e->getMessage());
            $this->detalle = 'El servidor de correo rechazó el envío.';
            return false;
        }
        if ($respuesta['codigo'] >= 200 && $respuesta['codigo'] < 300) {
            $this->detalle = 'Correo aceptado por Brevo.';
            return true;
        }
        error_log('[ProveedorNotificaciones] Brevo HTTP ' . $respuesta['codigo']);
        $this->detalle = 'El servidor de correo rechazó el envío.';
        return false;
    }

    private function whatsapp(array $datos): bool
    {
        $token = trim((string) App::config('whatsapp.token', ''));
        $numero = trim((string) App::config('whatsapp.phone_number_id', ''));
        $destino = CanalRecordatorio::telefono((string) ($datos['destinatario'] ?? ''));
        if ($token === '' || !preg_match('/\A\d{1,30}\z/', $numero) || $destino === null) {
            $this->detalle = 'WhatsApp no configurado.';
            return false;
        }
        $plantilla = trim((string) App::config('whatsapp.template', ''));
        if ($plantilla !== '') {
            $idioma = trim((string) App::config('whatsapp.idioma', 'es'));
            $parametros = array_map(
                fn (mixed $texto): array => ['type' => 'text', 'text' => self::textoPlantilla((string) $texto)],
                array_values(is_array($datos['parametros'] ?? null) ? $datos['parametros'] : [(string) ($datos['cuerpo'] ?? '')])
            );
            $cuerpo = [
                'messaging_product' => 'whatsapp',
                'to'                => $destino,
                'type'              => 'template',
                'template'          => [
                    'name'       => $plantilla,
                    'language'   => ['code' => $idioma !== '' ? $idioma : 'es'],
                    'components' => [['type' => 'body', 'parameters' => $parametros]],
                ],
            ];
        } else {
            // Sin plantilla aprobada, Meta solo acepta texto dentro de la ventana de 24 h del paciente.
            $cuerpo = [
                'messaging_product' => 'whatsapp',
                'to'                => $destino,
                'type'              => 'text',
                'text'              => ['preview_url' => false, 'body' => (string) ($datos['cuerpo'] ?? '')],
            ];
        }
        try {
            $respuesta = (new ClienteHttp())->enviar(
                'POST',
                'https://graph.facebook.com/v21.0/' . $numero . '/messages',
                (string) json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
                ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
            );
        } catch (\Throwable $e) {
            error_log('[ProveedorNotificaciones] ' . $e->getMessage());
            $this->detalle = 'WhatsApp rechazó el envío.';
            return false;
        }
        if ($respuesta['codigo'] >= 200 && $respuesta['codigo'] < 300) {
            $this->detalle = 'WhatsApp aceptó el mensaje.';
            return true;
        }
        error_log('[ProveedorNotificaciones] WhatsApp HTTP ' . $respuesta['codigo']);
        $this->detalle = 'WhatsApp rechazó el envío.';
        return false;
    }

    // La plantilla de Meta rechaza saltos de línea y un texto vacío.
    private static function textoPlantilla(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return '-';
        }

        return mb_substr($texto, 0, 900);
    }

    // Cliente SMTP mínimo (AUTH LOGIN, SSL o STARTTLS).
    private function smtp(array $c, string $destino, string $asunto, string $cuerpo): void
    {
        $seguro = strtolower((string) ($c['encryption'] ?? 'tls'));
        $host = ($seguro === 'ssl' ? 'ssl://' : '') . $c['host'];
        $conexion = @stream_socket_client($host . ':' . (int) ($c['port'] ?? 587), $errno, $error, 10);
        if ($conexion === false) {
            throw new \RuntimeException("No se pudo conectar: $error");
        }
        stream_set_timeout($conexion, 10);

        $leer = function (int $esperado) use ($conexion): void {
            $respuesta = '';
            while (($linea = fgets($conexion, 515)) !== false) {
                $respuesta .= $linea;
                if (isset($linea[3]) && $linea[3] === ' ') {
                    break;
                }
            }
            if ((int) substr($respuesta, 0, 3) !== $esperado) {
                throw new \RuntimeException('SMTP: ' . trim($respuesta));
            }
        };
        $enviar = function (string $comando, int $esperado) use ($conexion, $leer): void {
            fwrite($conexion, $comando . "\r\n");
            $leer($esperado);
        };

        $leer(220);
        $dominio = gethostname() ?: 'localhost';
        $enviar("EHLO $dominio", 250);
        if ($seguro === 'tls') {
            $enviar('STARTTLS', 220);
            if (!stream_socket_enable_crypto($conexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('No se pudo iniciar TLS.');
            }
            $enviar("EHLO $dominio", 250);
        }
        if (!empty($c['user'])) {
            $enviar('AUTH LOGIN', 334);
            $enviar(base64_encode((string) $c['user']), 334);
            $enviar(base64_encode((string) $c['pass']), 235);
        }

        $remitente = (string) ($c['from'] ?: $c['user']);
        $nombre = trim((string) ($c['from_name'] ?? ''));
        if ($nombre === '') {
            $nombre = (string) App::config('app.name', 'Clínica Vida Salud');
        }
        $enviar("MAIL FROM:<$remitente>", 250);
        $enviar("RCPT TO:<$destino>", 250);
        $enviar('DATA', 354);
        $cabeceras = [
            'From: =?UTF-8?B?' . base64_encode($nombre) . "?= <$remitente>",
            "To: <$destino>",
            'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
            'Date: ' . date(DATE_RFC2822),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        fwrite($conexion, implode("\r\n", $cabeceras) . "\r\n\r\n" . chunk_split(base64_encode($cuerpo)) . "\r\n.\r\n");
        $leer(250);
        $enviar('QUIT', 221);
        fclose($conexion);
    }
}
