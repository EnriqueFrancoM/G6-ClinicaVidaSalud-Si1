<?php

namespace App\Controllers\Reportes;

use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Services\ExportadorReporte;

// CU19 control. Mensajes: generarReporte(datos), validarAcceso().
// Entidades: Cita.filtrarCitas(filtros), Paciente.contarPacientes(período), ExportadorReporte.crearArchivo(resultado, formato).
class CtrlReporte
{
    // RF19: reportes disponibles.
    public const TIPOS = [
        'citas'     => 'Citas por fecha, médico, especialidad y estado',
        'totales'   => 'Totales de atendidas, canceladas e inasistencias',
        'pacientes' => 'Pacientes registrados',
        'agenda'    => 'Agenda diaria por médico',
    ];

    // Período máximo de un reporte.
    private const DIAS_MAXIMOS = 366;

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

    // CU19 mensaje generarReporte(datos): tipo, desde, hasta, id_medico, id_especialidad, estado y formato.
    // Sin formato devuelve la vista previa; con formato agrega el archivo (paso 6).
    public function generarReporte(array $datos): ?array
    {
        if (!$this->validarAcceso()) {
            return $this->error('No tiene permiso para generar reportes.');
        }

        // Paso 3: validar rango y filtros.
        $tipo = isset(self::TIPOS[$datos['tipo'] ?? '']) ? $datos['tipo'] : 'citas';
        $filtros = [
            'desde'           => trim((string) ($datos['desde'] ?? '')),
            'hasta'           => trim((string) ($datos['hasta'] ?? '')),
            'id_medico'       => (int) ($datos['id_medico'] ?? 0),
            'id_especialidad' => (int) ($datos['id_especialidad'] ?? 0),
            'estado'          => strtoupper(trim((string) ($datos['estado'] ?? ''))),
        ];
        foreach (['desde', 'hasta'] as $campo) {
            if (!\App\Core\App::fechaIso($filtros[$campo])) {
                return $this->error('Ingrese un período válido (desde y hasta).');
            }
        }
        if ($filtros['desde'] > $filtros['hasta']) {
            return $this->error('La fecha "desde" debe ser anterior o igual a la fecha "hasta".');
        }
        if ((strtotime($filtros['hasta']) - strtotime($filtros['desde'])) / 86400 > self::DIAS_MAXIMOS) {
            return $this->error('El período de un reporte no puede superar un año.');
        }
        if ($filtros['estado'] !== '' && !isset(Cita::ESTADOS[$filtros['estado']])) {
            return $this->error('Seleccione un estado válido.');
        }
        if ($tipo === 'agenda' && $filtros['id_medico'] <= 0) {
            return $this->error('La agenda diaria requiere elegir un médico.');
        }

        // Pasos 4 y 5: consultar el conjunto filtrado y previsualizar.
        $resultado = match ($tipo) {
            'totales'   => $this->totales($filtros),
            'pacientes' => $this->pacientes($filtros),
            'agenda'    => $this->agenda($filtros),
            default     => $this->citas($filtros),
        };
        $resultado['tipo'] = $tipo;
        $resultado['subtitulo'] = $this->subtitulo($tipo, $filtros);

        // Paso 6: exportar al formato elegido (crearArchivo(resultado, formato)).
        $formato = (string) ($datos['formato'] ?? '');
        if ($formato !== '') {
            if (!isset(ExportadorReporte::FORMATOS[$formato])) {
                return $this->error('Formato no disponible.');
            }
            $resultado['archivo'] = (new ExportadorReporte())->crearArchivo($resultado, $formato);
            (new Bitacora())->registrar((int) $this->usuarioActual['id_usuario'], 'EXPORTAR', 'Reportes',
                self::TIPOS[$tipo] . ' (' . strtoupper($formato) . ')');
        }
        if ($resultado['filas'] === []) {
            $this->mensaje = 'Sin resultados para los filtros elegidos.';
        }

        return $resultado;
    }

    private function citas(array $f): array
    {
        $filas = [];
        foreach ((new Cita())->filtrarCitas($f) as $c) {
            $filas[] = [$c['codigo'], date('d/m/Y', strtotime($c['fecha'])), $c['hora_inicio'], $c['paciente'], $c['medico'],
                $c['especialidad'], Cita::ESTADOS[$c['estado']] ?? $c['estado']];
        }

        return [
            'titulo'   => 'Reporte de citas',
            'columnas' => ['Código', 'Fecha', 'Hora', 'Paciente', 'Médico', 'Especialidad', 'Estado'],
            'filas'    => $filas,
            'resumen'  => ['Total de citas' => count($filas)],
        ];
    }

    // Totales del período por estado, médico y especialidad.
    private function totales(array $f): array
    {
        $grupos = [];
        $porEstado = array_fill_keys(array_keys(Cita::ESTADOS), 0);
        foreach ((new Cita())->filtrarCitas($f) as $c) {
            foreach (['Médico: ' . $c['medico'], 'Especialidad: ' . $c['especialidad']] as $grupo) {
                $grupos[$grupo] ??= array_fill_keys(array_keys(Cita::ESTADOS), 0);
                $grupos[$grupo][$c['estado']]++;
            }
            $porEstado[$c['estado']]++;
        }
        ksort($grupos);

        $filas = [];
        foreach ($grupos as $grupo => $conteo) {
            $filas[] = array_merge([$grupo], array_values($conteo), [array_sum($conteo)]);
        }
        $resumen = [];
        foreach ($porEstado as $estado => $total) {
            $resumen[Cita::ESTADOS[$estado] . 's'] = $total;
        }
        $resumen['Total del período'] = array_sum($porEstado);

        return [
            'titulo'   => 'Totales de citas del período',
            'columnas' => array_merge(['Grupo'], array_values(Cita::ESTADOS), ['Total']),
            'filas'    => $filas,
            'resumen'  => $resumen,
        ];
    }

    // Mensaje contarPacientes(período).
    private function pacientes(array $f): array
    {
        $pacientes = new Paciente();
        $filas = [];
        foreach ($pacientes->registradosEn($f['desde'], $f['hasta']) as $p) {
            $filas[] = [$p['codigo'], (string) $p['ci'], $p['paciente'], date('d/m/Y', strtotime($p['fecha_nacimiento'])),
                $p['telefono'], (string) $p['email'], date('d/m/Y', strtotime($p['fecha_registro'])),
                in_array($p['estado'], [true, 't', '1', 1], true) ? 'Activo' : 'Inactivo'];
        }
        $conteo = $pacientes->contarPacientes($f['desde'], $f['hasta']);

        return [
            'titulo'   => 'Pacientes registrados',
            'columnas' => ['Código', 'CI', 'Paciente', 'Nacimiento', 'Teléfono', 'Correo', 'Registro', 'Estado'],
            'filas'    => $filas,
            'resumen'  => [
                'Registrados en el período' => (int) $conteo['registrados'],
                'Pacientes activos'         => (int) $conteo['activos'],
                'Total de pacientes'        => (int) $conteo['total'],
            ],
        ];
    }

    // Agenda diaria por médico: cada día del período, en orden de hora.
    private function agenda(array $f): array
    {
        $filas = [];
        foreach ((new Cita())->filtrarCitas($f) as $c) {
            $filas[] = [date('d/m/Y', strtotime($c['fecha'])), $c['hora_inicio'] . '–' . $c['hora_fin'], $c['codigo'], $c['paciente'],
                (string) $c['paciente_telefono'], (string) $c['motivo'], Cita::ESTADOS[$c['estado']] ?? $c['estado']];
        }
        $medico = (new Medico())->validarMedico($f['id_medico']);

        return [
            'titulo'   => 'Agenda diaria – ' . ($medico['nombre'] ?? ''),
            'columnas' => ['Fecha', 'Horario', 'Código', 'Paciente', 'Teléfono', 'Motivo', 'Estado'],
            'filas'    => $filas,
            'resumen'  => ['Citas en la agenda' => count($filas)],
        ];
    }

    private function subtitulo(string $tipo, array $f): string
    {
        $partes = ['Período: ' . date('d/m/Y', strtotime($f['desde'])) . ' al ' . date('d/m/Y', strtotime($f['hasta']))];
        if ($tipo !== 'pacientes') {
            if ($f['id_medico'] > 0 && ($m = (new Medico())->validarMedico($f['id_medico']))) {
                $partes[] = 'Médico: ' . $m['nombre'];
            }
            if ($f['id_especialidad'] > 0 && ($e = (new Especialidad())->buscarPorId($f['id_especialidad']))) {
                $partes[] = 'Especialidad: ' . $e['nombre'];
            }
            if ($f['estado'] !== '') {
                $partes[] = 'Estado: ' . Cita::ESTADOS[$f['estado']];
            }
        }
        $partes[] = 'Generado: ' . date('d/m/Y H:i');

        return implode(' · ', $partes);
    }

    private function error(string $mensaje): null
    {
        $this->mensaje = $mensaje;
        return null;
    }
}
