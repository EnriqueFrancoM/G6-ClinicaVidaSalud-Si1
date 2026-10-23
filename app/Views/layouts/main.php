<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$usuario = Session::user();
$partes = preg_split('/[.\s_-]+/', (string) ($usuario['username'] ?? '')) ?: [];
$iniciales = '';
foreach (array_slice(array_filter($partes), 0, 2) as $parte) {
    $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = rtrim((string) parse_url(App::config('app.base_url', ''), PHP_URL_PATH), '/');
$menu = [
    ['auth',          '/dashboard',      'Inicio',          'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
    ['USU_GESTIONAR', '/usuarios',       'Usuarios',        'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 20a7 7 0 0 1 14 0'],
    ['ROL_GESTIONAR', '/roles',          'Roles y permisos','M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z'],
    ['BIT_CONSULTAR', '/bitacora',       'Bitácora',        'M6 4h12v16H6zM9 8h6M9 12h6M9 16h4'],
    ['ESP_GESTIONAR', '/especialidades', 'Especialidades',  'M12 3v18M5 8h14M7 16h10'],
    ['MED_GESTIONAR', '/medicos',        'Médicos',         'M8 21v-2a4 4 0 0 1 8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    ['HOR_GESTIONAR', '/horarios',       'Horarios',        'M12 7v5l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z'],
    ['PAC_GESTIONAR', '/pacientes',      'Pacientes',       'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM16 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM3 19a5 5 0 0 1 10 0M13 19a4 4 0 0 1 8 0'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(App::config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= App::url('/assets/css/app.css') ?>?v=<?= filemtime(BASE_PATH . '/public/assets/css/app.css') ?>">
</head>
<body class="app">
<div class="shell">
    <aside class="sidebar">
        <div>
            <div class="side-brand">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M12 7v10M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                Vida Salud
            </div>
            <label class="menu-search">
                <span class="sr-only">Buscar en el menú</span>
                <input type="search" data-menu-search placeholder="Buscar en el menú" autocomplete="off">
            </label>
            <nav>
                <?php foreach ($menu as [$permiso, $ruta, $texto, $trazo]): ?>
                    <?php if ($permiso === 'auth' || Session::hasPermission($permiso)): ?>
                        <?php $activo = rtrim($path, '/') === rtrim($base . $ruta, '/'); ?>
                        <a href="<?= App::url($ruta) ?>" data-menu-item<?= $activo ? ' class="active"' : '' ?>>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($trazo) ?>"/></svg>
                            <?= e($texto) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <form method="post" action="<?= App::url('/logout') ?>">
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <button type="submit" name="btnCerrarSesion">Cerrar sesión</button>
        </form>
    </aside>
    <div class="workspace">
        <header class="workspace-bar">
            <article class="user-card lift-card" data-spotlight>
                <span class="user-mark" aria-hidden="true"><?= e($iniciales !== '' ? $iniciales : 'VS') ?></span>
                <div class="user-copy">
                    <strong><?= e($usuario['username'] ?? '') ?></strong>
                    <span><?= e($usuario['rol'] ?? '') ?></span>
                </div>
            </article>
        </header>
        <main class="content">
            <?php if ($msg = Session::flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
            <?php if ($msg = Session::flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= App::url('/assets/js/app.js') ?>?v=<?= filemtime(BASE_PATH . '/public/assets/js/app.js') ?>"></script>
</body>
</html>
