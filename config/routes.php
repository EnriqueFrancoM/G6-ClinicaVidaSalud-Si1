<?php
// Tabla de rutas: [método, ruta, clase@operación, permiso requerido]
// permiso: null = pública, 'auth' = solo requiere sesión, 'CODIGO' = permiso de la tabla permiso.

use App\Controllers\Seguridad\UI_GestionarUsuarios;
use App\Controllers\Seguridad\UI_IniciarSesion;
use App\Controllers\Seguridad\UI_PanelPrincipal;

return [
    // P1 Seguridad y Acceso — CU01 mensaje 1
    ['GET',  '/',       [UI_IniciarSesion::class, 'ingresarCredenciales'], null],
    ['GET',  '/login',  [UI_IniciarSesion::class, 'ingresarCredenciales'], null],
    ['POST', '/login',  [UI_IniciarSesion::class, 'ingresarCredenciales'], null],

    // CU01 mensaje 8 / CU02 mensaje 1
    ['GET',  '/dashboard', [UI_PanelPrincipal::class, 'mostrarMenuSegunRol'], 'auth'],
    ['POST', '/logout',    [UI_PanelPrincipal::class, 'seleccionarCerrarSesion'], 'auth'],

    // CU03 mensajes 1 y 4
    ['GET',  '/usuarios', [UI_GestionarUsuarios::class, 'abrirUsuarios'],       'USU_GESTIONAR'],
    ['POST', '/usuarios', [UI_GestionarUsuarios::class, 'registrarOModificar'], 'USU_GESTIONAR'],

    // Próximos casos de uso (agregar aquí a medida que se implementen):
    // ['GET', '/roles',          [UI_RolesPermisos::class, ...],     'ROL_GESTIONAR'], // CU04
    // ['GET', '/bitacora',       [UI_Bitacora::class, ...],          'BIT_CONSULTAR'], // CU06
    // ['GET', '/especialidades', [UI_Especialidades::class, ...],    'ESP_GESTIONAR'], // CU07
    // ['GET', '/medicos',        [UI_Medicos::class, ...],           'MED_GESTIONAR'], // CU08
    // ['GET', '/horarios',       [UI_Horarios::class, ...],          'HOR_GESTIONAR'], // CU09
    // ['GET', '/pacientes',      [UI_Pacientes::class, ...],         'PAC_GESTIONAR'], // CU10
];
