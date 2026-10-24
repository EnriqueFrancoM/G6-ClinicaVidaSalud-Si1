<?php

namespace App\Controllers\GestionClinica;

use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\ContactoEmergencia;
use App\Models\Paciente;

// CU10 control. Mensajes: buscar(criterio), guardarPaciente(datos).
class CtrlPaciente
{
    public const SEXOS = ['F' => 'Femenino', 'M' => 'Masculino', 'O' => 'Otro'];

    public ?array $usuarioSesion;

    public string $mensaje = '';

    public ?string $campoError = null;

    // E1: paciente que ya tiene el CI ingresado (para ofrecer abrir su ficha).
    public ?array $existente = null;

    public int $idPaciente = 0;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU10 mensaje 2 → 3: buscar().
    public function buscar(array $criterio): array
    {
        return (new Paciente())->buscar($criterio);
    }

    // CU10 paso 9 / mensaje 9: ficha con historialPorPaciente().
    public function obtenerFicha(int $idPaciente): ?array
    {
        $fila = (new Paciente())->buscar(['id_paciente' => $idPaciente, 'estado' => null])[0] ?? null;
        if ($fila === null) {
            return null;
        }
        $fila['historial'] = (new Cita())->historialPorPaciente($idPaciente);

        return $fila;
    }

    // CU10 mensaje 5 → 6, 7, 8, 10.
    public function guardarPaciente(array $datos): bool
    {
        $pacientes = new Paciente();
        $this->idPaciente = (int) ($datos['id_paciente'] ?? 0);
        $actual = null;
        if ($this->idPaciente > 0) {
            $actual = $pacientes->buscar(['id_paciente' => $this->idPaciente, 'estado' => null])[0] ?? null;
            if ($actual === null) {
                $this->mensaje = 'El paciente no existe.';
                return false;
            }
        }

        // E3: desactivar (no se elimina).
        if (($datos['accion'] ?? '') === 'desactivar') {
            $pacientes->desactivar($this->idPaciente);
            $this->registrar('Desactivó ' . $actual['codigo']);
            $this->mensaje = 'Paciente desactivado; su historial se conserva.';
            return true;
        }

        $limpio = $this->validar($datos);
        if ($limpio === null) {
            return false;
        }

        // Mensaje 6: existeCI(). E1: el CI ya está registrado.
        $this->existente = $pacientes->existeCI($limpio['ci'], $this->idPaciente ?: null);
        if ($this->existente !== null) {
            $this->campoError = 'ci';
            $this->mensaje = 'Ya existe un paciente registrado con el CI ' . $limpio['ci'] . '.';
            return false;
        }

        // Paso 8: paciente y contacto de emergencia en una transacción.
        $db = Database::connection();
        $db->beginTransaction();
        try {
            if ($actual === null) {
                $nuevo = $pacientes->insertar($limpio);
                $this->idPaciente = $nuevo['id_paciente'];
                $codigo = $nuevo['codigo'];
            } else {
                $pacientes->actualizar($this->idPaciente, $limpio);
                $codigo = $actual['codigo'];
            }
            // Mensaje 8: guardar(contacto).
            (new ContactoEmergencia())->guardar($this->idPaciente, $limpio['contacto']);
            // Mensaje 10: registrar("PACIENTE").
            $this->registrar(($actual === null ? 'Registró ' : 'Modificó ') . $codigo);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->mensaje = $actual === null ? "Paciente $codigo registrado correctamente." : "Paciente $codigo actualizado correctamente.";

        return true;
    }

    // Paso 7: formatos. E2: fecha de nacimiento futura, teléfono o correo inválidos.
    private function validar(array $datos): ?array
    {
        $texto = fn (string $campo) => preg_replace('/\s+/u', ' ', trim((string) ($datos[$campo] ?? '')));
        $opcional = fn (string $campo) => $texto($campo) !== '' ? $texto($campo) : null;
        $telefonoValido = fn (string $t) => preg_match('/^[0-9+\- ]{7,15}$/', $t) === 1;

        $ci = strtoupper($texto('ci'));
        $fecha = $texto('fecha_nacimiento');
        $sexo = $texto('sexo');
        $email = $texto('email');
        $sangre = $texto('tipo_sangre');

        if (!preg_match('/^[0-9A-Z\-]{4,15}$/', $ci)) {
            return $this->invalido('ci', 'Ingrese un CI válido (4 a 15 caracteres, solo números, letras o guion).');
        }
        if ($texto('nombres') === '' || mb_strlen($texto('nombres')) > 60) {
            return $this->invalido('nombres', 'Ingrese los nombres (máximo 60 caracteres).');
        }
        if ($texto('apellidos') === '' || mb_strlen($texto('apellidos')) > 60) {
            return $this->invalido('apellidos', 'Ingrese los apellidos (máximo 60 caracteres).');
        }
        if (\DateTime::createFromFormat('!Y-m-d', $fecha)?->format('Y-m-d') !== $fecha) {
            return $this->invalido('fecha_nacimiento', 'Ingrese una fecha de nacimiento válida.');
        }
        if ($fecha > date('Y-m-d')) {
            return $this->invalido('fecha_nacimiento', 'La fecha de nacimiento no puede ser futura.');
        }
        if ($sexo !== '' && !isset(self::SEXOS[$sexo])) {
            return $this->invalido('sexo', 'Seleccione un sexo válido.');
        }
        if (!$telefonoValido($texto('telefono'))) {
            return $this->invalido('telefono', 'Ingrese un teléfono válido (7 a 15 dígitos).');
        }
        if ($email !== '' && (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return $this->invalido('email', 'Ingrese un correo electrónico válido.');
        }
        if (mb_strlen($texto('direccion')) > 150) {
            return $this->invalido('direccion', 'La dirección admite hasta 150 caracteres.');
        }
        if ($sangre !== '' && !in_array($sangre, Paciente::TIPOS_SANGRE, true)) {
            return $this->invalido('tipo_sangre', 'Seleccione un tipo de sangre válido.');
        }

        // Contacto de emergencia: opcional, pero si se llena va completo.
        $contacto = ['nombre' => $texto('contacto_nombre'), 'parentesco' => $texto('contacto_parentesco'), 'telefono' => $texto('contacto_telefono')];
        if (implode('', $contacto) === '') {
            $contacto = null;
        } elseif ($contacto['nombre'] === '' || mb_strlen($contacto['nombre']) > 100) {
            return $this->invalido('contacto_nombre', 'Ingrese el nombre del contacto de emergencia.');
        } elseif ($contacto['parentesco'] === '' || mb_strlen($contacto['parentesco']) > 30) {
            return $this->invalido('contacto_parentesco', 'Ingrese el parentesco del contacto de emergencia.');
        } elseif (!$telefonoValido($contacto['telefono'])) {
            return $this->invalido('contacto_telefono', 'Ingrese un teléfono válido para el contacto de emergencia.');
        }

        return [
            'ci'                    => $ci,
            'nombres'               => $texto('nombres'),
            'apellidos'             => $texto('apellidos'),
            'fecha_nacimiento'      => $fecha,
            'sexo'                  => $sexo !== '' ? $sexo : null,
            'telefono'              => $texto('telefono'),
            'email'                 => $email !== '' ? $email : null,
            'direccion'             => $opcional('direccion'),
            'tipo_sangre'           => $sangre !== '' ? $sangre : null,
            'alergias'              => $opcional('alergias'),
            'enfermedades_cronicas' => $opcional('enfermedades_cronicas'),
            'estado'                => ($datos['estado'] ?? '1') === '1',
            'contacto'              => $contacto,
        ];
    }

    private function registrar(string $detalle): void
    {
        (new Bitacora())->registrar((int) ($this->usuarioSesion['id_usuario'] ?? 0), 'PACIENTE', 'Pacientes', $detalle);
    }

    private function invalido(string $campo, string $mensaje): null
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return null;
    }
}
