<?php

namespace App\Controllers\Seguridad;

use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Usuario;

// CU06 control. Mensajes: consultar(filtros), exportarCSV(filtros).
class CtrlBitacora
{
    public const POR_PAGINA = 20;

    public ?array $usuarioSesion;

    public string $mensaje = '';

    public ?string $campoError = null;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU06 mensaje 2 → 3: filtrar(filtros), 5: obtenerNombre(id_usuario).
    public function consultar(array $filtros, int $pagina = 1): ?array
    {
        if (!$this->validar($filtros)) {
            return null;
        }

        $resultado = (new Bitacora())->filtrar($filtros, $pagina, self::POR_PAGINA);
        $resultado['registros'] = $this->conNombres($resultado['registros']);
        $resultado['usuarios'] = (new Usuario())->listar();

        return $resultado;
    }

    // CU06 mensaje 8: el CSV contiene todos los registros filtrados, no solo la página visible.
    public function exportarCSV(array $filtros): ?string
    {
        if (!$this->validar($filtros)) {
            return null;
        }

        $bitacora = new Bitacora();
        $registros = $this->conNombres($bitacora->filtrar($filtros, 1, 0)['registros']);

        return $bitacora->exportarCSV($registros);
    }

    // E2 (paso 3): fechas con formato válido y "desde" no posterior a "hasta".
    private function validar(array $filtros): bool
    {
        foreach (['desde', 'hasta'] as $campo) {
            $valor = (string) ($filtros[$campo] ?? '');
            if ($valor !== '' && \DateTime::createFromFormat('!Y-m-d', $valor)?->format('Y-m-d') !== $valor) {
                $this->campoError = $campo;
                $this->mensaje = 'Ingrese una fecha válida.';
                return false;
            }
        }
        if (!empty($filtros['desde']) && !empty($filtros['hasta']) && $filtros['desde'] > $filtros['hasta']) {
            $this->campoError = 'desde';
            $this->mensaje = 'La fecha "desde" no puede ser posterior a la fecha "hasta".';
            return false;
        }

        return true;
    }

    private function conNombres(array $registros): array
    {
        $usuarios = new Usuario();
        $nombres = [];
        foreach ($registros as &$fila) {
            $id = $fila['id_usuario'];
            if ($id === null) {
                $fila['usuario'] = '—';
                continue;
            }
            $nombres[$id] ??= $usuarios->obtenerNombre((int) $id) ?? '—';
            $fila['usuario'] = $nombres[$id];
        }

        return $registros;
    }
}
