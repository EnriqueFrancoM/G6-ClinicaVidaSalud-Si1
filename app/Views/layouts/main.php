<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$usuario = Session::user();

// Menú lateral: [permiso, ruta, texto]. Solo se muestran las opciones permitidas al rol.
$menu = [
    ['auth',          '/dashboard',      'Inicio'],
    ['USU_GESTIONAR', '/usuarios',       'Usuarios'],
    ['ROL_GESTIONAR', '/roles',          'Roles y permisos'],
    ['BIT_CONSULTAR', '/bitacora',       'Bitácora'],
    ['ESP_GESTIONAR', '/especialidades', 'Especialidades'],
    ['MED_GESTIONAR', '/medicos',        'Médicos'],
    ['HOR_GESTIONAR', '/horarios',       'Horarios'],
    ['PAC_GESTIONAR', '/pacientes',      'Pacientes'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(App::config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= App::url('/assets/css/app.css') ?>">
</head>
<body>
<header class="topbar">
    <strong><?= e(App::config('app.name')) ?></strong>
    <div>
        <?= e($usuario['username'] ?? '') ?> (<?= e($usuario['rol'] ?? '') ?>)
        <form method="post" action="<?= App::url('/logout') ?>" class="inline">
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <button type="submit">Cerrar sesión</button>
        </form>
    </div>
</header>
<div class="layout">
    <nav class="sidebar">
        <?php foreach ($menu as [$permiso, $ruta, $texto]): ?>
            <?php if ($permiso === 'auth' || Session::hasPermission($permiso)): ?>
                <a href="<?= App::url($ruta) ?>"><?= e($texto) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <main class="content">
        <?php if ($msg = Session::flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($msg = Session::flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>
        <?= $content ?>
    </main>
</div>
<script src="<?= App::url('/assets/js/app.js') ?>"></script>
</body>
</html>
