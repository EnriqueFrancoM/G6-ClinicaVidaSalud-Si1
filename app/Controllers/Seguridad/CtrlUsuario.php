<?php

namespace App\Controllers\Seguridad;

use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;

// CU03 control. Mensajes: listarUsuarios(), guardarUsuario(datos).
class CtrlUsuario
{
    public ?array $usuarioSesion;

    public string $mensaje = '';

    // Campo con error, para resaltarlo en el formulario (E1, E2).
    public ?string $campoError = null;

    public function __construct()
    {
        $this->usuarioSesion = Session::user();
    }

    // CU03 mensaje 2 → 3: listar().
    public function listarUsuarios(string $nombre = '', ?int $idRol = null, ?bool $estado = null): array
    {
        return [
            'usuarios'  => (new Usuario())->listar($nombre, $idRol, $estado),
            'roles'     => (new Rol())->listar(),
            'medicos'   => (new Medico())->listar(),
            'pacientes' => (new Paciente())->buscar(),
        ];
    }

    // CU03 mensaje 5 → 6, 7, 8, 9.
    public function guardarUsuario(array $datos): bool
    {
        $usuarios = new Usuario();
        $idUsuario = (int) ($datos['id_usuario'] ?? 0);

        if (($datos['accion'] ?? '') === 'estado') {
            return $this->cambiarEstado($usuarios, $idUsuario, (bool) ($datos['estado'] ?? false));
        }

        $limpio = $this->validar($datos);
        if ($limpio === null) {
            return false;
        }

        // E1 (paso 6): usuario o correo ya registrados.
        $duplicado = $usuarios->existeUsuarioOEmail($limpio['username'], $limpio['email'], $idUsuario ?: null);
        if ($duplicado !== null) {
            $this->campoError = $duplicado;
            $this->mensaje = $duplicado === 'username'
                ? 'El nombre de usuario ya está registrado.'
                : 'El correo electrónico ya está registrado.';
            return false;
        }

        // E4: el administrador no puede desactivar su propia cuenta.
        if ($idUsuario === $this->idSesion() && !$limpio['estado']) {
            $this->campoError = 'estado';
            $this->mensaje = 'No puede desactivar su propia cuenta.';
            return false;
        }

        $bitacora = new Bitacora();
        if ($idUsuario === 0) {
            // Paso 7: contraseña temporal cifrada con password_hash() y primer ingreso.
            $temporal = $this->generarClaveTemporal();
            $usuarios->insertar($limpio, password_hash($temporal, PASSWORD_DEFAULT));
            $bitacora->registrar($this->idSesion(), 'USUARIO', 'Seguridad', 'Registró ' . $limpio['username']);
            $this->mensaje = 'Usuario registrado. Contraseña temporal: ' . $temporal . ' (deberá cambiarla en el primer ingreso).';
            return true;
        }

        if ($usuarios->buscarPorId($idUsuario) === null) {
            $this->mensaje = 'El usuario no existe.';
            return false;
        }
        $usuarios->actualizar($idUsuario, $limpio);
        $bitacora->registrar($this->idSesion(), 'USUARIO', 'Seguridad', 'Modificó ' . $limpio['username']);
        $this->mensaje = 'Usuario actualizado correctamente.';

        return true;
    }

    private function cambiarEstado(Usuario $usuarios, int $idUsuario, bool $activo): bool
    {
        $fila = $usuarios->buscarPorId($idUsuario);
        if ($fila === null) {
            $this->mensaje = 'El usuario no existe.';
            return false;
        }
        // E4
        if ($idUsuario === $this->idSesion() && !$activo) {
            $this->mensaje = 'No puede desactivar su propia cuenta.';
            return false;
        }

        $usuarios->cambiarEstado($idUsuario, $activo);
        (new Bitacora())->registrar(
            $this->idSesion(),
            'USUARIO',
            'Seguridad',
            ($activo ? 'Activó ' : 'Desactivó ') . $fila['username']
        );
        $this->mensaje = $activo ? 'Usuario activado.' : 'Usuario desactivado.';

        return true;
    }

    // E2 (paso 6): datos inválidos.
    private function validar(array $datos): ?array
    {
        $username = trim((string) ($datos['username'] ?? ''));
        $email = trim((string) ($datos['email'] ?? ''));
        $idRol = (int) ($datos['id_rol'] ?? 0);
        $vinculo = $this->leerVinculo((string) ($datos['vinculo'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) {
            return $this->invalido('username', 'El nombre de usuario debe tener de 3 a 40 caracteres (letras, números, punto, guion o guion bajo).');
        }
        if (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->invalido('email', 'Ingrese un correo electrónico válido.');
        }
        // CU03 mensaje 7: obtenerRol(id_rol).
        $rol = (new Rol())->obtenerRol($idRol);
        if ($rol === null) {
            return $this->invalido('id_rol', 'Seleccione un rol válido.');
        }
        if ($vinculo === false) {
            return $this->invalido('vinculo', 'El médico o paciente seleccionado no es válido.');
        }
        if ($vinculo['tipo'] !== null && !$this->vinculoDisponible($vinculo, (int) ($datos['id_usuario'] ?? 0))) {
            return $this->invalido('vinculo', 'Ese médico o paciente ya está vinculado a otro usuario.');
        }

        return [
            'username' => $username,
            'email'    => $email,
            'id_rol'   => $idRol,
            'estado'   => ($datos['estado'] ?? '1') === '1',
            'vinculo'  => $vinculo,
        ];
    }

    // "medico:3" / "paciente:5" / "" → ['tipo', 'id']; false si el formato no es válido.
    private function leerVinculo(string $valor): array|false
    {
        if ($valor === '') {
            return ['tipo' => null, 'id' => null];
        }
        if (!preg_match('/^(medico|paciente):(\d+)$/', $valor, $m)) {
            return false;
        }

        return ['tipo' => $m[1], 'id' => (int) $m[2]];
    }

    private function vinculoDisponible(array $vinculo, int $idUsuario): bool
    {
        $lista = $vinculo['tipo'] === 'medico' ? (new Medico())->listar() : (new Paciente())->buscar();
        $clave = $vinculo['tipo'] === 'medico' ? 'id_medico' : 'id_paciente';
        foreach ($lista as $fila) {
            if ((int) $fila[$clave] === $vinculo['id']) {
                return $fila['id_usuario'] === null || (int) $fila['id_usuario'] === $idUsuario;
            }
        }

        return false;
    }

    private function invalido(string $campo, string $mensaje): null
    {
        $this->campoError = $campo;
        $this->mensaje = $mensaje;
        return null;
    }

    private function generarClaveTemporal(): string
    {
        $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $clave = '';
        for ($i = 0; $i < 10; $i++) {
            $clave .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }

        return $clave;
    }

    private function idSesion(): int
    {
        return (int) ($this->usuarioSesion['id_usuario'] ?? 0);
    }
}
