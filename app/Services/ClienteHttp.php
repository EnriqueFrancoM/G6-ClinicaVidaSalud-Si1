<?php

namespace App\Services;

// POST o GET contra un proveedor externo. Solo HTTPS. No sigue redirecciones.
class ClienteHttp
{
    public function enviar(string $metodo, string $url, string $cuerpo, array $cabeceras, int $espera = 15): array
    {
        if (!preg_match('#\Ahttps://#', $url)) {
            throw new \RuntimeException('Solo se permiten llamadas HTTPS.');
        }

        if (function_exists('curl_init')) {
            return $this->curl($metodo, $url, $cuerpo, $cabeceras, $espera);
        }

        return $this->flujo($metodo, $url, $cuerpo, $cabeceras, $espera);
    }

    private function curl(string $metodo, string $url, string $cuerpo, array $cabeceras, int $espera): array
    {
        $conexion = curl_init($url);
        if ($conexion === false) {
            throw new \RuntimeException('No se pudo contactar al proveedor.');
        }
        $opciones = [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $espera,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($cuerpo !== '') {
            $opciones[CURLOPT_POSTFIELDS] = $cuerpo;
        }
        curl_setopt_array($conexion, $opciones);
        $respuesta = curl_exec($conexion);
        $codigo = (int) curl_getinfo($conexion, CURLINFO_RESPONSE_CODE);
        curl_close($conexion);
        if ($respuesta === false) {
            throw new \RuntimeException('Sin respuesta del proveedor.');
        }

        return ['codigo' => $codigo, 'cuerpo' => (string) $respuesta];
    }

    private function flujo(string $metodo, string $url, string $cuerpo, array $cabeceras, int $espera): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method'        => $metodo,
                'header'        => implode("\r\n", $cabeceras),
                'content'       => $cuerpo,
                'timeout'       => $espera,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);
        $respuesta = @file_get_contents($url, false, $contexto);
        $codigo = 0;
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('#\AHTTP/\S+\s+(\d+)#', $linea, $coincide)) {
                $codigo = (int) $coincide[1];
            }
        }
        if ($respuesta === false && $codigo === 0) {
            throw new \RuntimeException('Sin respuesta del proveedor.');
        }

        return ['codigo' => $codigo, 'cuerpo' => (string) $respuesta];
    }
}
