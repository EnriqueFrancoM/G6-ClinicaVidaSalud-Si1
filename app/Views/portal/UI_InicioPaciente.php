<?php

use App\Core\App;
use App\Models\Cita;
use function App\Core\e;

$paciente = $paciente ?? null;
$proxima = $proxima ?? null;
$grupos = $grupos ?? [];
$especialidades = $especialidades ?? [];
$avisos = $avisos ?? [];
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$fechaLarga = static function (string $fecha) use ($dias): string {
    $momento = strtotime($fecha);
    return $dias[(int) date('w', $momento)] . ' ' . date('d/m/Y', $momento);
};
$propias = array_merge($grupos['pendientes'] ?? [], $grupos['confirmadas'] ?? []);
?>
<div class="portal-inicio" data-ui="UI_InicioPaciente">
    <section class="hoja" aria-labelledby="titulo-proxima">
        <header class="hoja-cabecera">
            <div>
                <h1 id="titulo-proxima">Próxima cita</h1>
                <p class="lede">La más cercana entre sus citas futuras, no solo las de hoy.</p>
            </div>
        </header>
        <?php if ($paciente === null): ?>
            <p class="aviso-rango">Complete <a href="<?= App::url('/portal/perfil') ?>">su perfil</a> para ver y reservar citas.</p>
        <?php elseif ($proxima === null): ?>
            <p class="vacio-dia">No tiene citas programadas o confirmadas a partir de ahora.</p>
            <a class="btn-primary btn-chico" href="<?= App::url('/portal/citas?reservar=1') ?>">Reservar cita</a>
        <?php else: ?>
            <article class="panel-card proxima-cita">
                <p class="visita-kicker"><?= e($fechaLarga($proxima['fecha'])) ?></p>
                <p class="visita-hora"><?= e($proxima['hora']) ?></p>
                <p class="visita-nombre"><?= e($proxima['medico']) ?></p>
                <p class="visita-detalle"><?= e($proxima['especialidad']) ?><?= $proxima['motivo'] ? ' · ' . e($proxima['motivo']) : '' ?></p>
                <span class="estado-pill estado-<?= e(strtolower($proxima['estado'])) ?>"><?= e(Cita::ESTADOS[$proxima['estado']] ?? $proxima['estado']) ?></span>
                <p class="nota">Código <?= e($proxima['codigo']) ?>.</p>
                <div class="modal-acciones">
                    <a class="btn-borde" href="<?= App::url('/citas/comprobante?codigo=' . rawurlencode($proxima['codigo'])) ?>">Ver comprobante</a>
                    <a class="btn-primary btn-chico" href="<?= App::url('/portal/citas') ?>">Mis citas</a>
                </div>
            </article>
        <?php endif; ?>
        <?php foreach ($avisos as $aviso): ?>
            <p class="aviso-rango">Recordatorio en el portal: cita <?= e($aviso['codigo']) ?> con <?= e($aviso['medico']) ?> el <?= e(date('d/m/Y', strtotime($aviso['fecha']))) ?> a las <?= e($aviso['hora_inicio']) ?>.</p>
        <?php endforeach; ?>
    </section>

    <section class="hoja" aria-labelledby="titulo-reservar">
        <header class="hoja-cabecera">
            <div>
                <h1 id="titulo-reservar">Reservar cita</h1>
                <p class="lede">Elija la especialidad y la fecha. Ver horarios muestra la hora y el médico para que usted elija.</p>
            </div>
        </header>

        <?php if ($especialidades === []): ?>
            <p class="vacio-dia">No hay especialidades activas.</p>
        <?php else: ?>
            <div class="tarjetas-medicos">
                <?php foreach ($especialidades as $especialidad): ?>
                    <article class="tarjeta-medico">
                        <h2><?= e($especialidad['nombre']) ?></h2>
                        <?php if ((int) $especialidad['medicos'] === 0): ?>
                            <p class="aviso-rango" data-sin-cobertura="<?= e($especialidad['nombre']) ?>">Sin médico activo con horario. No se ofrecen cupos.</p>
                        <?php else: ?>
                            <p><?= (int) $especialidad['medicos'] === 1 ? '1 médico con horario' : (int) $especialidad['medicos'] . ' médicos con horario' ?></p>
                            <a class="btn-borde" href="<?= App::url('/portal/citas?' . http_build_query([
                                'reservar' => 1,
                                'id_especialidad' => $especialidad['id_especialidad'],
                                'fecha' => date('Y-m-d'),
                            ])) ?>">Ver horarios</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="hoja" aria-labelledby="titulo-mis-citas">
        <header class="hoja-cabecera">
            <div>
                <h1 id="titulo-mis-citas">Mis citas</h1>
                <p class="lede">Solo las citas de su cuenta.</p>
            </div>
            <a class="btn-borde" href="<?= App::url('/portal/citas') ?>">Ver todas</a>
        </header>
        <?php if ($paciente === null): ?>
            <p class="vacio-dia">Su ficha todavía no está completa.</p>
        <?php elseif ($propias === []): ?>
            <p class="vacio-dia">No tiene citas pendientes ni confirmadas.</p>
        <?php else: ?>
            <ol class="agenda-dia mis-citas">
                <?php foreach ($propias as $cita): ?>
                    <li>
                        <time datetime="<?= e($cita['fecha'] . 'T' . $cita['hora_inicio']) ?>"><?= e($cita['hora_inicio']) ?></time>
                        <div class="quien">
                            <strong><?= e($cita['medico']) ?></strong>
                            <span><?= e($fechaLarga($cita['fecha'])) ?> · <?= e($cita['especialidad']) ?> · <?= e($cita['codigo']) ?></span>
                        </div>
                        <span class="estado-pill estado-<?= e(strtolower($cita['estado'])) ?>"><?= e(Cita::ESTADOS[$cita['estado']] ?? $cita['estado']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
