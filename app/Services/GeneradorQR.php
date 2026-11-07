<?php

namespace App\Services;

// RF24 Comprobante con código QR. Generador QR (ISO/IEC 18004) sin dependencias externas:
// modo byte, nivel de corrección M, versiones 1 a 10 (hasta 213 bytes) y la máscara de menor penalización.
class GeneradorQR
{
    // Por versión: [codewords de datos, codewords de corrección por bloque, bloques [[cantidad, datos por bloque], ...]]
    private const VERSIONES = [
        1  => [16, 10, [[1, 16]]],
        2  => [28, 16, [[1, 28]]],
        3  => [44, 26, [[1, 44]]],
        4  => [64, 18, [[2, 32]]],
        5  => [86, 24, [[2, 43]]],
        6  => [108, 16, [[4, 27]]],
        7  => [124, 18, [[4, 31]]],
        8  => [154, 22, [[2, 38], [2, 39]]],
        9  => [182, 22, [[3, 36], [2, 37]]],
        10 => [216, 26, [[4, 43], [1, 44]]],
    ];

    private const ALINEACION = [
        2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private const RESTO = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0];

    private static array $exp = [];

    private static array $log = [];

    private array $m = [];      // módulos: true = oscuro

    private array $fijo = [];   // patrones de función (no se enmascaran)

    private int $n = 0;

    // SVG del QR, escalable, con zona de silencio de 4 módulos.
    public static function svg(string $texto, int $tamano = 220): string
    {
        $matriz = self::matriz($texto);
        $n = count($matriz);
        $total = $n + 8;
        $trazo = '';
        foreach ($matriz as $y => $fila) {
            foreach ($fila as $x => $oscuro) {
                if ($oscuro) {
                    $trazo .= 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" width="' . $tamano . '" height="' . $tamano
            . '" shape-rendering="crispEdges" role="img" aria-label="Código QR"><rect width="100%" height="100%" fill="#fff"/>'
            . '<path d="' . $trazo . '" fill="#000"/></svg>';
    }

    // Matriz de módulos (true = oscuro).
    public static function matriz(string $texto): array
    {
        $qr = new self();
        return $qr->codificar($texto);
    }

    private function codificar(string $texto): array
    {
        $largo = strlen($texto);
        $version = null;
        foreach (self::VERSIONES as $v => [$datos]) {
            $bitsCuenta = $v < 10 ? 8 : 16;
            if (4 + $bitsCuenta + 8 * $largo <= 8 * $datos) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new \InvalidArgumentException('Texto demasiado largo para el código QR.');
        }
        [$capacidad, $ecPorBloque, $grupos] = self::VERSIONES[$version];

        // Flujo de bits: modo byte (0100), cantidad, datos, terminador y relleno.
        $bits = '0100' . str_pad(decbin($largo), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $largo; $i++) {
            $bits .= str_pad(decbin(ord($texto[$i])), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, 8 * $capacidad - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $datos = array_map('bindec', str_split($bits, 8));
        for ($relleno = [0xEC, 0x11], $i = 0; count($datos) < $capacidad; $i++) {
            $datos[] = $relleno[$i % 2];
        }

        // Bloques, corrección Reed-Solomon y entrelazado.
        $bloques = [];
        $correcciones = [];
        $pos = 0;
        foreach ($grupos as [$cantidad, $porBloque]) {
            for ($b = 0; $b < $cantidad; $b++) {
                $bloque = array_slice($datos, $pos, $porBloque);
                $pos += $porBloque;
                $bloques[] = $bloque;
                $correcciones[] = self::reedSolomon($bloque, $ecPorBloque);
            }
        }
        $final = [];
        $maximo = max(array_map('count', $bloques));
        for ($i = 0; $i < $maximo; $i++) {
            foreach ($bloques as $bloque) {
                if (isset($bloque[$i])) {
                    $final[] = $bloque[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPorBloque; $i++) {
            foreach ($correcciones as $ec) {
                $final[] = $ec[$i];
            }
        }
        $flujo = '';
        foreach ($final as $byte) {
            $flujo .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $flujo .= str_repeat('0', self::RESTO[$version]);

        // Matriz con patrones de función y datos; se elige la máscara de menor penalización.
        $this->n = 17 + 4 * $version;
        $this->m = array_fill(0, $this->n, array_fill(0, $this->n, false));
        $this->fijo = array_fill(0, $this->n, array_fill(0, $this->n, false));
        $this->patrones($version);
        $this->colocarDatos($flujo);

        $mejor = null;
        $menor = PHP_INT_MAX;
        $base = $this->m;
        for ($mascara = 0; $mascara < 8; $mascara++) {
            $this->m = $base;
            $this->aplicarMascara($mascara);
            $this->formato($mascara);
            $penal = $this->penalizacion();
            if ($penal < $menor) {
                $menor = $penal;
                $mejor = $this->m;
            }
        }

        return $mejor;
    }

    private function patrones(int $version): void
    {
        $n = $this->n;
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $x = $fx + $dx;
                    $y = $fy + $dy;
                    if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
                        continue;
                    }
                    $dentro = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                    $oscuro = $dentro && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                    $this->poner($x, $y, $oscuro);
                }
            }
        }
        for ($i = 8; $i < $n - 8; $i++) {
            $this->poner($i, 6, $i % 2 === 0);
            $this->poner(6, $i, $i % 2 === 0);
        }
        $centros = self::ALINEACION[$version] ?? [];
        $ultimo = count($centros) - 1;
        foreach ($centros as $i => $cy) {
            foreach ($centros as $j => $cx) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $ultimo) || ($i === $ultimo && $j === 0)) {
                    continue; // se superpone con un patrón de búsqueda
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->poner($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        // Módulo oscuro y reserva del área de formato.
        $this->poner(8, $n - 8, true);
        for ($i = 0; $i < 9; $i++) {
            if (!$this->fijo[8][$i]) {
                $this->poner($i, 8, false);
            }
            if (!$this->fijo[$i][8]) {
                $this->poner(8, $i, false);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $this->poner($n - 1 - $i, 8, false);
            if (!$this->fijo[$n - 1 - $i][8]) {
                $this->poner(8, $n - 1 - $i, false);
            }
        }
        // Información de versión (v7+).
        if ($version >= 7) {
            $resto = $version;
            for ($i = 0; $i < 12; $i++) {
                $resto = ($resto << 1) ^ (($resto >> 11) * 0x1F25);
            }
            $info = ($version << 12) | $resto;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($info >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->poner($a, $b, $bit);
                $this->poner($b, $a, $bit);
            }
        }
    }

    private function colocarDatos(string $flujo): void
    {
        $n = $this->n;
        $i = 0;
        $total = strlen($flujo);
        for ($derecha = $n - 1; $derecha >= 1; $derecha -= 2) {
            if ($derecha === 6) {
                $derecha = 5;
            }
            for ($v = 0; $v < $n; $v++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $derecha - $j;
                    $subiendo = (($derecha + 1) & 2) === 0;
                    $y = $subiendo ? $n - 1 - $v : $v;
                    if (!$this->fijo[$y][$x] && $i < $total) {
                        $this->m[$y][$x] = $flujo[$i] === '1';
                        $i++;
                    }
                }
            }
        }
    }

    private function aplicarMascara(int $mascara): void
    {
        for ($y = 0; $y < $this->n; $y++) {
            for ($x = 0; $x < $this->n; $x++) {
                if ($this->fijo[$y][$x]) {
                    continue;
                }
                $invertir = match ($mascara) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invertir) {
                    $this->m[$y][$x] = !$this->m[$y][$x];
                }
            }
        }
    }

    // Formato: nivel M (00) y máscara, con BCH(15,5) y la máscara 0x5412.
    private function formato(int $mascara): void
    {
        $datos = (0b00 << 3) | $mascara;
        $resto = $datos;
        for ($i = 0; $i < 10; $i++) {
            $resto = ($resto << 1) ^ (($resto >> 9) * 0x537);
        }
        $bits = (($datos << 10) | $resto) ^ 0x5412;
        $n = $this->n;
        for ($i = 0; $i <= 5; $i++) {
            $this->m[$i][8] = (($bits >> $i) & 1) === 1;
        }
        $this->m[7][8] = (($bits >> 6) & 1) === 1;
        $this->m[8][8] = (($bits >> 7) & 1) === 1;
        $this->m[8][7] = (($bits >> 8) & 1) === 1;
        for ($i = 9; $i < 15; $i++) {
            $this->m[8][14 - $i] = (($bits >> $i) & 1) === 1;
        }
        for ($i = 0; $i < 8; $i++) {
            $this->m[8][$n - 1 - $i] = (($bits >> $i) & 1) === 1;
        }
        for ($i = 8; $i < 15; $i++) {
            $this->m[$n - 15 + $i][8] = (($bits >> $i) & 1) === 1;
        }
        $this->m[$n - 8][8] = true;
    }

    private function penalizacion(): int
    {
        $n = $this->n;
        $total = 0;
        $oscuros = 0;
        for ($k = 0; $k < 2; $k++) {
            for ($a = 0; $a < $n; $a++) {
                $racha = 1;
                $linea = '';
                for ($b = 0; $b < $n; $b++) {
                    $valor = $k === 0 ? $this->m[$a][$b] : $this->m[$b][$a];
                    $linea .= $valor ? '1' : '0';
                    if ($b > 0) {
                        $anterior = $k === 0 ? $this->m[$a][$b - 1] : $this->m[$b - 1][$a];
                        if ($valor === $anterior) {
                            $racha++;
                        } else {
                            if ($racha >= 5) {
                                $total += $racha - 2;
                            }
                            $racha = 1;
                        }
                    }
                }
                if ($racha >= 5) {
                    $total += $racha - 2;
                }
                $total += 40 * (substr_count($linea, '10111010000') + substr_count($linea, '00001011101'));
            }
        }
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->m[$y][$x]) {
                    $oscuros++;
                }
                if ($y < $n - 1 && $x < $n - 1) {
                    $c = $this->m[$y][$x];
                    if ($c === $this->m[$y][$x + 1] && $c === $this->m[$y + 1][$x] && $c === $this->m[$y + 1][$x + 1]) {
                        $total += 3;
                    }
                }
            }
        }
        $total += 10 * intdiv(abs($oscuros * 20 - $n * $n * 10), $n * $n);

        return $total;
    }

    private function poner(int $x, int $y, bool $oscuro): void
    {
        $this->m[$y][$x] = $oscuro;
        $this->fijo[$y][$x] = true;
    }

    // Reed-Solomon sobre GF(256) con el polinomio 0x11D.
    private static function reedSolomon(array $datos, int $grado): array
    {
        if (self::$exp === []) {
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                self::$exp[$i] = $x;
                self::$log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                self::$exp[$i] = self::$exp[$i - 255];
            }
        }
        $generador = [1];
        for ($i = 0; $i < $grado; $i++) {
            $nuevo = array_fill(0, count($generador) + 1, 0);
            foreach ($generador as $j => $coef) {
                $nuevo[$j] ^= $coef;
                $nuevo[$j + 1] ^= $coef === 0 ? 0 : self::$exp[self::$log[$coef] + $i];
            }
            $generador = $nuevo;
        }
        $resto = array_merge($datos, array_fill(0, $grado, 0));
        for ($i = 0, $c = count($datos); $i < $c; $i++) {
            $coef = $resto[$i];
            if ($coef === 0) {
                continue;
            }
            for ($j = 1; $j <= $grado; $j++) {
                if ($generador[$j] !== 0) {
                    $resto[$i + $j] ^= self::$exp[self::$log[$generador[$j]] + self::$log[$coef]];
                }
            }
        }

        return array_slice($resto, count($datos));
    }
}
