<?php
// Tabla de rutas: [método, ruta, clase@operación, permiso requerido]
// permiso: null = pública, 'auth' = solo requiere sesión, 'CODIGO' = permiso de la tabla permiso.

use App\Controllers\Citas\UI_AgendaMedica;
use App\Controllers\Citas\UI_CancelarCita;
use App\Controllers\Citas\UI_Comprobante;
use App\Controllers\Citas\UI_EstadoCita;
use App\Controllers\Citas\UI_HistorialCitas;
use App\Controllers\Citas\UI_ListaEspera;
use App\Controllers\Citas\UI_ProgramarCita;
use App\Controllers\Citas\UI_ReprogramarCita;
use App\Controllers\GestionClinica\UI_Especialidades;
use App\Controllers\GestionClinica\UI_Horarios;
use App\Controllers\GestionClinica\UI_Medicos;
use App\Controllers\GestionClinica\UI_Pacientes;
use App\Controllers\Portal\UI_MisCitas;
use App\Controllers\Portal\UI_PerfilPaciente;
use App\Controllers\Reportes\ServicioRecordatorios;
use App\Controllers\Reportes\UI_Indicadores;
use App\Controllers\Reportes\UI_Reportes;
use App\Controllers\Seguridad\UI_Bitacora;
use App\Controllers\Seguridad\UI_CambiarContrasena;
use App\Controllers\Seguridad\UI_GestionarUsuarios;
use App\Controllers\Seguridad\UI_IniciarSesion;
use App\Controllers\Seguridad\UI_PanelPrincipal;
use App\Controllers\Seguridad\UI_RolesPermisos;

return [
    // P1 Seguridad y Acceso — CU01 mensaje 1
    ['GET',  '/',       [UI_IniciarSesion::class, 'ingresarCredenciales'], null],
    ['GET',  '/login',  [UI_IniciarSesion::class, 'ingresarCredenciales'], null],
    ['POST', '/login',  [UI_IniciarSesion::class, 'ingresarCredenciales'], null],

    // CU01 mensaje 8 / CU02 mensaje 1
    ['GET',  '/dashboard', [UI_PanelPrincipal::class, 'mostrarMenuSegunRol'], 'auth'],
    ['POST', '/logout',    [UI_PanelPrincipal::class, 'seleccionarCerrarSesion'], 'auth'],

    // CU05 mensaje 1 (desde el menú del perfil)
    ['GET',  '/cambiar-contrasena', [UI_CambiarContrasena::class, 'ingresar'], 'auth'],
    ['POST', '/cambiar-contrasena', [UI_CambiarContrasena::class, 'ingresar'], 'auth'],

    // CU03 mensajes 1 y 4
    ['GET',  '/usuarios', [UI_GestionarUsuarios::class, 'abrirUsuarios'],       'USU_GESTIONAR'],
    ['POST', '/usuarios', [UI_GestionarUsuarios::class, 'registrarOModificar'], 'USU_GESTIONAR'],

    // CU04 mensajes 1 y 5
    ['GET',  '/roles', [UI_RolesPermisos::class, 'abrirRoles'], 'ROL_GESTIONAR'],
    ['POST', '/roles', [UI_RolesPermisos::class, 'editarRol'],  'ROL_GESTIONAR'],

    // CU06 mensajes 1, 4 y 7
    ['GET', '/bitacora',          [UI_Bitacora::class, 'abrirBitacora'],  'BIT_CONSULTAR'],
    ['GET', '/bitacora/filtrar',  [UI_Bitacora::class, 'aplicarFiltros'], 'BIT_CONSULTAR'],
    ['GET', '/bitacora/exportar', [UI_Bitacora::class, 'exportar'],       'BIT_CONSULTAR'],

    // P2 Gestión Clínica — CU07 mensajes 1 y 4
    ['GET',  '/especialidades', [UI_Especialidades::class, 'abrirEspecialidades'], 'ESP_GESTIONAR'],
    ['POST', '/especialidades', [UI_Especialidades::class, 'registrarOModificar'], 'ESP_GESTIONAR'],

    // CU08 mensajes 1 y 5
    ['GET',  '/medicos', [UI_Medicos::class, 'abrirMedicos'],        'MED_GESTIONAR'],
    ['POST', '/medicos', [UI_Medicos::class, 'registrarOModificar'], 'MED_GESTIONAR'],

    // CU09 mensajes 1 y 4
    ['GET',  '/horarios', [UI_Horarios::class, 'seleccionarMedico'], 'HOR_GESTIONAR'],
    ['POST', '/horarios', [UI_Horarios::class, 'definirHorario'],    'HOR_GESTIONAR'],
    // CU09 «extend» Registrar bloqueo de agenda
    ['POST', '/horarios/bloqueo',        [UI_Horarios::class, 'registrarBloqueo'], 'HOR_GESTIONAR'],
    ['POST', '/horarios/bloqueo/quitar', [UI_Horarios::class, 'quitarBloqueo'],    'HOR_GESTIONAR'],

    // CU10 mensajes 1, 4 y 11
    ['GET',  '/pacientes',       [UI_Pacientes::class, 'buscarPaciente'],      'PAC_GESTIONAR'],
    ['POST', '/pacientes',       [UI_Pacientes::class, 'registrarOModificar'], 'PAC_GESTIONAR'],
    ['GET',  '/pacientes/ficha', [UI_Pacientes::class, 'mostrarFicha'],        'PAC_GESTIONAR'],

    // P3 Gestión de Citas — CU11 solicitar(programar) → programar(datos)
    ['GET',  '/citas/programar', [UI_ProgramarCita::class, 'solicitar'], 'CIT_GESTIONAR'],
    ['POST', '/citas/programar', [UI_ProgramarCita::class, 'solicitar'], 'CIT_GESTIONAR'],
    // CU12 solicitar(reprogramar) → reprogramar(datos)
    ['GET',  '/citas/reprogramar', [UI_ReprogramarCita::class, 'solicitar'], 'CIT_GESTIONAR'],
    ['POST', '/citas/reprogramar', [UI_ReprogramarCita::class, 'solicitar'], 'CIT_GESTIONAR'],
    // CU13 solicitar(cancelar) → cancelar(datos)
    ['GET',  '/citas/cancelar', [UI_CancelarCita::class, 'solicitar'], 'CIT_GESTIONAR'],
    ['POST', '/citas/cancelar', [UI_CancelarCita::class, 'solicitar'], 'CIT_GESTIONAR'],
    // CU14 solicitar(cambiarEstado) → cambiarEstado(datos). Recepción (CIT_GESTIONAR) o médico (CIT_ATENDER):
    // lo comprueba CtrlEstadoCita.validarAcceso().
    ['GET',  '/citas/estado', [UI_EstadoCita::class, 'solicitar'], 'auth'],
    ['POST', '/citas/estado', [UI_EstadoCita::class, 'solicitar'], 'auth'],
    // RF24: destino del QR del comprobante (llegada del paciente)
    ['GET',  '/citas/llegada',     [UI_EstadoCita::class, 'registrarLlegada'], 'CIT_GESTIONAR'],
    ['GET',  '/citas/comprobante', [UI_Comprobante::class, 'mostrar'],         'auth'],
    // CU15 solicitar(consultarAgenda) → consultarAgenda(datos)
    ['GET',  '/agenda', [UI_AgendaMedica::class, 'solicitar'], 'AGE_CONSULTAR'],
    // CU16 solicitar(consultarHistorial) → consultarHistorial(datos)
    ['GET',  '/citas/historial', [UI_HistorialCitas::class, 'solicitar'], 'CIT_GESTIONAR'],
    // CU21 solicitar(registrarSolicitud) → registrarSolicitud(datos)
    ['GET',  '/lista-espera', [UI_ListaEspera::class, 'solicitar'], 'CIT_GESTIONAR'],
    ['POST', '/lista-espera', [UI_ListaEspera::class, 'solicitar'], 'CIT_GESTIONAR'],

    // P4 Portal del Paciente — CU17 solicitar(guardarPerfil) → guardarPerfil(datos); /registro es el paso 1
    ['GET',  '/registro',      [UI_PerfilPaciente::class, 'solicitar'], null],
    ['POST', '/registro',      [UI_PerfilPaciente::class, 'solicitar'], null],
    ['GET',  '/portal/perfil', [UI_PerfilPaciente::class, 'solicitar'], 'POR_PACIENTE'],
    ['POST', '/portal/perfil', [UI_PerfilPaciente::class, 'solicitar'], 'POR_PACIENTE'],
    // CU18 solicitar(reservar) → reservar(datos)
    ['GET',  '/portal/citas', [UI_MisCitas::class, 'solicitar'], 'POR_PACIENTE'],
    ['POST', '/portal/citas', [UI_MisCitas::class, 'solicitar'], 'POR_PACIENTE'],

    // P5 Reportes y Notificaciones — CU19 solicitar(generarReporte) → generarReporte(datos)
    ['GET',  '/reportes', [UI_Reportes::class, 'solicitar'], 'REP_GENERAR'],
    // CU20 solicitar(procesarPendientes) → procesarPendientes(datos); seguimiento de recordatorios (PN12)
    ['GET',  '/recordatorios', [ServicioRecordatorios::class, 'solicitar'], 'REP_GENERAR'],
    ['POST', '/recordatorios', [ServicioRecordatorios::class, 'solicitar'], 'REP_GENERAR'],
    // RF21: enlace del recordatorio para confirmar o cancelar (firmado)
    ['GET',  '/recordatorio/responder', [ServicioRecordatorios::class, 'responder'], null],
    // CU22 solicitar(calcularIndicadores) → calcularIndicadores(datos)
    ['GET',  '/indicadores', [UI_Indicadores::class, 'solicitar'], 'REP_GENERAR'],
];
