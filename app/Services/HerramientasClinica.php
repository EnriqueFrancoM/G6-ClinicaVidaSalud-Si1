<?php

namespace App\Services;

use App\Controllers\Citas\CtrlAgenda;
use App\Controllers\Citas\CtrlCita;
use App\Controllers\Citas\CtrlEstadoCita;
use App\Controllers\Citas\CtrlListaEspera;
use App\Controllers\GestionClinica\CtrlEspecialidad;
use App\Controllers\GestionClinica\CtrlHorario;
use App\Controllers\GestionClinica\CtrlMedico;
use App\Controllers\GestionClinica\CtrlPaciente;
use App\Controllers\Portal\CtrlReserva;
use App\Controllers\Seguridad\CtrlRol;
use App\Controllers\Seguridad\CtrlUsuario;
use App\Core\App;
use App\Core\Session;
use App\Models\Bitacora;
use App\Models\BloqueoAgenda;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Paciente;

// Acciones del asistente. Cada una reutiliza el control del caso de uso y exige el permiso del rol.
class HerramientasClinica
{
    public function definiciones(): array
    {
        $salida = [];
        foreach ($this->catalogo() as $herramienta) {
            if (!$this->permite($herramienta['permiso'])) {
                continue;
            }
            $salida[] = [
                'type' => 'function',
                'function' => [
                    'name' => $herramienta['nombre'],
                    'description' => $herramienta['descripcion'],
                    'parameters' => $herramienta['parametros'],
                ],
            ];
        }

        return $salida;
    }

    public function ejecutar(string $nombre, array $args): string
    {
        foreach ($this->catalogo() as $herramienta) {
            if ($herramienta['nombre'] !== $nombre) {
                continue;
            }
            if (!$this->permite($herramienta['permiso'])) {
                return $this->json(['ok' => false, 'mensaje' => 'Esta cuenta no tiene permiso para esa operación.']);
            }

            return ($herramienta['ejecutar'])($args);
        }

        return $this->json(['ok' => false, 'mensaje' => 'La operación no existe.']);
    }

    private function catalogo(): array
    {
        $objeto = static fn (array $propiedades, array $requeridos = []) => [
            'type' => 'object',
            'properties' => $propiedades === [] ? new \stdClass() : $propiedades,
            'required' => $requeridos,
        ];
        $texto = static fn (string $descripcion) => ['type' => 'string', 'description' => $descripcion];
        $entero = static fn (string $descripcion) => ['type' => 'integer', 'description' => $descripcion];
        $bandera = static fn (string $descripcion) => ['type' => 'boolean', 'description' => $descripcion];

        return [
            $this->herramienta('listar_especialidades', 'Lista las especialidades, activas e inactivas.', ['ESP_GESTIONAR', 'MED_GESTIONAR', 'HOR_GESTIONAR', 'CIT_GESTIONAR', 'POR_PACIENTE', 'AGE_CONSULTAR'], $objeto([]), function (array $args) {
                return $this->json(['ok' => true, 'datos' => (new Especialidad())->listar()]);
            }),
            $this->herramienta('guardar_especialidad', 'Crea o edita una especialidad. Sin id_especialidad crea una nueva. No se elimina: para darla de baja use cambiar_estado_especialidad.', ['ESP_GESTIONAR'], $objeto([
                'id_especialidad' => $entero('Vacío al crear. Id al editar.'),
                'nombre' => $texto('Nombre, máximo 60 caracteres.'),
                'descripcion' => $texto('Descripción opcional, máximo 200 caracteres.'),
                'estado' => $bandera('Activa. Por defecto true.'),
            ], ['nombre']), function (array $args) {
                $control = new CtrlEspecialidad();
                $ok = $control->guardar([
                    'id_especialidad' => (int) ($args['id_especialidad'] ?? 0),
                    'nombre' => $args['nombre'] ?? '',
                    'descripcion' => $args['descripcion'] ?? '',
                    'estado' => $this->marca($args['estado'] ?? true),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('cambiar_estado_especialidad', 'Activa o desactiva una especialidad. Desactivar es la baja: no se borra la fila.', ['ESP_GESTIONAR'], $objeto([
                'id_especialidad' => $entero('Id de la especialidad.'),
                'activo' => $bandera('true activa, false desactiva.'),
            ], ['id_especialidad', 'activo']), function (array $args) {
                $control = new CtrlEspecialidad();
                $ok = $control->guardar([
                    'accion' => 'estado',
                    'id_especialidad' => (int) ($args['id_especialidad'] ?? 0),
                    'estado' => $this->marca($args['activo'] ?? false),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('listar_medicos', 'Consulta médicos por nombre, especialidad o código. Devuelve id, código, registro, contacto y especialidades.', ['MED_GESTIONAR', 'HOR_GESTIONAR', 'CIT_GESTIONAR', 'AGE_CONSULTAR', 'POR_PACIENTE'], $objeto([
                'nombre' => $texto('Parte del nombre, apellido o código.'),
                'id_especialidad' => $entero('Filtra por especialidad. También acepta el nombre en especialidad.'),
                'especialidad' => $texto('Nombre de la especialidad, si no se conoce el id.'),
                'solo_activos' => $bandera('Por defecto true.'),
            ]), function (array $args) {
                $idEspecialidad = (int) ($args['id_especialidad'] ?? 0);
                if ($idEspecialidad <= 0 && ($args['especialidad'] ?? '') !== '') {
                    $idEspecialidad = $this->idEspecialidad($args['especialidad']);
                }
                $filtro = ['nombre' => trim((string) ($args['nombre'] ?? ''))];
                if ($idEspecialidad > 0) {
                    $filtro['id_especialidad'] = $idEspecialidad;
                }
                if (($args['solo_activos'] ?? true) !== false) {
                    $filtro['estado'] = true;
                }
                $filas = (new Medico())->buscar($filtro);
                if (!$this->permite(['MED_GESTIONAR', 'HOR_GESTIONAR', 'CIT_GESTIONAR', 'AGE_CONSULTAR'])) {
                    $filas = array_map(static fn (array $fila): array => [
                        'id_medico' => $fila['id_medico'],
                        'codigo' => $fila['codigo'],
                        'nombres' => $fila['nombres'],
                        'apellidos' => $fila['apellidos'],
                        'nro_registro_prof' => $fila['nro_registro_prof'],
                        'especialidades' => $fila['especialidades'],
                    ], $filas);
                }

                return $this->json(['ok' => true, 'datos' => $filas]);
            }),
            $this->herramienta('guardar_medico', 'Crea o edita un médico. especialidades acepta ids o nombres. Desactivar se hace con cambiar_estado_medico.', ['MED_GESTIONAR'], $objeto([
                'id_medico' => $entero('Vacío al crear.'),
                'nro_registro_prof' => $texto('Registro profesional, máximo 20.'),
                'nombres' => $texto('Nombres.'),
                'apellidos' => $texto('Apellidos.'),
                'telefono' => $texto('Teléfono de 7 a 15 dígitos.'),
                'email' => $texto('Correo opcional.'),
                'especialidades' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Ids o nombres de especialidades activas. Al menos una.'],
                'estado' => $bandera('Activo. Por defecto true.'),
                'crear_cuenta' => $bandera('Al crear, abre un usuario con rol Médico y contraseña temporal.'),
            ], ['nro_registro_prof', 'nombres', 'apellidos', 'telefono', 'especialidades']), function (array $args) {
                $control = new CtrlMedico();
                $ok = $control->guardarMedico([
                    'id_medico' => (int) ($args['id_medico'] ?? 0),
                    'nro_registro_prof' => $args['nro_registro_prof'] ?? '',
                    'nombres' => $args['nombres'] ?? '',
                    'apellidos' => $args['apellidos'] ?? '',
                    'telefono' => $args['telefono'] ?? '',
                    'email' => $args['email'] ?? '',
                    'especialidades' => $this->idsEspecialidad($args['especialidades'] ?? []),
                    'estado' => $this->marca($args['estado'] ?? true),
                    'crear_cuenta' => $this->marca($args['crear_cuenta'] ?? false),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('cambiar_estado_medico', 'Activa o desactiva un médico. No se elimina. Un médico inactivo no recibe citas nuevas.', ['MED_GESTIONAR'], $objeto([
                'id_medico' => $entero('Id o, si no se conoce, use codigo.'),
                'codigo' => $texto('Código MED-000, alternativo al id.'),
                'activo' => $bandera('true activa, false desactiva.'),
            ], ['activo']), function (array $args) {
                $id = (int) ($args['id_medico'] ?? 0);
                if ($id <= 0) {
                    $id = $this->idMedico($args['codigo'] ?? '');
                }
                $control = new CtrlMedico();
                $ok = $control->guardarMedico([
                    'accion' => 'estado',
                    'id_medico' => $id,
                    'estado' => $this->marca($args['activo'] ?? false),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('listar_pacientes', 'Consulta pacientes por CI, código o nombre. Por defecto solo activos.', ['PAC_GESTIONAR', 'CIT_GESTIONAR'], $objeto([
                'nombre' => $texto('Parte del nombre.'),
                'ci' => $texto('CI o inicio del CI. También acepta el código PAC-00000.'),
                'incluir_inactivos' => $bandera('true incluye dados de baja.'),
            ]), function (array $args) {
                $criterio = [
                    'nombre' => trim((string) ($args['nombre'] ?? '')),
                    'ci' => trim((string) ($args['ci'] ?? '')),
                ];
                if (($args['incluir_inactivos'] ?? false) === true) {
                    $criterio['estado'] = null;
                }
                $codigo = (new Paciente())->buscarPaciente($criterio['ci']);
                if ($codigo !== null && !str_contains(strtoupper($criterio['ci']), 'PAC') && $criterio['ci'] !== '') {
                    // buscar() filtra CI por prefijo. Un código exacto se resuelve aparte.
                }
                if ($codigo !== null && str_starts_with(strtoupper($criterio['ci']), 'PAC')) {
                    $criterio = ['id_paciente' => (int) $codigo['id_paciente'], 'estado' => ($args['incluir_inactivos'] ?? false) === true ? null : true];
                }

                return $this->json(['ok' => true, 'datos' => (new Paciente())->buscar($criterio)]);
            }),
            $this->herramienta('ver_paciente', 'Ficha de un paciente e historial de citas. paciente es el id, el CI o el código.', ['PAC_GESTIONAR'], $objeto([
                'paciente' => $texto('Id, CI o código PAC-00000.'),
            ], ['paciente']), function (array $args) {
                $id = $this->idPaciente($args['paciente'] ?? 0);
                $ficha = (new CtrlPaciente())->obtenerFicha($id);
                if ($ficha === null) {
                    return $this->json(['ok' => false, 'mensaje' => 'El paciente no existe.']);
                }
                if (isset($ficha['historial']) && is_array($ficha['historial'])) {
                    $ficha['historial'] = array_slice($ficha['historial'], 0, 20);
                }

                return $this->json(['ok' => true, 'datos' => $ficha]);
            }),
            $this->herramienta('guardar_paciente', 'Crea o edita un paciente. Sin id crea uno nuevo. La baja es desactivar_paciente.', ['PAC_GESTIONAR'], $objeto([
                'id_paciente' => $entero('Vacío al crear.'),
                'ci' => $texto('CI, 4 a 15 caracteres.'),
                'nombres' => $texto('Nombres.'),
                'apellidos' => $texto('Apellidos.'),
                'fecha_nacimiento' => $texto('Fecha Y-m-d.'),
                'sexo' => $texto('F, M, O o vacío.'),
                'telefono' => $texto('Teléfono.'),
                'email' => $texto('Correo opcional.'),
                'direccion' => $texto('Dirección opcional.'),
                'tipo_sangre' => $texto('O+, O-, A+, A-, B+, B-, AB+ o AB-.'),
                'alergias' => $texto('Opcional.'),
                'enfermedades_cronicas' => $texto('Opcional.'),
                'contacto_nombre' => $texto('Contacto de emergencia. Si se llena, parentesco y teléfono también.'),
                'contacto_parentesco' => $texto('Parentesco.'),
                'contacto_telefono' => $texto('Teléfono del contacto.'),
                'estado' => $bandera('Activo. Por defecto true.'),
            ], ['ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'telefono']), function (array $args) {
                $control = new CtrlPaciente();
                $datos = $args;
                $datos['id_paciente'] = (int) ($args['id_paciente'] ?? 0);
                $datos['estado'] = $this->marca($args['estado'] ?? true);
                $ok = $control->guardarPaciente($datos);

                return $this->resultado($control, $ok, $ok ? ['id_paciente' => $control->idPaciente] : null);
            }),
            $this->herramienta('desactivar_paciente', 'Da de baja a un paciente. Conserva el historial y no borra la fila.', ['PAC_GESTIONAR'], $objeto([
                'paciente' => $texto('Id, CI o código.'),
            ], ['paciente']), function (array $args) {
                $control = new CtrlPaciente();
                $ok = $control->guardarPaciente([
                    'accion' => 'desactivar',
                    'id_paciente' => $this->idPaciente($args['paciente'] ?? 0),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('listar_horarios', 'Horarios semanales de un médico. medico es id, código o nombre.', ['HOR_GESTIONAR', 'CIT_GESTIONAR', 'AGE_CONSULTAR'], $objeto([
                'medico' => $texto('Id, código MED-000 o nombre.'),
            ], ['medico']), function (array $args) {
                $id = $this->idMedico($args['medico'] ?? 0);
                if ($id <= 0) {
                    return $this->json(['ok' => false, 'mensaje' => 'No encontré ese médico.']);
                }
                $datos = (new CtrlHorario())->obtenerHorarios($id);

                return $this->json(['ok' => true, 'datos' => [
                    'horarios' => $datos['horarios'],
                    'bloqueos' => $datos['bloqueos'],
                    'dias' => CtrlHorario::DIAS,
                ]]);
            }),
            $this->herramienta('guardar_horario', 'Crea o edita el horario semanal de un médico. dia_semana va de 1 lunes a 7 domingo. duracion_cita_min es 15, 20, 30, 45 o 60. Si el cambio afecta citas futuras, la primera llamada lo avisa y hay que repetirla con confirmado true.', ['HOR_GESTIONAR'], $objeto([
                'medico' => $texto('Id, código o nombre.'),
                'id_horario' => $entero('Vacío al crear.'),
                'dia_semana' => $entero('1 lunes … 7 domingo.'),
                'hora_inicio' => $texto('HH:MM.'),
                'hora_fin' => $texto('HH:MM. El rango debe ser múltiplo de la duración.'),
                'duracion_cita_min' => $entero('15, 20, 30, 45 o 60.'),
                'confirmado' => $bandera('true solo después de avisar que hay citas futuras afectadas.'),
            ], ['medico', 'dia_semana', 'hora_inicio', 'hora_fin', 'duracion_cita_min']), function (array $args) {
                $control = new CtrlHorario();
                $ok = $control->guardarHorario([
                    'id_medico' => $this->idMedico($args['medico'] ?? 0),
                    'id_horario' => (int) ($args['id_horario'] ?? 0),
                    'dia_semana' => (int) ($args['dia_semana'] ?? 0),
                    'hora_inicio' => $args['hora_inicio'] ?? '',
                    'hora_fin' => $args['hora_fin'] ?? '',
                    'duracion_cita_min' => (int) ($args['duracion_cita_min'] ?? 0),
                    'confirmado' => $this->marca($args['confirmado'] ?? false),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('desactivar_horario', 'Da de baja un horario semanal. No borra las citas ya programadas. Si hay citas futuras, primero avisa.', ['HOR_GESTIONAR'], $objeto([
                'medico' => $texto('Id, código o nombre.'),
                'id_horario' => $entero('Id del horario.'),
                'confirmado' => $bandera('true para confirmar aunque haya citas futuras.'),
            ], ['medico', 'id_horario']), function (array $args) {
                $control = new CtrlHorario();
                $ok = $control->guardarHorario([
                    'accion' => 'desactivar',
                    'id_medico' => $this->idMedico($args['medico'] ?? 0),
                    'id_horario' => (int) ($args['id_horario'] ?? 0),
                    'confirmado' => $this->marca($args['confirmado'] ?? false),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('listar_dias_bloqueados', 'Lista días festivos de toda la clínica y bloqueos de un médico. Un festivo tiene alcance festivo e id_medico null.', ['HOR_GESTIONAR', 'CIT_GESTIONAR', 'AGE_CONSULTAR'], $objeto([
                'desde' => $texto('Fecha Y-m-d. Por defecto hoy.'),
            ]), function (array $args) {
                $desde = trim((string) ($args['desde'] ?? ''));
                if ($desde === '' || !App::fechaIso($desde)) {
                    $desde = date('Y-m-d');
                }

                return $this->json(['ok' => true, 'datos' => (new BloqueoAgenda())->listarDesde($desde)]);
            }),
            $this->herramienta('registrar_dia_bloqueado', 'Registra un día festivo para toda la clínica o un bloqueo de un médico. Las citas de ese día no se borran, pero no se ofrecen cupos nuevos.', ['HOR_GESTIONAR'], $objeto([
                'alcance' => $texto('general para festivo de todos, medico para un solo médico.'),
                'medico' => $texto('Obligatorio si alcance es medico. Id, código o nombre.'),
                'fecha' => $texto('Y-m-d, desde hoy.'),
                'motivo' => $texto('Motivo, máximo 120 caracteres.'),
            ], ['alcance', 'fecha', 'motivo']), function (array $args) {
                $general = mb_strtolower(trim((string) ($args['alcance'] ?? ''))) === 'general';
                $control = new CtrlHorario();
                $ok = $control->registrarBloqueo([
                    'alcance' => $general ? 'general' : 'medico',
                    'id_medico' => $general ? 0 : $this->idMedico($args['medico'] ?? 0),
                    'fecha' => $args['fecha'] ?? '',
                    'motivo' => $args['motivo'] ?? '',
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('quitar_dia_bloqueado', 'Elimina un festivo o un bloqueo de médico por id_bloqueo. Esta fila sí se borra.', ['HOR_GESTIONAR'], $objeto([
                'id_bloqueo' => $entero('Id devuelto por listar_dias_bloqueados.'),
            ], ['id_bloqueo']), function (array $args) {
                $control = new CtrlHorario();
                $ok = $control->quitarBloqueo((int) ($args['id_bloqueo'] ?? 0));

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('consultar_cupos', 'Cupos libres de un médico o de una especialidad en una fecha. No reserva.', ['CIT_GESTIONAR', 'HOR_GESTIONAR', 'POR_PACIENTE', 'AGE_CONSULTAR'], $objeto([
                'fecha' => $texto('Y-m-d.'),
                'medico' => $texto('Id, código o nombre. Opcional si se indica especialidad.'),
                'especialidad' => $texto('Id o nombre de la especialidad.'),
            ], ['fecha']), function (array $args) {
                $fecha = trim((string) ($args['fecha'] ?? ''));
                if (!App::fechaIso($fecha)) {
                    return $this->json(['ok' => false, 'mensaje' => 'Ingrese una fecha válida.']);
                }
                $horarios = new HorarioAtencion();
                $idEspecialidad = $this->idEspecialidad($args['especialidad'] ?? 0);
                if ($idEspecialidad > 0 && trim((string) ($args['medico'] ?? '')) === '') {
                    return $this->json(['ok' => true, 'datos' => $horarios->cuposDeEspecialidad($idEspecialidad, $fecha)]);
                }
                $idMedico = $this->idMedico($args['medico'] ?? 0);
                if ($idMedico <= 0) {
                    return $this->json(['ok' => false, 'mensaje' => 'Indique un médico o una especialidad.']);
                }

                return $this->json(['ok' => true, 'datos' => $horarios->consultarDisponibilidad($idMedico, $fecha)]);
            }),
            $this->herramienta('consultar_agenda', 'Citas de un día. medico 0 o vacío muestra a todos si la cuenta es de recepción. Un médico solo ve la suya.', ['AGE_CONSULTAR'], $objeto([
                'fecha' => $texto('Y-m-d.'),
                'medico' => $texto('Id, código, nombre o 0 para todos.'),
                'estado' => $texto('PROGRAMADA, CONFIRMADA, ATENDIDA, CANCELADA, INASISTENCIA o vacío.'),
            ], ['fecha']), function (array $args) {
                $medico = trim((string) ($args['medico'] ?? '0'));
                $id = $medico === '' || $medico === '0' ? 0 : $this->idMedico($medico);
                $control = new CtrlAgenda();
                $agenda = $control->consultarAgenda([
                    'id_medico' => $id,
                    'fecha' => $args['fecha'] ?? '',
                    'estado' => $args['estado'] ?? '',
                ]);
                if ($agenda === null) {
                    return $this->resultado($control, false);
                }
                $citas = [];
                foreach ($agenda['citas'] ?? [] as $cita) {
                    $citas[] = [
                        'codigo' => $cita['codigo'],
                        'hora_inicio' => $cita['hora_inicio'],
                        'hora_fin' => $cita['hora_fin'],
                        'paciente' => $cita['paciente'],
                        'paciente_codigo' => $cita['paciente_codigo'],
                        'medico' => $cita['medico'] ?? ($agenda['medico']['nombre'] ?? ''),
                        'especialidad' => $cita['especialidad'],
                        'estado' => $cita['estado'],
                        'motivo' => $cita['motivo'],
                        'prioritaria' => !empty($cita['prioritaria']),
                    ];
                }

                return $this->json(['ok' => true, 'mensaje' => $control->mensaje, 'datos' => $citas]);
            }),
            $this->herramienta('programar_cita', 'Programa una cita desde recepción. paciente es id, CI o código. medico y especialidad aceptan id o nombre.', ['CIT_GESTIONAR'], $objeto([
                'paciente' => $texto('Id, CI o código.'),
                'especialidad' => $texto('Id o nombre.'),
                'medico' => $texto('Id, código o nombre.'),
                'fecha' => $texto('Y-m-d.'),
                'hora_inicio' => $texto('HH:MM de un cupo libre.'),
                'motivo' => $texto('Motivo opcional.'),
            ], ['paciente', 'especialidad', 'medico', 'fecha', 'hora_inicio']), function (array $args) {
                $control = new CtrlCita();
                $ok = $control->programar([
                    'id_paciente' => $this->idPaciente($args['paciente'] ?? 0),
                    'id_especialidad' => $this->idEspecialidad($args['especialidad'] ?? 0),
                    'id_medico' => $this->idMedico($args['medico'] ?? 0),
                    'fecha' => $args['fecha'] ?? '',
                    'hora_inicio' => $args['hora_inicio'] ?? '',
                    'motivo' => $args['motivo'] ?? '',
                ]);

                return $this->resultado($control, $ok, $control->cita);
            }),
            $this->herramienta('reprogramar_cita', 'Cambia la fecha u hora de una cita programada o confirmada.', ['CIT_GESTIONAR'], $objeto([
                'codigo' => $texto('Código CM-0000.'),
                'fecha' => $texto('Nueva fecha Y-m-d.'),
                'hora_inicio' => $texto('Nuevo HH:MM libre.'),
            ], ['codigo', 'fecha', 'hora_inicio']), function (array $args) {
                $control = new CtrlCita();
                $ok = $control->reprogramar($args);

                return $this->resultado($control, $ok, $control->cita);
            }),
            $this->herramienta('cancelar_cita', 'Cancela una cita pendiente e indica el motivo. Libera el cupo.', ['CIT_GESTIONAR'], $objeto([
                'codigo' => $texto('Código CM-0000.'),
                'motivo' => $texto('Motivo, máximo 200 caracteres.'),
            ], ['codigo', 'motivo']), function (array $args) {
                $control = new CtrlCita();
                $ok = $control->cancelar($args);

                return $this->resultado($control, $ok, $control->cita);
            }),
            $this->herramienta('cambiar_estado_cita', 'Marca asistencia: CONFIRMADA, ATENDIDA o INASISTENCIA, según lo que el rol pueda hacer.', ['CIT_GESTIONAR', 'CIT_ATENDER'], $objeto([
                'codigo' => $texto('Código CM-0000.'),
                'estado' => $texto('CONFIRMADA, ATENDIDA o INASISTENCIA.'),
                'observacion' => $texto('Nota opcional.'),
            ], ['codigo', 'estado']), function (array $args) {
                $control = new CtrlEstadoCita();
                $ok = $control->cambiarEstado($args);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('listar_espera', 'Consulta la lista de espera.', ['CIT_GESTIONAR'], $objeto([
                'estado' => $texto('ACTIVA, OFERTADA, ASIGNADA, CANCELADA o vacío.'),
            ]), function (array $args) {
                $estado = trim((string) ($args['estado'] ?? ''));

                return $this->json(['ok' => true, 'datos' => (new CtrlListaEspera())->listar($estado !== '' ? $estado : null)]);
            }),
            $this->herramienta('gestionar_espera', 'Registra, acepta, rechaza o cancela una solicitud de lista de espera.', ['CIT_GESTIONAR'], $objeto([
                'accion' => $texto('registrar, aceptar, rechazar o cancelar.'),
                'paciente' => $texto('CI o código, al registrar.'),
                'especialidad' => $texto('Id o nombre, al registrar.'),
                'medico' => $texto('Opcional, al registrar.'),
                'fecha_desde' => $texto('Y-m-d opcional.'),
                'fecha_hasta' => $texto('Y-m-d opcional.'),
                'preferencias' => $texto('Nota opcional.'),
                'id_solicitud' => $entero('Obligatorio para aceptar, rechazar o cancelar.'),
            ], ['accion']), function (array $args) {
                $control = new CtrlListaEspera();
                $ok = $control->registrarSolicitud([
                    'accion' => $args['accion'] ?? 'registrar',
                    'paciente' => $args['paciente'] ?? '',
                    'id_especialidad' => $this->idEspecialidad($args['especialidad'] ?? 0),
                    'id_medico' => $this->idMedico($args['medico'] ?? 0),
                    'fecha_desde' => $args['fecha_desde'] ?? '',
                    'fecha_hasta' => $args['fecha_hasta'] ?? '',
                    'preferencias' => $args['preferencias'] ?? '',
                    'id_solicitud' => (int) ($args['id_solicitud'] ?? 0),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('listar_usuarios', 'Consulta cuentas de usuario y su rol.', ['USU_GESTIONAR'], $objeto([
                'nombre' => $texto('Parte del usuario o del correo.'),
            ]), function (array $args) {
                $lista = (new CtrlUsuario())->listarUsuarios(trim((string) ($args['nombre'] ?? '')));

                return $this->json(['ok' => true, 'datos' => ['usuarios' => $lista['usuarios'], 'roles' => $lista['roles']]]);
            }),
            $this->herramienta('guardar_usuario', 'Crea o edita una cuenta. Al crear, la respuesta incluye la contraseña temporal. vinculo es medico:ID o paciente:ID.', ['USU_GESTIONAR'], $objeto([
                'id_usuario' => $entero('Vacío al crear.'),
                'username' => $texto('Usuario, 3 a 40 caracteres.'),
                'email' => $texto('Correo.'),
                'id_rol' => $entero('Id del rol.'),
                'vinculo' => $texto('medico:3, paciente:5 o vacío.'),
                'estado' => $bandera('Activa. Por defecto true.'),
            ], ['username', 'email', 'id_rol']), function (array $args) {
                $control = new CtrlUsuario();
                $ok = $control->guardarUsuario([
                    'id_usuario' => (int) ($args['id_usuario'] ?? 0),
                    'username' => $args['username'] ?? '',
                    'email' => $args['email'] ?? '',
                    'id_rol' => (int) ($args['id_rol'] ?? 0),
                    'vinculo' => $args['vinculo'] ?? '',
                    'estado' => $this->marca($args['estado'] ?? true),
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('cambiar_estado_usuario', 'Activa o desactiva una cuenta. No se puede desactivar la propia.', ['USU_GESTIONAR'], $objeto([
                'id_usuario' => $entero('Id de la cuenta.'),
                'activo' => $bandera('true activa, false desactiva.'),
            ], ['id_usuario', 'activo']), function (array $args) {
                $control = new CtrlUsuario();
                $ok = $control->guardarUsuario([
                    'accion' => 'estado',
                    'id_usuario' => (int) ($args['id_usuario'] ?? 0),
                    'estado' => ($args['activo'] ?? false) === true,
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('listar_roles', 'Consulta los roles. Un rol no se elimina.', ['ROL_GESTIONAR', 'USU_GESTIONAR'], $objeto([]), function (array $args) {
                return $this->json(['ok' => true, 'datos' => (new CtrlRol())->listarRolesYPermisos()]);
            }),
            $this->herramienta('consultar_bitacora', 'Últimos movimientos de la bitácora.', ['BIT_CONSULTAR'], $objeto([
                'modulo' => $texto('Módulo, por ejemplo Citas o Pacientes.'),
                'desde' => $texto('Y-m-d opcional.'),
                'hasta' => $texto('Y-m-d opcional.'),
            ]), function (array $args) {
                $datos = (new Bitacora())->filtrar([
                    'modulo' => trim((string) ($args['modulo'] ?? '')),
                    'desde' => trim((string) ($args['desde'] ?? '')),
                    'hasta' => trim((string) ($args['hasta'] ?? '')),
                ], 1, 20);

                return $this->json(['ok' => true, 'total' => $datos['total'], 'datos' => $datos['registros']]);
            }),
            $this->herramienta('ver_mis_citas', 'Citas y próxima cita del paciente que inició sesión.', ['POR_PACIENTE'], $objeto([]), function (array $args) {
                $control = new CtrlReserva();
                $datos = $control->datosInicio();

                return $this->json(['ok' => true, 'datos' => [
                    'perfil_completo' => $datos['paciente'] !== null,
                    'proxima' => $datos['proxima'],
                    'grupos' => $datos['grupos'],
                ]]);
            }),
            $this->herramienta('reservar_mi_cita', 'El paciente reserva un cupo libre de una especialidad. Antes conviene consultar_cupos.', ['POR_PACIENTE'], $objeto([
                'especialidad' => $texto('Id o nombre.'),
                'medico' => $texto('Id, código o nombre del médico del cupo.'),
                'fecha' => $texto('Y-m-d.'),
                'hora_inicio' => $texto('HH:MM.'),
                'motivo' => $texto('Motivo opcional.'),
            ], ['especialidad', 'medico', 'fecha', 'hora_inicio']), function (array $args) {
                $control = new CtrlReserva();
                $ok = $control->reservar([
                    'id_especialidad' => $this->idEspecialidad($args['especialidad'] ?? 0),
                    'id_medico' => $this->idMedico($args['medico'] ?? 0),
                    'fecha' => $args['fecha'] ?? '',
                    'hora_inicio' => $args['hora_inicio'] ?? '',
                    'motivo' => $args['motivo'] ?? '',
                ]);

                return $this->resultado($control, $ok);
            }),
            $this->herramienta('responder_mi_cita', 'El paciente confirma o cancela una cita propia.', ['POR_PACIENTE'], $objeto([
                'codigo' => $texto('Código CM-0000.'),
                'respuesta' => $texto('confirmar o cancelar.'),
            ], ['codigo', 'respuesta']), function (array $args) {
                $control = new CtrlReserva();
                $ok = $control->responderCita((string) ($args['codigo'] ?? ''), (string) ($args['respuesta'] ?? ''));

                return $this->resultado($control, $ok);
            }),
        ];
    }

    private function herramienta(string $nombre, string $descripcion, array $permiso, array $parametros, \Closure $ejecutar): array
    {
        return compact('nombre', 'descripcion', 'permiso', 'parametros', 'ejecutar');
    }

    private function permite(array $permisos): bool
    {
        foreach ($permisos as $codigo) {
            if (Session::hasPermission($codigo)) {
                return true;
            }
        }

        return false;
    }

    private function resultado(object $control, bool $ok, mixed $datos = null): string
    {
        $salida = ['ok' => $ok, 'mensaje' => (string) ($control->mensaje ?? '')];
        if (!$ok && isset($control->citasAfectadas) && (int) $control->citasAfectadas > 0) {
            $salida['citas_afectadas'] = (int) $control->citasAfectadas;
            $salida['repetir_con_confirmado'] = true;
        }
        if ($ok && $datos !== null) {
            $salida['datos'] = $datos;
        }

        return $this->json($salida);
    }

    private function marca(mixed $valor): string
    {
        return in_array($valor, [true, 1, '1', 'true'], true) ? '1' : '0';
    }

    private function idsEspecialidad(mixed $valor): array
    {
        if (!is_array($valor)) {
            $valor = [$valor];
        }
        $ids = [];
        foreach ($valor as $item) {
            $id = $this->idEspecialidad($item);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function idEspecialidad(mixed $valor): int
    {
        if (is_int($valor) || (is_string($valor) && ctype_digit($valor))) {
            return (int) $valor;
        }
        $nombre = mb_strtolower(trim((string) $valor));
        if ($nombre === '') {
            return 0;
        }
        foreach ((new Especialidad())->listar() as $fila) {
            if (mb_strtolower($fila['nombre']) === $nombre) {
                return (int) $fila['id_especialidad'];
            }
        }

        return 0;
    }

    private function idMedico(mixed $valor): int
    {
        if (is_int($valor) || (is_string($valor) && ctype_digit($valor))) {
            return (int) $valor;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return 0;
        }
        foreach ((new Medico())->buscar(['estado' => null]) as $fila) {
            $nombre = $fila['nombres'] . ' ' . $fila['apellidos'];
            if (strcasecmp($fila['codigo'], $texto) === 0 || strcasecmp($nombre, $texto) === 0) {
                return (int) $fila['id_medico'];
            }
        }

        return 0;
    }

    private function idPaciente(mixed $valor): int
    {
        if (is_int($valor) || (is_string($valor) && ctype_digit($valor))) {
            return (int) $valor;
        }
        $fila = (new Paciente())->buscarPaciente((string) $valor);

        return $fila === null ? 0 : (int) $fila['id_paciente'];
    }

    private function json(mixed $datos): string
    {
        $json = json_encode($this->recortar($datos), JSON_UNESCAPED_UNICODE);

        return $json === false ? '{"ok":false,"mensaje":"No se pudo preparar la respuesta."}' : $json;
    }

    private function recortar(mixed $datos, int $profundidad = 0): mixed
    {
        if ($profundidad > 5) {
            return null;
        }
        if (!is_array($datos)) {
            return is_string($datos) ? mb_substr($datos, 0, 500) : $datos;
        }
        $lista = array_is_list($datos);
        if ($lista && count($datos) > 40) {
            $datos = array_slice($datos, 0, 40);
            $datos[] = ['aviso' => 'Hay más filas. Refine la consulta.'];
        }
        $salida = [];
        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && preg_match('/clave|password|contrase|hash|token/i', $clave)) {
                continue;
            }
            $salida[$clave] = $this->recortar($valor, $profundidad + 1);
        }

        return $salida;
    }
}
