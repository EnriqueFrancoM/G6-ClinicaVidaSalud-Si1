<?php
// Tabla de rutas: [método, ruta, Controlador@acción, permiso requerido]
// permiso: null = pública, 'auth' = solo requiere sesión, 'CODIGO' = permiso de la tabla permiso.

use App\Controllers\Seguridad\AuthController;
use App\Controllers\DashboardController;

return [
    // P1 Seguridad y Acceso
    ['GET',  '/',       [AuthController::class, 'showLogin'], null],
    ['GET',  '/login',  [AuthController::class, 'showLogin'], null],
    ['POST', '/login',  [AuthController::class, 'login'],     null],     // CU01
    ['POST', '/logout', [AuthController::class, 'logout'],    'auth'],   // CU02

    ['GET',  '/dashboard', [DashboardController::class, 'index'], 'auth'],

    // Próximos casos de uso (agregar aquí a medida que se implementen):
    // ['GET', '/usuarios',       [UsuarioController::class, 'index'],      'USU_GESTIONAR'], // CU03
    // ['GET', '/roles',          [RolController::class, 'index'],          'ROL_GESTIONAR'], // CU04
    // ['GET', '/bitacora',       [BitacoraController::class, 'index'],     'BIT_CONSULTAR'], // CU06
    // ['GET', '/especialidades', [EspecialidadController::class, 'index'], 'ESP_GESTIONAR'], // CU07
    // ['GET', '/medicos',        [MedicoController::class, 'index'],       'MED_GESTIONAR'], // CU08
    // ['GET', '/horarios',       [HorarioController::class, 'index'],      'HOR_GESTIONAR'], // CU09
    // ['GET', '/pacientes',      [PacienteController::class, 'index'],     'PAC_GESTIONAR'], // CU10
];
