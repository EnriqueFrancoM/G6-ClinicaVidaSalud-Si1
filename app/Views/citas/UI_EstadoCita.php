<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Cita;
use function App\Core\e;
?>
<section class="hoja" data-ui="UI_EstadoCita">
    <header class="hoja-cabecera">
        <div>
            <h1>Registrar asistencia y estado de cita</h1>
            <p class="lede">Programada → Confirmada → Atendida o Inasistencia. Los estados finales no se revierten.</p>
        </div>
    </header>

    <!-- Paso 1: consultar la cita -->
    <form class="filtros filtros-codigo" method="get" action="<?= App::url('/citas/estado') ?>">
        <label class="field">
            <span>Código de cita</span>
            <input type="search" name="codigo" value="<?= e($codigo) ?>" placeholder="Ej. CM-0005" required>
        </label>
        <button type="submit" class="btn-primary btn-chico">Buscar</button>
    </form>

    <?php if ($aviso): ?>
        <p class="alert error"><?= e($aviso) ?></p>
    <?php endif; ?>

    <?php if ($cita !== null): ?>
        <?php require __DIR__ . '/_resumen_cita.php'; ?>

        <?php if ($destinos === []): ?>
            <p class="vacio-dia">
                <?= isset(Cita::TRANSICIONES[$cita['estado']])
                    ? 'No hay cambios de estado disponibles para su rol en este momento (atendida e inasistencia se registran desde la hora de la cita).'
                    : 'La cita está ' . e(mb_strtolower(Cita::ESTADOS[$cita['estado']])) . ': es un estado final.' ?>
            </p>
        <?php else: ?>
            <!-- Paso 2: seleccionar estado. Pasos 3 a 5 en el servidor -->
            <form method="post" action="<?= App::url('/citas/estado') ?>">
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <input type="hidden" name="codigo" value="<?= e($cita['codigo']) ?>">
                <label class="field">
                    <span>Observación</span>
                    <input type="text" name="observacion" maxlength="200" placeholder="Opcional">
                </label>
                <div class="modal-acciones">
                    <a class="btn-borde" href="<?= App::url('/agenda?id_medico=' . (int) $cita['id_medico'] . '&fecha=' . $cita['fecha']) ?>">Volver a la agenda</a>
                    <?php foreach ($destinos as $estado): ?>
                        <button type="submit" name="estado" value="<?= e($estado) ?>"
                                class="<?= $estado === 'INASISTENCIA' ? 'btn-borde btn-peligro' : 'btn-primary btn-chico' ?>"
                                data-confirmar="¿Marcar la cita <?= e($cita['codigo']) ?> como <?= e(mb_strtolower(Cita::ESTADOS[$estado])) ?>?">
                            Marcar <?= e(mb_strtolower(Cita::ESTADOS[$estado])) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
