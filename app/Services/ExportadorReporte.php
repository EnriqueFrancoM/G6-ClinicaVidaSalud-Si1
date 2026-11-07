<?php

namespace App\Services;

// CU19 ExportadorReporte. Mensaje: crearArchivo(resultado, formato).
// Genera ReporteCitas.pdf o ReporteCitas.xlsx (nombres del documento, 7.2) sin dependencias externas:
// el PDF se escribe con las fuentes estándar Helvetica y el XLSX es un ZIP con SpreadsheetML.
class ExportadorReporte
{
    public const FORMATOS = ['pdf' => 'PDF', 'xlsx' => 'Excel (XLSX)'];

    // resultado: titulo, subtitulo, columnas[], filas[][], resumen [etiqueta => valor].
    // Devuelve nombre, tipo MIME y contenido del archivo.
    public function crearArchivo(array $resultado, string $formato): array
    {
        return match ($formato) {
            'pdf'  => ['nombre' => 'ReporteCitas.pdf', 'tipo' => 'application/pdf', 'contenido' => $this->pdf($resultado)],
            'xlsx' => ['nombre' => 'ReporteCitas.xlsx',
                'tipo' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'contenido' => $this->xlsx($resultado)],
            default => throw new \InvalidArgumentException('Formato no disponible.'),
        };
    }

    // ---------- PDF (A4 horizontal) ----------
    private function pdf(array $r): string
    {
        $ancho = 842;
        $alto = 595;
        $margen = 36;
        $columnas = $r['columnas'];
        $filas = $r['filas'];

        // Ancho de cada columna proporcional al texto más largo (con límites).
        $largos = [];
        foreach ($columnas as $i => $titulo) {
            $largo = mb_strlen($titulo);
            foreach ($filas as $fila) {
                $largo = max($largo, mb_strlen((string) ($fila[$i] ?? '')));
            }
            $largos[$i] = min(max($largo, 4), 40);
        }
        $util = $ancho - 2 * $margen;
        $anchos = array_map(fn ($l) => $util * $l / array_sum($largos), $largos);

        $paginas = [];
        $lineas = [];
        $y = 0;
        $nueva = function () use (&$lineas, &$y, &$paginas, $alto, $margen, $r, $columnas, $anchos): void {
            if ($lineas !== []) {
                $paginas[] = $lineas;
            }
            $lineas = [];
            $y = $alto - $margen;
            $lineas[] = $this->texto($margen, $y, $r['titulo'], 14, true);
            $y -= 16;
            if (!empty($r['subtitulo'])) {
                $lineas[] = $this->texto($margen, $y, $r['subtitulo'], 9);
                $y -= 18;
            }
            $x = $margen;
            foreach ($columnas as $i => $titulo) {
                $lineas[] = $this->texto($x + 2, $y, $this->recortar($titulo, $anchos[$i], 8), 8, true);
                $x += $anchos[$i];
            }
            $lineas[] = sprintf('0.6 w %.1F %.1F m %.1F %.1F l S', $margen, $y - 4, $margen + array_sum($anchos), $y - 4);
            $y -= 15;
        };
        $nueva();

        foreach ($filas as $fila) {
            if ($y < $margen + 20) {
                $nueva();
            }
            $x = $margen;
            foreach ($columnas as $i => $_) {
                $lineas[] = $this->texto($x + 2, $y, $this->recortar((string) ($fila[$i] ?? ''), $anchos[$i], 8), 8);
                $x += $anchos[$i];
            }
            $y -= 13;
        }
        if ($filas === []) {
            $lineas[] = $this->texto($margen, $y, 'Sin resultados para los filtros elegidos.', 9);
            $y -= 14;
        }
        foreach ($r['resumen'] ?? [] as $etiqueta => $valor) {
            if ($y < $margen + 20) {
                $nueva();
            }
            $y -= 4;
            $lineas[] = $this->texto($margen, $y, $etiqueta . ': ' . $valor, 9, true);
            $y -= 10;
        }
        $paginas[] = $lineas;

        // Objetos: 1 catálogo, 2 páginas, 3 Helvetica, 4 Helvetica-Bold, luego página + contenido por página.
        $objetos = [];
        $kids = [];
        $total = count($paginas);
        foreach ($paginas as $n => $contenido) {
            $idPagina = 5 + $n * 2;
            $kids[] = "$idPagina 0 R";
            $contenido[] = $this->texto($ancho - $margen - 60, 20, 'Página ' . ($n + 1) . ' de ' . $total, 7);
            $flujo = implode("\n", $contenido);
            $objetos[$idPagina] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $ancho $alto] "
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($idPagina + 1) . ' 0 R >>';
            $objetos[$idPagina + 1] = '<< /Length ' . strlen($flujo) . " >>\nstream\n$flujo\nendstream";
        }
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count $total >>";
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posiciones = [];
        foreach ($objetos as $id => $cuerpo) {
            $posiciones[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$cuerpo\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
        foreach ($posiciones as $pos) {
            $pdf .= sprintf("%010d 00000 n \n", $pos);
        }
        $pdf .= 'trailer << /Size ' . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";

        return $pdf;
    }

    private function texto(float $x, float $y, string $texto, int $tamano, bool $negrita = false): string
    {
        $cp1252 = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        $escapado = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $cp1252);

        return sprintf('BT /%s %d Tf %.1F %.1F Td (%s) Tj ET', $negrita ? 'F2' : 'F1', $tamano, $x, $y, $escapado);
    }

    // Recorta el texto al ancho de la columna (Helvetica: ~0.5 del tamaño por carácter).
    private function recortar(string $texto, float $ancho, int $tamano): string
    {
        $maximo = max(3, (int) floor(($ancho - 4) / ($tamano * 0.5)));
        return mb_strlen($texto) > $maximo ? mb_substr($texto, 0, $maximo - 1) . '…' : $texto;
    }

    // ---------- XLSX ----------
    private function xlsx(array $r): string
    {
        $filas = [[$r['titulo']]];
        if (!empty($r['subtitulo'])) {
            $filas[] = [$r['subtitulo']];
        }
        $filas[] = [];
        $encabezado = count($filas) + 1;
        $filas[] = $r['columnas'];
        foreach ($r['filas'] as $fila) {
            $filas[] = $fila;
        }
        if (!empty($r['resumen'])) {
            $filas[] = [];
            foreach ($r['resumen'] as $etiqueta => $valor) {
                $filas[] = [$etiqueta, $valor];
            }
        }

        $hoja = '';
        foreach ($filas as $n => $fila) {
            $numero = $n + 1;
            $celdas = '';
            foreach (array_values($fila) as $i => $valor) {
                $ref = $this->columnaExcel($i) . $numero;
                $estilo = ($numero === 1 || $numero === $encabezado) ? ' s="1"' : '';
                if (is_int($valor) || is_float($valor)) {
                    $celdas .= "<c r=\"$ref\"$estilo><v>$valor</v></c>";
                } else {
                    $celdas .= "<c r=\"$ref\" t=\"inlineStr\"$estilo><is><t xml:space=\"preserve\">"
                        . htmlspecialchars((string) $valor, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
                }
            }
            $hoja .= "<row r=\"$numero\">$celdas</row>";
        }
        $anchos = '';
        foreach ($r['columnas'] as $i => $_) {
            $anchos .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="20" customWidth="1"/>';
        }

        $archivos = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
                . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                . '</styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . "<cols>$anchos</cols><sheetData>$hoja</sheetData></worksheet>",
        ];

        return $this->zip($archivos);
    }

    private function columnaExcel(int $indice): string
    {
        $letras = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26) . $letras;
        }
        return $letras;
    }

    // ZIP sin compresión (método "stored"): suficiente para el XLSX y no requiere la extensión zip.
    private function zip(array $archivos): string
    {
        $datos = '';
        $central = '';
        $hora = getdate();
        $dosHora = ($hora['hours'] << 11) | ($hora['minutes'] << 5) | intdiv($hora['seconds'], 2);
        $dosFecha = (($hora['year'] - 1980) << 9) | ($hora['mon'] << 5) | $hora['mday'];

        foreach ($archivos as $nombre => $contenido) {
            $crc = crc32($contenido);
            $largo = strlen($contenido);
            $posicion = strlen($datos);
            $datos .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dosHora, $dosFecha, $crc, $largo, $largo, strlen($nombre), 0)
                . $nombre . $contenido;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $dosHora, $dosFecha, $crc, $largo, $largo,
                strlen($nombre), 0, 0, 0, 0, 0, $posicion) . $nombre;
        }

        return $datos . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($archivos), count($archivos), strlen($central), strlen($datos), 0);
    }
}
