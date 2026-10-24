<?php

namespace App\Controllers\GestionClinica;

use App\Controllers\Seguridad\CtrlUsuario;
use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\MedicoEspecialidad;
use App\Models\Rol;
use App\Models\Usuario;

// CU08 control. Mensajes: listarMedicos(filtro), guardarMedico(datos).
class CtrlMedico
{
    public ?array $usuarioSesion;

    public string $mensaje = '';

    public ?string $campoError = null;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU08 mensaje 2 → 3: buscar(filtro), 4: listarActivas().
    public function listarMedicos(array $filtro): array
    {
        return [
            'medicos'        => (new Medico())->buscar($filtro),
            'especialidades' => (new Especialidad())->listarActivas(),
            'siguiente'      => (new Medico())->siguienteCodigo(),
        ];
    }

    // CU08 mensaje 6 → 7, 8, 9, 10.
    public function guardarMedico(array $datos): bool
    {
        $medicos = new Medico();
        $id = (int) ($datos['id_medico'] ?? 0);
        $actual = null;
        if ($id > 0) {
            foreach ($medicos->buscar() as $fila) {
                if ((int) $fila['id_medico'] === $id) {
                    $actual = $fila;
                }
            }
            if ($actual === null) {
                $this->mensaje = 'El médico no existe.';
                return false;
            }
        }

        // E3: activar o desactivar (no se elimina).
        if (($datos['accion'] ?? '') === 'estado') {
            $activo = ($datos['estado'] ?? '0') === '1';
            $medicos->cambiarEstado($id, $activo);
            $this->registrar(($activo ? 'Activó ' : 'Desactivó ') . $actual['codigo']);
            $this->mensaje = $activo ? 'Médico activado.' : 'Médico desactivado; ya no podrá recibir nuevas citas.';
            return true;
        }

        $limpio = $this->validar($datos);
        if ($limpio === null) {
            return false;
        }

        // Mensaje 7: existeRegistro(). E1: n.º de registro profesional repetido.
        if ($medicos->existeRegistro($limpio['nro_registro_prof'], $id ?: null)) {
            $this->invalido('nro_registro_prof', 'El n.º de registro profesional ya está registrado.');
            return false;
        }

        // Paso 7: médico y especialidades en una transacción.
        $relacion = new MedicoEspecialidad();
        $db = Database::connection();
        $db->beginTransaction();
        try {
            if ($actual === null) {
                $id = $medicos->insertar($limpio);
                $anteriores = [];
            } else {
                $medicos->actualizar($id, $limpio);
                $anteriores = $actual['ids_especialidad'];
            }
            // Mensaje 9: asignar() / quitar().
            foreach (array_diff($limpio['especialidades'], $anteriores) as $idEspecialidad) {
                $relacion->asignar($id, $idEspecialidad);
            }
            foreach (array_diff($anteriores, $limpio['especialidades']) as $idEspecialidad) {
                $relacion->quitar($id, $idEspecialidad);
            }
            $codigo = $actual['codigo'] ?? $this->codigoDe($id);
            $this->registrar(($actual === null ? 'Registró ' : 'Modificó ') . $codigo);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->mensaje = $actual === null ? "Médico $codigo registrado correctamente." : "Médico $codigo actualizado correctamente.";

        // Paso 8 (opcional): crea la cuenta de usuario con rol Médico (incluye CU03).
        if ($actual === null && ($datos['crear_cuenta'] ?? '0') === '1') {
            $this->mensaje .= ' ' . $this->crearCuenta($id, $limpio);
        }

        return true;
    }

    private function crearCuenta(int $idMedico, array $medico): string
    {
        $idRol = null;
        foreach ((new Rol())->listar() as $rol) {
            if ($rol['nombre'] === 'Médico') {
                $idRol = (int) $rol['id_rol'];
            }
        }
        if ($idRol === null || $medico['email'] === null) {
            return 'No se creó la cuenta de usuario: ' . ($idRol === null ? 'no existe el rol Médico.' : 'falta el correo electrónico.');
        }

        $cuenta = new CtrlUsuario();
        $ok = $cuenta->guardarUsuario([
            'accion'     => 'guardar',
            'id_usuario' => 0,
            'username'   => $this->sugerirUsuario($medico),
            'email'      => $medico['email'],
            'id_rol'     => (string) $idRol,
            'estado'     => '1',
            'vinculo'    => 'medico:' . $idMedico,
        ]);

        return $ok ? $cuenta->mensaje : 'No se creó la cuenta de usuario: ' . $cuenta->mensaje;
    }

    // "Daniela Montaño Cuéllar" → "dr.dmontano" (o dr.dmontano2 si ya existe).
    private function sugerirUsuario(array $medico): string
    {
        $plano = fn (string $t) => strtolower(preg_replace('/[^A-Za-z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: ''));
        $apellido = explode(' ', trim($medico['apellidos']))[0];
        $base = 'dr.' . substr($plano($medico['nombres']), 0, 1) . $plano($apellido);
        $usuarios = new Usuario();
        $candidato = $base;
        for ($n = 2; $usuarios->existeUsuarioOEmail($candidato, '') === 'username'; $n++) {
            $candidato = $base . $n;
        }

        return $candidato;
    }

    // Paso 6: campos obligatorios. E2 (paso 5): al menos una especialidad activa.
    private function validar(array $datos): ?array
    {
        $texto = fn (string $campo) => preg_replace('/\s+/u', ' ', trim((string) ($datos[$campo] ?? '')));
        $nro = strtoupper($texto('nro_registro_prof'));
        $nombres = $texto('nombres');
        $apellidos = $texto('apellidos');
        $telefono = $texto('telefono');
        $email = $texto('email');

        if ($nro === '' || strlen($nro) > 20) {
            return $this->invalido('nro_registro_prof', 'Ingrese el n.º de registro profesional (máximo 20 caracteres).');
        }
        if ($nombres === '' || mb_strlen($nombres) > 60) {
            return $this->invalido('nombres', 'Ingrese los nombres (máximo 60 caracteres).');
        }
        if ($apellidos === '' || mb_strlen($apellidos) > 60) {
            return $this->invalido('apellidos', 'Ingrese los apellidos (máximo 60 caracteres).');
        }
        if (!preg_match('/^[0-9+\- ]{7,15}$/', $telefono)) {
            return $this->invalido('telefono', 'Ingrese un teléfono válido (7 a 15 dígitos).');
        }
        if ($email !== '' && (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return $this->invalido('email', 'Ingrese un correo electrónico válido.');
        }

        $activas = array_map('intval', array_column((new Especialidad())->listarActivas(), 'id_especialidad'));
        $elegidas = array_values(array_intersect(array_map('intval', (array) ($datos['especialidades'] ?? [])), $activas));
        if ($elegidas === []) {
            return $this->invalido('especialidades', 'Seleccione al menos una especialidad.');
        }

        return [
            'nro_registro_prof' => $nro,
            'nombres'           => $nombres,
            'apellidos'         => $apellidos,
            'telefono'          => $telefono,
            'email'             => $email !== '' ? $email : null,
            'estado'            => ($datos['estado'] ?? '1') === '1',
            'especialidades'    => $elegidas,
        ];
    }

    private function codigoDe(int $idMedico): string
    {
        foreach ((new Medico())->buscar() as $fila) {
            if ((int) $fila['id_medico'] === $idMedico) {
                return $fila['codigo'];
            }
        }
        return '';
    }

    // Mensaje 10: registrar("MEDICO").
    private function registrar(string $detalle): void
    {
        (new Bitacora())->registrar((int) ($this->usuarioSesion['id_usuario'] ?? 0), 'MEDICO', 'Médicos', $detalle);
    }

    private function invalido(string $campo, string $mensaje): null
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return null;
    }
}
