<?php

namespace App\Models;

use App\Core\Model;

// Entidad Bitacora. CU01: registrar("LOGIN", ip). CU02: registrar("LOGOUT", ip).
// CU06: filtrar(filtros), exportarCSV(filtros).
class Bitacora extends Model
{
    public function registrar(?int $idUsuario, string $accion, string $modulo, ?string $detalle = null): void
    {
        $this->execute(
            'INSERT INTO bitacora (id_usuario, accion, modulo, detalle, ip)
             VALUES (:id_usuario, :accion, :modulo, :detalle, :ip)',
            [
                'id_usuario' => $idUsuario,
                'accion'     => $accion,
                'modulo'     => $modulo,
                'detalle'    => $detalle,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    // CU06 mensaje 3. Filtros: id_usuario, modulo, desde, hasta (Y-m-d). Más reciente primero.
    // Con $porPagina = 0 devuelve todos los registros (para exportar).
    public function filtrar(array $filtros, int $pagina = 1, int $porPagina = 20): array
    {
        [$where, $params] = $this->condiciones($filtros);

        $total = (int) $this->fetch("SELECT COUNT(*) AS total FROM bitacora $where", $params)['total'];

        $sql = "SELECT id_bitacora, id_usuario, fecha_hora, accion, modulo, detalle, ip
                  FROM bitacora $where
              ORDER BY fecha_hora DESC, id_bitacora DESC";
        if ($porPagina > 0) {
            $sql .= ' LIMIT ' . $porPagina . ' OFFSET ' . (max(1, $pagina) - 1) * $porPagina;
        }

        return [
            'registros' => $this->fetchAll($sql, $params),
            'total'     => $total,
            'modulos'   => array_column($this->fetchAll('SELECT DISTINCT modulo FROM bitacora ORDER BY modulo'), 'modulo'),
        ];
    }

    // CU06 paso 5: genera el CSV de los registros ya filtrados (con el nombre de usuario resuelto).
    public function exportarCSV(array $registros): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien las tildes
        fputcsv($salida, ['Fecha y hora', 'Usuario', 'Módulo', 'Acción', 'Detalle', 'IP'], ';');
        foreach ($registros as $fila) {
            fputcsv($salida, [
                $fila['fecha_hora'],
                $fila['usuario'],
                $fila['modulo'],
                $fila['accion'],
                $fila['detalle'],
                $fila['ip'],
            ], ';');
        }
        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv;
    }

    private function condiciones(array $filtros): array
    {
        $where = [];
        $params = [];
        if (!empty($filtros['id_usuario'])) {
            $where[] = 'id_usuario = :id_usuario';
            $params['id_usuario'] = (int) $filtros['id_usuario'];
        }
        if (!empty($filtros['modulo'])) {
            $where[] = 'modulo = :modulo';
            $params['modulo'] = $filtros['modulo'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'fecha_hora >= CAST(:desde AS DATE)';
            $params['desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = "fecha_hora < CAST(:hasta AS DATE) + INTERVAL '1 day'";
            $params['hasta'] = $filtros['hasta'];
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }
}
