<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Cita;
use function App\Core\e;

$mensaje = $mensaje ?? null;
?>
<section class="hoja" data-ui="UI_Llegada">
    <header class="hoja-cabecera">
        <div>
            <h1>Registrar llegada</h1>
            <p class="lede">El comprobante no confirma la llegada solo con abrirlo. Revise la cita y confirme.</p>
        </div>
    </header>

    <?php if ($mensaje): ?>
        <p class="alert error" role="alert"><?= e($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($cita === null): ?>
        <p class="alert error" role="alert"><?= !empty($invalido) ? 'El enlace no es válido.' : ($codigo === '' ? 'Falta el código de la cita.' : 'El comprobante no corresponde a ninguna cita.') ?></p>
    <?php else: ?>
        <article class="resumen-previo">
            <h2>Cita <?= e($cita['codigo']) ?></h2>
            <dl>
                <dt>Paciente</dt><dd><?= e($cita['paciente']) ?></dd>
                <dt>Médico</dt><dd><?= e($cita['medico']) ?></dd>
                <dt>Especialidad</dt><dd><?= e($cita['especialidad']) ?></dd>
                <dt>Horario</dt><dd><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?> · <?= e($cita['hora_inicio']) ?></dd>
                <dt>Estado</dt><dd><?= e(Cita::ESTADOS[$cita['estado']] ?? $cita['estado']) ?></dd>
            </dl>
        </article>
        <?php if ($puede): ?>
            <form method="post" action="<?= App::url('/citas/llegada') ?>">
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <input type="hidden" name="codigo" value="<?= e($cita['codigo']) ?>">
                <button type="submit" class="btn-primary btn-chico">Confirmar llegada</button>
            </form>
        <?php elseif (!$mensaje): ?>
            <p class="alert error" role="alert">
                <?php if (!in_array($cita['estado'], Cita::PENDIENTES, true)): ?>
                    La cita está <?= e(mb_strtolower(Cita::ESTADOS[$cita['estado']] ?? $cita['estado'])) ?>; no se registra la llegada.
                <?php else: ?>
                    La cita es del <?= e(date('d/m/Y', strtotime($cita['fecha']))) ?>, no de hoy.
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>
