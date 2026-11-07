<?php

namespace App\Controllers\Portal;

use App\Controllers\GestionClinica\CtrlPaciente;
use App\Core\Database;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\ContactoEmergencia;
use App\Models\Paciente;
use App\Models\Usuario;

// CU17 control. Mensajes: guardarPerfil(datos), validarAcceso().
// Entidades: Usuario.validarTitular(sesión), Paciente.guardarDatosPropios(datos), ContactoEmergencia.guardarContacto(datos).
class CtrlPerfil
{
    public ?array $usuarioActual;

    public string $mensaje = '';

    public ?string $campoError = null;

    public function __construct()
    {
        $this->usuarioActual = Session::user();
    }

    public function validarAcceso(): bool
    {
        return Session::hasPermission('POR_PACIENTE');
    }

    // Paso 2: abrir el perfil propio (siempre el del titular de la sesión).
    public function obtenerPerfil(): ?array
    {
        $titular = (new Usuario())->validarTitular($this->usuarioActual ?? []);
        if ($titular === null) {
            return null;
        }
        $ficha = $titular['id_paciente'] !== null
            ? ((new Paciente())->buscar(['id_paciente' => $titular['id_paciente'], 'estado' => null])[0] ?? null)
            : null;

        return ['titular' => $titular, 'ficha' => $ficha];
    }

    // CU17 mensaje guardarPerfil(datos). Sin sesión es el registro del paciente (paso 1);
    // con sesión actualiza solo el perfil del titular.
    public function guardarPerfil(array $datos): bool
    {
        return $this->usuarioActual === null ? $this->registrar($datos) : $this->actualizar($datos);
    }

    private function actualizar(array $datos): bool
    {
        if (!$this->validarAcceso()) {
            return $this->error('Solo el paciente titular puede editar su perfil.');
        }
        // Mensaje validarTitular(sesión). Alternativa: intento de editar otro perfil.
        $titular = (new Usuario())->validarTitular($this->usuarioActual);
        if ($titular === null) {
            return $this->error('Su cuenta no está habilitada para el portal del paciente.');
        }
        if (isset($datos['id_paciente']) && (int) $datos['id_paciente'] !== (int) $titular['id_paciente']) {
            return $this->error('Solo puede editar su propio perfil.');
        }

        $limpio = $this->validarDatos($datos, $titular['id_paciente']);
        if ($limpio === null) {
            return false;
        }
        if ($limpio['email'] !== null && ($campo = (new Usuario())->existeUsuarioOEmail('', $limpio['email'], (int) $titular['id_usuario'])) === 'email') {
            return $this->error('Ese correo ya está registrado en otra cuenta.', 'email');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            // Mensajes guardarDatosPropios(datos) y guardarContacto(datos).
            $idPaciente = (new Paciente())->guardarDatosPropios((int) $titular['id_usuario'], $titular['id_paciente'], $limpio);
            (new ContactoEmergencia())->guardarContacto($idPaciente, $limpio['contacto']);
            if ($limpio['email'] !== null) {
                (new Usuario())->actualizarEmail((int) $titular['id_usuario'], $limpio['email']);
            }
            (new Bitacora())->registrar((int) $titular['id_usuario'], 'PERFIL', 'Portal', 'Actualizó su perfil');
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->mensaje = 'Perfil guardado correctamente.';

        return true;
    }

    // Paso 1: registrarse. Cuenta con rol Paciente y ficha vinculada, en una transacción.
    private function registrar(array $datos): bool
    {
        $username = mb_strtolower(trim((string) ($datos['username'] ?? '')));
        $clave = (string) ($datos['clave'] ?? '');
        $usuarios = new Usuario();

        if (!preg_match('/^[a-z0-9._-]{4,40}$/', $username)) {
            return $this->error('El usuario debe tener de 4 a 40 caracteres (letras, números, punto, guion).', 'username');
        }
        if (filter_var((string) ($datos['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
            return $this->error('Ingrese un correo electrónico válido; se usará para iniciar sesión y recibir recordatorios.', 'email');
        }
        if (!$usuarios->validarPolitica($clave)) {
            return $this->error('La contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un símbolo.', 'clave');
        }
        if ($clave !== (string) ($datos['confirmar'] ?? '')) {
            return $this->error('La confirmación no coincide con la contraseña.', 'confirmar');
        }

        $limpio = $this->validarDatos($datos, null);
        if ($limpio === null) {
            return false;
        }
        // Alternativa: correo o usuario duplicado.
        $duplicado = $usuarios->existeUsuarioOEmail($username, $limpio['email']);
        if ($duplicado !== null) {
            return $this->error($duplicado === 'username' ? 'Ese nombre de usuario ya existe.' : 'Ese correo ya está registrado.', $duplicado);
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $idUsuario = $usuarios->registrarPaciente($username, $limpio['email'], password_hash($clave, PASSWORD_DEFAULT));
            $idPaciente = (new Paciente())->guardarDatosPropios($idUsuario, null, $limpio);
            (new ContactoEmergencia())->guardarContacto($idPaciente, $limpio['contacto']);
            (new Bitacora())->registrar($idUsuario, 'REGISTRO', 'Portal', 'Registro de paciente en el portal');
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->mensaje = 'Cuenta creada. Ya puede iniciar sesión con su usuario o correo.';

        return true;
    }

    // Paso 4: validar campos (mismas reglas de CU10) y CI no duplicado.
    private function validarDatos(array $datos, ?int $idPaciente): ?array
    {
        $validador = new CtrlPaciente();
        $limpio = $validador->validar($datos);
        if ($limpio === null) {
            $this->error($validador->mensaje, $validador->campoError);
            return null;
        }
        if ((new Paciente())->existeCI($limpio['ci'], $idPaciente) !== null) {
            $this->error('Ese CI ya está registrado. Si ya es paciente de la clínica, pida en recepción que vinculen su cuenta.', 'ci');
            return null;
        }
        $limpio['estado'] = true;

        return $limpio;
    }

    private function error(string $mensaje, ?string $campo = null): bool
    {
        $this->mensaje = $mensaje;
        $this->campoError = $campo;
        return false;
    }
}
