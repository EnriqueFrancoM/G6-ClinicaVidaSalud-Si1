<?php

namespace App\Services;

use App\Core\App;

// Envío transaccional por la API de Brevo. La clave y el remitente viven en .env.
class CorreoBrevo
{
    public function configurado(): bool
    {
        return $this->clave() !== '' && $this->remitente() !== '';
    }

    public function enviarCodigo(string $destino, string $codigo): void
    {
        $minutos = (int) App::config('security.codigo_minutos', 10);
        $html = '<p>Su código de un solo uso para Clínica Vida Salud es <strong style="font-size:22px;letter-spacing:4px;">'
            . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8')
            . '</strong>.</p><p>Vence en ' . $minutos . ' minutos y solo puede usarse una vez.</p>';

        $this->enviar($destino, 'Código para restablecer su contraseña', $html);
    }

    private function enviar(string $destino, string $asunto, string $html): void
    {
        if (!$this->configurado()) {
            throw new \RuntimeException('Brevo no está configurado.');
        }

        $payload = json_encode([
            'sender' => [
                'name'  => (string) App::config('mail.sender_name', 'Clínica Vida Salud'),
                'email' => $this->remitente(),
            ],
            'to'          => [['email' => $destino]],
            'subject'     => $asunto,
            'htmlContent' => $html,
        ], JSON_UNESCAPED_UNICODE);

        $canal = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($canal, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $this->clave(),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $cuerpo = curl_exec($canal);
        $estado = (int) curl_getinfo($canal, CURLINFO_HTTP_CODE);
        $error = curl_error($canal);
        curl_close($canal);

        if ($cuerpo === false || $estado < 200 || $estado >= 300) {
            throw new \RuntimeException($error !== '' ? $error : 'Brevo respondió ' . $estado);
        }
    }

    private function clave(): string
    {
        return trim((string) App::config('mail.api_key', ''));
    }

    private function remitente(): string
    {
        return trim((string) App::config('mail.sender', ''));
    }
}
