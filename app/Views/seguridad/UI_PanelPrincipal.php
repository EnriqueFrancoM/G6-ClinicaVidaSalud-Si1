<?php

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
?>
<section class="hoja hoja-dia">
    <header class="hoja-cabecera">
        <div>
            <h1><?= e($titulo) ?></h1>
            <p class="lede"><?= e($alcance) ?></p>
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
