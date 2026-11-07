<?php

namespace App\Services;

use App\Core\App;

// CU20 ProveedorNotificaciones. Mensaje: enviarMensaje(datos).
// Canal EMAIL: SMTP configurado en .env (MAIL_*). Canal SISTEMA: aviso dentro del portal del paciente.
// Sin proveedor configurado el envío falla de forma controlada (el recordatorio queda FALLIDO).
class ProveedorNotificaciones
{
    public string $detalle = '';

    // datos: canal, destinatario, asunto, cuerpo. Devuelve true si el proveedor aceptó el mensaje.
    public function enviarMensaje(array $datos): bool
    {
        if (($datos['canal'] ?? '') === 'SISTEMA') {
            $this->detalle = 'Aviso disponible en el portal del paciente.';
            return true;
        }

        $destino = (string) ($datos['destinatario'] ?? '');
        if (filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
            $this->detalle = 'Destinatario inválido.';
            return false;
        }

        $smtp = App::config('mail');
        if (empty($smtp['host'])) {
            $this->detalle = 'Proveedor de correo no configurado.';
            return false;
        }

        try {
            $this->smtp($smtp, $destino, (string) $datos['asunto'], (string) $datos['cuerpo']);
            $this->detalle = 'Correo aceptado por el servidor SMTP.';
            return true;
        } catch (\Throwable $e) {
            // RNF03: el detalle técnico no se muestra al usuario.
            error_log('[ProveedorNotificaciones] ' . $e->getMessage());
            $this->detalle = 'El servidor de correo rechazó el envío.';
            return false;
        }
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
        $enviar("MAIL FROM:<$remitente>", 250);
        $enviar("RCPT TO:<$destino>", 250);
        $enviar('DATA', 354);
        $cabeceras = [
            'From: =?UTF-8?B?' . base64_encode((string) App::config('app.name', 'Clínica Vida Salud')) . "?= <$remitente>",
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
