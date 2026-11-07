<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$citas = $citas ?? [];
$rol = (string) ($usuario['rol'] ?? '');
$hoy = new DateTime('today');
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$titulo = ucfirst($dias[(int) $hoy->format('w')]) . ' ' . $hoy->format('j') . ' de ' . $meses[(int) $hoy->format('n')];
$etiqueta = static function (string $estado): string {
    return match ($estado) {
        'PROGRAMADA' => 'Programada',
        'CONFIRMADA' => 'Confirmada',
        'ATENDIDA' => 'Atendida',
        'CANCELADA' => 'Cancelada',
        'INASISTENCIA' => 'Inasistencia',
        default => $estado,
    };
};
$clase = static function (string $estado): string {
    return 'estado-pill estado-' . strtolower($estado);
};
$alcance = match ($rol) {
    'Médico' => 'Sus citas de hoy.',
    'Paciente' => 'Sus citas de hoy.',
    default => 'Citas de hoy en la clínica.',
};

$conteo = ['PROGRAMADA' => 0, 'CONFIRMADA' => 0, 'ATENDIDA' => 0, 'CANCELADA' => 0, 'INASISTENCIA' => 0];
$porEspecialidad = [];
foreach ($citas as $cita) {
    $estado = (string) $cita['estado'];
    if (isset($conteo[$estado])) {
        $conteo[$estado]++;
    }
    $nombre = (string) $cita['especialidad'];
    $porEspecialidad[$nombre] = ($porEspecialidad[$nombre] ?? 0) + 1;
}
arsort($porEspecialidad);
$total = count($citas);
$pico = max(1, ...array_values($conteo));
$porAtender = $conteo['PROGRAMADA'] + $conteo['CONFIRMADA'];
$proxima = null;
foreach ($citas as $cita) {
    if (in_array($cita['estado'], ['PROGRAMADA', 'CONFIRMADA'], true)) {
        $proxima = $cita;
        break;
    }
}

$accesos = array_values(array_filter([
    Session::hasPermission('USU_GESTIONAR') ? ['/usuarios', 'Usuarios', 'Cuentas y accesos'] : null,
    Session::hasPermission('ROL_GESTIONAR') ? ['/roles', 'Roles', 'Matriz de permisos'] : null,
    Session::hasPermission('ESP_GESTIONAR') ? ['/especialidades', 'Especialidades', 'Catálogo clínico'] : null,
    Session::hasPermission('MED_GESTIONAR') ? ['/medicos', 'Médicos', 'Registro profesional'] : null,
    Session::hasPermission('HOR_GESTIONAR') ? ['/horarios', 'Horarios', 'Semana y bloqueos'] : null,
    Session::hasPermission('PAC_GESTIONAR') ? ['/pacientes', 'Pacientes', 'Fichas y búsqueda'] : null,
    Session::hasPermission('BIT_CONSULTAR') ? ['/bitacora', 'Bitácora', 'Movimientos del día'] : null,
    Session::hasPermission('REP_GENERAR') ? ['/indicadores', 'Indicadores', 'Ocupación y demanda'] : null,
]));
?>
<div class="panel-dia" data-ui="UI_PanelPrincipal">
    <section class="hoja">
        <header class="hoja-cabecera">
            <div>
                <h1><?= e($titulo) ?></h1>
                <p class="lede"><?= e($alcance) ?></p>
            </div>
        </header>

        <?php if ($accesos !== []): ?>
            <div class="stat-row">
                <?php foreach ($accesos as $acceso): ?>
                    <a class="stat-card lift-card acceso-admin" data-spotlight href="<?= App::url($acceso[0]) ?>">
                        <span>Administración</span>
                        <strong><?= e($acceso[1]) ?></strong>
                        <small><?= e($acceso[2]) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="stat-row">
            <article class="stat-card lift-card" data-spotlight>
                <span>Citas de hoy</span>
                <strong><?= $total ?></strong>
            </article>
            <article class="stat-card lift-card" data-spotlight>
                <span>Por atender</span>
                <strong><?= $porAtender ?></strong>
            </article>
            <article class="stat-card lift-card" data-spotlight>
                <span>Atendidas</span>
                <strong><?= $conteo['ATENDIDA'] ?></strong>
            </article>
            <article class="stat-card lift-card" data-spotlight>
                <span>Cerradas</span>
                <strong><?= $conteo['CANCELADA'] + $conteo['INASISTENCIA'] ?></strong>
            </article>
        </div>

        <div class="dash-grid">
            <section class="panel-card lift-card" data-spotlight>
                <header class="chart-head">
                    <div>
                        <h2>Estados del día</h2>
                        <p>Misma lectura que el panel de indicadores, limitada a hoy.</p>
                    </div>
                    <strong class="chart-total"><?= $total ?></strong>
                </header>
                <div class="bars" role="img" aria-label="Citas de hoy por estado">
                    <?php foreach ($conteo as $estado => $cantidad): ?>
                        <?php $alto = (int) round($cantidad / $pico * 120); ?>
                        <div class="bar <?= e(strtolower($estado)) ?>">
                            <span style="height: <?= max(6, $alto) ?>px"></span>
                            <strong><?= $cantidad ?></strong>
                            <small><?= e($etiqueta($estado)) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($porEspecialidad) > 1): ?>
                    <h2 class="subtitulo">Por especialidad</h2>
                    <ul class="flujo">
                        <?php foreach ($porEspecialidad as $nombre => $cantidad): ?>
                            <li>
                                <span><?= e($nombre) ?></span>
                                <span class="track"><i style="width: <?= $total === 0 ? 0 : (int) round($cantidad / $total * 100) ?>%"></i></span>
                                <strong><?= $cantidad ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <aside class="panel-card lift-card" data-spotlight>
                <?php if ($proxima === null): ?>
                    <h2>Siguiente turno</h2>
                    <p class="vacio-dia">No queda ninguna cita programada o confirmada para hoy.</p>
                <?php else: ?>
                    <p class="visita-kicker">Siguiente turno</p>
                    <p class="visita-hora"><?= e($proxima['hora']) ?></p>
                    <p class="visita-nombre"><?= e($rol === 'Paciente' ? $proxima['medico'] : $proxima['paciente']) ?></p>
                    <p class="visita-detalle"><?= e($proxima['especialidad']) ?><?= $proxima['motivo'] ? ' · ' . e($proxima['motivo']) : '' ?></p>
                    <span class="<?= e($clase($proxima['estado'])) ?>"><?= e($etiqueta($proxima['estado'])) ?></span>
                <?php endif; ?>
            </aside>
        </div>
    </section>

    <section class="hoja">
        <header class="hoja-cabecera">
            <div>
                <h1>Agenda</h1>
                <p class="lede"><?= $total === 0 ? 'El día está libre.' : $total . ($total === 1 ? ' cita en el día.' : ' citas en el día.') ?></p>
            </div>
        </header>
        <?php if ($citas === []): ?>
            <p class="vacio-dia">No hay citas en la agenda de hoy.</p>
        <?php else: ?>
            <ol class="agenda-dia">
                <?php foreach ($citas as $cita): ?>
                    <li>
                        <time datetime="<?= e($cita['hora']) ?>"><?= e($cita['hora']) ?></time>
                        <div class="quien">
                            <strong><?= e($rol === 'Paciente' ? $cita['medico'] : $cita['paciente']) ?></strong>
                            <span><?= e($rol === 'Paciente' ? $cita['especialidad'] : $cita['medico'] . ' · ' . $cita['especialidad']) ?><?= $cita['motivo'] ? ' · ' . e($cita['motivo']) : '' ?></span>
                        </div>
                        <span class="<?= e($clase($cita['estado'])) ?>"><?= e($etiqueta($cita['estado'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
