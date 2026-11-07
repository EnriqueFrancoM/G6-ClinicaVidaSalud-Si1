<?php

namespace App\Controllers\Reportes;

use App\Core\Session;
use App\Models\BloqueoAgenda;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\SolicitudEspera;

// CU22 control. Mensajes: calcularIndicadores(datos), validarAcceso().
// Entidades: Cita.agruparPorEstado(filtros), HorarioAtencion.obtenerCapacidad(filtros),
// BloqueoAgenda.descontarBloqueos(filtros). Cada indicador muestra su denominador; con denominador 0
// el resultado es "sin datos" (PN14: sin división por cero).
class CtrlIndicador
{
    public ?array $usuarioActual;

    public string $mensaje = '';

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    public function validarAcceso(): bool
    {
        return Session::hasPermission('REP_GENERAR');
    }

    // CU22 mensaje calcularIndicadores(datos): período, médico y especialidad.
    public function calcularIndicadores(array $datos): ?array
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para consultar indicadores.');
        }
        $filtros = [
            'desde'           => trim((string) ($datos['desde'] ?? '')),
            'hasta'           => trim((string) ($datos['hasta'] ?? '')),
            'id_medico'       => (int) ($datos['id_medico'] ?? 0),
            'id_especialidad' => (int) ($datos['id_especialidad'] ?? 0),
        ];
        // Paso 3: validar alcance (período válido).
        foreach (['desde', 'hasta'] as $campo) {
            if (\DateTime::createFromFormat('!Y-m-d', $filtros[$campo])?->format('Y-m-d') !== $filtros[$campo]) {
                return $this->error('Ingrese un período válido (desde y hasta).');
            }
        }
        if ($filtros['desde'] > $filtros['hasta']) {
            return $this->error('La fecha "desde" debe ser anterior o igual a la fecha "hasta".');
        }

        // Paso 4: agrupar citas y horarios.
        $grupos = (new Cita())->agruparPorEstado($filtros);
        $capacidad = $this->porMedico((new HorarioAtencion())->obtenerCapacidad($filtros));
        $bloqueos = $this->porMedico((new BloqueoAgenda())->descontarBloqueos($filtros));
        $espera = (new SolicitudEspera())->contarPorEstado($filtros);

        $estados = array_fill_keys(array_keys(Cita::ESTADOS), 0);
        $medicos = [];
        $especialidades = [];
        foreach ($grupos as $g) {
            $total = (int) $g['total'];
            $estados[$g['estado']] += $total;
            $medicos[(int) $g['id_medico']]['nombre'] = $g['medico'];
            $medicos[(int) $g['id_medico']]['ocupadas'] = ($medicos[(int) $g['id_medico']]['ocupadas'] ?? 0) + ($g['estado'] !== 'CANCELADA' ? $total : 0);
            $especialidades[$g['especialidad']] = ($especialidades[$g['especialidad']] ?? 0) + $total;
        }

        // Paso 5: indicadores con denominadores explícitos.
        $totalCitas = array_sum($estados);
        $ocupadas = $totalCitas - $estados['CANCELADA'];
        $cuposNetos = max(0, array_sum($capacidad) - array_sum($bloqueos));

        $porMedico = [];
        foreach ($capacidad + array_fill_keys(array_keys($medicos), 0) as $id => $_) {
            $netos = max(0, ($capacidad[$id] ?? 0) - ($bloqueos[$id] ?? 0));
            $usadas = $medicos[$id]['ocupadas'] ?? 0;
            $porMedico[] = [
                'medico'    => $medicos[$id]['nombre'] ?? $this->nombreMedico($id),
                'ocupadas'  => $usadas,
                'cupos'     => $netos,
                'indicador' => $this->tasa($usadas, $netos),
            ];
        }
        usort($porMedico, fn ($a, $b) => ($b['indicador'] ?? -1) <=> ($a['indicador'] ?? -1));

        arsort($especialidades);
        $demanda = [];
        foreach ($especialidades as $nombre => $total) {
            $demanda[] = ['especialidad' => $nombre, 'citas' => $total, 'indicador' => $this->tasa($total, $totalCitas)];
        }

        $resultado = [
            'estados'     => $estados,
            'total'       => $totalCitas,
            'inasistencia' => [
                'valor' => $this->tasa($estados['INASISTENCIA'], $estados['ATENDIDA'] + $estados['INASISTENCIA']),
                'numerador' => $estados['INASISTENCIA'], 'denominador' => $estados['ATENDIDA'] + $estados['INASISTENCIA'],
            ],
            'cancelacion' => [
                'valor' => $this->tasa($estados['CANCELADA'], $totalCitas),
                'numerador' => $estados['CANCELADA'], 'denominador' => $totalCitas,
            ],
            'ocupacion'   => [
                'valor' => $this->tasa($ocupadas, $cuposNetos),
                'numerador' => $ocupadas, 'denominador' => $cuposNetos,
                'capacidad' => array_sum($capacidad), 'bloqueados' => array_sum($bloqueos),
            ],
            'espera'      => [
                'valor' => $this->tasa($espera['asignadas'], $espera['total']),
                'numerador' => $espera['asignadas'], 'denominador' => $espera['total'], 'vigentes' => $espera['vigentes'],
            ],
            'porMedico'   => $porMedico,
            'demanda'     => $demanda,
        ];
        if ($totalCitas === 0 && $cuposNetos === 0) {
            $this->mensaje = 'Sin datos: el período no tiene citas ni cupos programados.';
        }

        return $resultado;
    }

    // Porcentaje con una cifra decimal, o null si el denominador es 0.
    private function tasa(int $numerador, int $denominador): ?float
    {
        return $denominador > 0 ? round($numerador * 100 / $denominador, 1) : null;
    }

    private function porMedico(array $filas): array
    {
        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['id_medico']] = (int) $f['cupos'];
        }
        return $mapa;
    }

    private function nombreMedico(int $idMedico): string
    {
        return (new \App\Models\Medico())->validarMedico($idMedico)['nombre'] ?? ('Médico #' . $idMedico);
    }

    private function error(string $mensaje): null
    {
        $this->mensaje = $mensaje;
        return null;
    }
}
