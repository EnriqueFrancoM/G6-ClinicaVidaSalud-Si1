<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$pendiente = $pendiente ?? false;
$cita = $cita ?? null;
$respuesta = $respuesta ?? '';
$token = $token ?? '';
?>
<div class="registro-pagina respuesta-recordatorio">
    <a class="auth-brand" href="<?= App::url('/login') ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" stroke-dasharray="3.2 2.2"/>
            <path d="M12 7v10M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Vida Salud
    </a>
    <section class="hoja">
        <h1><?= $pendiente ? 'Confirmar respuesta' : ($ok ? 'Respuesta registrada' : 'No se pudo registrar') ?></h1>
        <p class="alert <?= $ok ? 'success' : 'error' ?>" role="status"><?= e($mensaje) ?></p>
        <?php if ($pendiente && $cita !== null): ?>
            <article class="resumen-previo">
                <h2>Cita <?= e($cita['codigo']) ?></h2>
                <dl>
                    <dt>Paciente</dt><dd><?= e($cita['paciente']) ?></dd>
                    <dt>Médico</dt><dd><?= e($cita['medico']) ?></dd>
                    <dt>Horario</dt><dd><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?> · <?= e($cita['hora_inicio']) ?></dd>
                </dl>
            </article>
            <form method="post" action="<?= App::url('/recordatorio/responder') ?>">
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <?php if ($token !== ''): ?>
                    <input type="hidden" name="t" value="<?= e($token) ?>">
                <?php else: ?>
                    <input type="hidden" name="c" value="<?= (int) $id ?>">
                    <input type="hidden" name="r" value="<?= e($respuesta) ?>">
                    <input type="hidden" name="f" value="<?= e($fecha) ?>">
                    <input type="hidden" name="h" value="<?= e($hora) ?>">
                    <input type="hidden" name="t" value="<?= e($firma) ?>">
                <?php endif; ?>
                <button type="submit" class="btn-primary btn-chico"><?= $respuesta === 'cancelar' ? 'Cancelar la cita' : 'Confirmar asistencia' ?></button>
            </form>
        <?php else: ?>
            <p class="nota">Puede revisar sus citas iniciando sesión en el portal del paciente.</p>
            <a class="btn-primary btn-chico" href="<?= App::url('/login') ?>">Ir al portal</a>
        <?php endif; ?>
    </section>
</div>
