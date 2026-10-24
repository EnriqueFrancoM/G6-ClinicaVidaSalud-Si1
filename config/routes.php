<?php
// Tabla de rutas: [método, ruta, clase@operación, permiso requerido]
// permiso: null = pública, 'auth' = solo requiere sesión, 'CODIGO' = permiso de la tabla permiso.

use App\Controllers\GestionClinica\UI_Especialidades;
use App\Controllers\GestionClinica\UI_Medicos;
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

    // Próximos casos de uso (agregar aquí a medida que se implementen):
    // ['GET', '/horarios',       [UI_Horarios::class, ...],          'HOR_GESTIONAR'], // CU09
    // ['GET', '/pacientes',      [UI_Pacientes::class, ...],         'PAC_GESTIONAR'], // CU10
];
