<?php

namespace App\Services;

// Voz de la llamada. El texto se queda en el servidor: lo habla espeak-ng y vuelve un WAV.
class SintetizadorVoz
{
    private const MAXIMO = 500;

    private static ?array $motorListo = null;

    public function sintetizar(string $texto): ?string
    {
        $frase = $this->frase($texto);
        $motor = $frase === '' ? null : $this->motor();
        if ($frase === '' || $motor === null) {
            return null;
        }

        $archivo = tempnam(sys_get_temp_dir(), 'vsvoz');
        if ($archivo === false) {
            return null;
        }

        try {
            $codigo = $this->correr($motor['env'], [
                $motor['bin'],
                '-v',
                $motor['voz'],
                '-s',
                '145',
                '-a',
                '170',
                '-w',
                $archivo,
                '--stdin',
            ], $frase);
            if (!is_file($archivo)) {
                return null;
            }
            $wav = file_get_contents($archivo);
            if (!is_string($wav) || strlen($wav) < 44 || !str_starts_with($wav, 'RIFF')) {
                return null;
            }
            $declarado = unpack('V', substr($wav, 4, 4));
            if (!is_array($declarado) || ($declarado[1] + 8) > strlen($wav)) {
                return null;
            }
            if ($codigo !== 0) {
                return null;
            }
            return $wav;
        } finally {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }
    }

    private function frase(string $texto): string
    {
        $texto = str_replace(["\0", "\r", "\n", "\t"], ' ', $texto);
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }
        if (mb_strlen($texto) > self::MAXIMO) {
            $texto = mb_substr($texto, 0, self::MAXIMO);
        }
        return $texto;
    }

    private function motor(): ?array
    {
        if (self::$motorListo !== null) {
            return self::$motorListo;
        }
        $bin = $this->binario();
        if ($bin === '') {
            return null;
        }
        $env = $this->entorno($bin);
        $salida = '';
        if ($this->correr($env, [$bin, '--voices'], '', $salida) !== 0) {
            return null;
        }
        $voz = preg_match('/\bes-419\b/', $salida) === 1 ? 'es-419' : 'es';
        self::$motorListo = ['bin' => $bin, 'voz' => $voz, 'env' => $env];
        return self::$motorListo;
    }

    private function binario(): string
    {
        $orden = ['/usr/bin/espeak-ng', '/usr/bin/espeak'];
        if (defined('BASE_PATH')) {
            $orden[] = BASE_PATH . '/storage/voz/usr/bin/espeak-ng';
        }
        foreach ($orden as $ruta) {
            if (is_executable($ruta)) {
                return $ruta;
            }
        }
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $dir) {
            if ($dir === '') {
                continue;
            }
            foreach (['espeak-ng', 'espeak'] as $nombre) {
                $ruta = rtrim($dir, '/') . '/' . $nombre;
                if (is_executable($ruta)) {
                    return $ruta;
                }
            }
        }
        return '';
    }

    private function entorno(string $bin): array
    {
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            'HOME' => sys_get_temp_dir(),
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ];
        if (defined('BASE_PATH') && str_starts_with($bin, BASE_PATH . '/storage/voz/')) {
            $prefijo = BASE_PATH . '/storage/voz/usr';
            $env['ESPEAK_DATA_PATH'] = $prefijo . '/share';
            $env['LD_LIBRARY_PATH'] = $prefijo . '/lib';
        }
        return $env;
    }

    private function correr(array $env, array $comando, string $entrada, ?string &$salida = null): int
    {
        $proceso = proc_open($comando, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, $env);
        if (!is_resource($proceso)) {
            return 1;
        }

        fwrite($pipes[0], $entrada);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $leido = '';
        $inicio = microtime(true);
        $codigo = 1;
        while (true) {
            $estado = proc_get_status($proceso);
            $leido .= (string) stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            if (!$estado['running']) {
                $codigo = (int) $estado['exitcode'];
                break;
            }
            if ((microtime(true) - $inicio) > 8) {
                proc_terminate($proceso, 9);
                $codigo = 1;
                break;
            }
            usleep(20000);
        }
        $leido .= (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proceso);
        if ($salida !== null) {
            $salida = $leido;
        }
        return $codigo;
    }
}
