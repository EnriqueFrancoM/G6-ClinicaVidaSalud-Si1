<?php

namespace App\Controllers\Seguridad;

use App\Core\App;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ProveedorNotificaciones;

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
        if (($datos['accion'] ?? '') === 'reenviar') {
            return $this->reenviarClave($usuarios, $idUsuario);
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
            // Primero se confirma el alta. El correo sale después, como en un alta real:
            // si el correo falla, la cuenta ya existe y se puede reenviar la clave.
            $temporal = $this->generarClaveTemporal();
            try {
                $usuarios->insertar($limpio, password_hash($temporal, PASSWORD_DEFAULT));
            } catch (\Throwable $e) {
                error_log('[CtrlUsuario] ' . $e->getMessage());
                $this->mensaje = 'No se pudo guardar el usuario. Revise los datos e intente de nuevo.';
                return false;
            }
            $this->anotar($bitacora, 'Registró ' . $limpio['username']);
            $this->mensaje = $this->avisarClave($limpio['email'], $limpio['username'], $temporal, true);
            return true;
        }

        if ($usuarios->buscarPorId($idUsuario) === null) {
            $this->mensaje = 'El usuario no existe.';
            return false;
        }
        try {
            $usuarios->actualizar($idUsuario, $limpio);
        } catch (\Throwable $e) {
            error_log('[CtrlUsuario] ' . $e->getMessage());
            $this->mensaje = 'No se pudo guardar el usuario. Revise los datos e intente de nuevo.';
            return false;
        }
        $this->anotar($bitacora, 'Modificó ' . $limpio['username']);
        $this->mensaje = 'Usuario actualizado correctamente.';

        return true;
    }

    private function reenviarClave(Usuario $usuarios, int $idUsuario): bool
    {
        $fila = $usuarios->buscarPorId($idUsuario);
        if ($fila === null) {
            $this->mensaje = 'El usuario no existe.';
            return false;
        }
        if (!$fila['estado']) {
            $this->mensaje = 'Active la cuenta antes de reenviar la clave.';
            return false;
        }
        if ($idUsuario === $this->idSesion()) {
            $this->mensaje = 'No puede reenviar la clave de su propia cuenta.';
            return false;
        }

        $temporal = $this->generarClaveTemporal();
        $usuarios->asignarClaveTemporal($idUsuario, password_hash($temporal, PASSWORD_DEFAULT));
        $this->anotar(new Bitacora(), 'Reenvió la clave temporal de ' . $fila['username']);
        $this->mensaje = $this->avisarClave($fila['email'], $fila['username'], $temporal, false);

        return true;
    }

    // El alta ya quedó guardado. La clave va al correo y no se escribe en la bitácora.
    private function avisarClave(string $email, string $username, string $temporal, bool $alta): string
    {
        $proveedor = new ProveedorNotificaciones();
        $enviado = $proveedor->enviarMensaje([
            'canal'        => 'EMAIL',
            'destinatario' => $email,
            'asunto'       => 'Tu acceso a ' . App::config('app.name'),
            'cuerpo'       => $this->cuerpoClave($email, $username, $temporal),
        ]);
        if ($enviado) {
            $this->anotar(new Bitacora(), 'Envió la clave temporal a ' . $email);
            return $alta
                ? 'Usuario registrado. La clave temporal se envió a ' . $email . '.'
                : 'Se envió una nueva clave temporal a ' . $email . '.';
        }

        $motivo = $proveedor->detalle !== '' ? $proveedor->detalle : 'el correo no está configurado';
        return ($alta ? 'Usuario registrado, pero el correo no se envió' : 'La clave cambió, pero el correo no se envió')
            . ' (' . $motivo . '). Clave temporal: ' . $temporal . '. Puede reenviarla desde la lista.';
    }

    private function cuerpoClave(string $email, string $username, string $temporal): string
    {
        $nombre = (string) App::config('app.name');

        return "Hola,\n\n"
            . "Un administrador creó tu cuenta en {$nombre}. Usá estos datos en el primer ingreso:\n\n"
            . "Usuario: {$username}\n"
            . "Correo: {$email}\n"
            . "Clave temporal: {$temporal}\n\n"
            . "Al ingresar, el sistema te va a pedir que elijas una clave nueva. No compartas esta clave.\n\n"
            . 'Ingresar: ' . App::url('/login') . "\n";
    }

    private function anotar(Bitacora $bitacora, string $detalle): void
    {
        try {
            $bitacora->registrar($this->idBitacora(), 'USUARIO', 'Seguridad', $detalle);
        } catch (\Throwable $e) {
            error_log('[CtrlUsuario] bitácora ' . $e->getMessage());
        }
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
        $this->anotar(new Bitacora(), ($activo ? 'Activó ' : 'Desactivó ') . $fila['username']);
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

    // Cumple la política de primer ingreso: 8+, mayúscula, minúscula, número y símbolo.
    private function generarClaveTemporal(): string
    {
        $grupos = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnpqrstuvwxyz',
            '23456789',
            '!@#$%*?',
        ];
        $caracteres = [];
        foreach ($grupos as $grupo) {
            $caracteres[] = $grupo[random_int(0, strlen($grupo) - 1)];
        }
        $todos = implode('', $grupos);
        while (count($caracteres) < 12) {
            $caracteres[] = $todos[random_int(0, strlen($todos) - 1)];
        }
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }
        $clave = implode('', $caracteres);
        if (!(new Usuario())->validarPolitica($clave)) {
            return $this->generarClaveTemporal();
        }

        return $clave;
    }

    private function idSesion(): int
    {
        return (int) ($this->usuarioSesion['id_usuario'] ?? 0);
    }

    private function idBitacora(): ?int
    {
        $id = $this->idSesion();

        return $id > 0 ? $id : null;
    }
}
