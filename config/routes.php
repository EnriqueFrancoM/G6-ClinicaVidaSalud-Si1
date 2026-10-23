<?php
// Tabla de rutas: [método, ruta, clase@operación, permiso requerido]
// permiso: null = pública, 'auth' = solo requiere sesión, 'CODIGO' = permiso de la tabla permiso.

use App\Controllers\Seguridad\UI_GestionarUsuarios;
use App\Controllers\Seguridad\UI_IniciarSesion;
use App\Controllers\Seguridad\UI_PanelPrincipal;
use App\Controllers\Seguridad\UI_RecuperarClave;

return [
    // P1 Seguridad y Acceso — CU01
    ['GET',  '/',       [UI_IniciarSesion::class, 'ingresarCredenciales'], null],
    ['GET',  '/login',  [UI_IniciarSesion::class, 'ingresarCredenciales'], null],
    ['POST', '/login',  [UI_IniciarSesion::class, 'ingresarCredenciales'], null],

    // CU01 mensaje 8 / CU02 mensaje 1
    ['GET',  '/dashboard', [UI_PanelPrincipal::class, 'mostrarMenuSegunRol'], 'auth'],
    ['POST', '/logout',    [UI_PanelPrincipal::class, 'seleccionarCerrarSesion'], 'auth'],

    ['GET',  '/recuperar',        [UI_RecuperarClave::class, 'solicitar'], null],
    ['POST', '/recuperar',        [UI_RecuperarClave::class, 'solicitar'], null],
    ['GET',  '/recuperar/codigo', [UI_RecuperarClave::class, 'ingresarCodigo'], null],
    ['POST', '/recuperar/codigo', [UI_RecuperarClave::class, 'ingresarCodigo'], null],
    ['GET',  '/recuperar/clave',  [UI_RecuperarClave::class, 'definirClave'], null],
    ['POST', '/recuperar/clave',  [UI_RecuperarClave::class, 'definirClave'], null],

    // CU03: el administrador desbloquea la cuenta. El alta y la edición quedan para después.
    ['GET',  '/usuarios',            [UI_GestionarUsuarios::class, 'abrirUsuarios'], 'USU_GESTIONAR'],
    ['POST', '/usuarios/desbloquear',[UI_GestionarUsuarios::class, 'desbloquear'], 'USU_GESTIONAR'],

    // Próximos casos de uso (agregar aquí a medida que se implementen):
    // ['GET', '/roles',          [UI_RolesPermisos::class, 'abrirRoles'],       'ROL_GESTIONAR'], // CU04
    // ['GET', '/bitacora',       [CtrlBitacora::class, 'consultar'],            'BIT_CONSULTAR'], // CU06
    // ['GET', '/especialidades', [UI_Especialidades::class, 'abrirEspecialidades'], 'ESP_GESTIONAR'], // CU07
    // ['GET', '/medicos',        [UI_Medicos::class, 'abrirMedicos'],           'MED_GESTIONAR'], // CU08
    // ['GET', '/horarios',       [UI_Horarios::class, 'definirHorario'],        'HOR_GESTIONAR'], // CU09
    // ['GET', '/pacientes',      [UI_Pacientes::class, 'buscarPaciente'],       'PAC_GESTIONAR'], // CU10
];
