<?php

namespace App\Controllers\GestionClinica;

use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Especialidad;

// CU07 control. Mensajes: listar(), guardar(datos).
class CtrlEspecialidad
{
    public ?array $usuarioSesion;

    public string $mensaje = '';

    public ?string $campoError = null;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU07 mensaje 2 → 3: listar().
    public function listar(): array
    {
        return (new Especialidad())->listar();
    }

    // CU07 mensaje 5 → 6, 7, 8.
    public function guardar(array $datos): bool
    {
        $especialidades = new Especialidad();
        $id = (int) ($datos['id_especialidad'] ?? 0);
        $actual = null;
        foreach ($especialidades->listar() as $fila) {
            if ((int) $fila['id_especialidad'] === $id) {
                $actual = $fila;
            }
        }
        if ($id > 0 && $actual === null) {
            $this->mensaje = 'La especialidad no existe.';
            return false;
        }

        if (($datos['accion'] ?? '') === 'estado') {
            $activo = ($datos['estado'] ?? '0') === '1';
            $especialidades->cambiarEstado($id, $activo);
            $this->registrar(($activo ? 'Activó ' : 'Desactivó ') . $actual['nombre']);
            $this->mensaje = $activo ? 'Especialidad activada.' : 'Especialidad desactivada.';
            return true;
        }

        // Paso 5: normaliza el nombre (sin espacios sobrantes).
        $nombre = preg_replace('/\s+/u', ' ', trim((string) ($datos['nombre'] ?? '')));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 60) {
            return $this->invalido('nombre', 'Ingrese el nombre de la especialidad (máximo 60 caracteres).');
        }
        if (mb_strlen($descripcion) > 200) {
            return $this->invalido('descripcion', 'La descripción admite hasta 200 caracteres.');
        }

        // Mensaje 6: existeNombreNormalizado(). E1: el nombre ya existe.
        if ($especialidades->existeNombreNormalizado($nombre, $id ?: null)) {
            return $this->invalido('nombre', 'Ya existe una especialidad con ese nombre.');
        }

        $limpio = [
            'nombre'      => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'estado'      => ($datos['estado'] ?? '1') === '1',
        ];

        // Mensaje 7: insertar() / actualizar().
        if ($actual === null) {
            $especialidades->insertar($limpio);
            $this->registrar('Registró ' . $nombre);
            $this->mensaje = 'Especialidad registrada correctamente.';
        } else {
            $especialidades->actualizar($id, $limpio);
            $this->registrar('Modificó ' . $nombre);
            $this->mensaje = 'Especialidad actualizada correctamente.';
        }

        return true;
    }

    // Mensaje 8: registrar("ESPECIALIDAD").
    private function registrar(string $detalle): void
    {
        (new Bitacora())->registrar((int) ($this->usuarioSesion['id_usuario'] ?? 0), 'ESPECIALIDAD', 'Especialidades', $detalle);
    }

    private function invalido(string $campo, string $mensaje): bool
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return false;
    }
}
