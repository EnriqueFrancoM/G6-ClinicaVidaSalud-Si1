<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Cita;
use function App\Core\e;

$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
?>
<section class="hoja" data-ui="UI_ReprogramarCita">
    <header class="hoja-cabecera">
        <div>
            <h1>Reprogramar cita</h1>
            <p class="lede">Ingrese el código de la cita y elija el nuevo horario. El horario anterior se libera.</p>
        </div>
    </header>

    <!-- Paso 1: buscar cita -->
    <form class="filtros filtros-codigo" method="get" action="<?= App::url('/citas/reprogramar') ?>">
        <label class="field<?= $invalido('codigo') ?>">
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

        <?php if (!$editable): ?>
            <p class="alert error">Solo se reprograman citas programadas o confirmadas; esta cita está <?= e(mb_strtolower(Cita::ESTADOS[$cita['estado']])) ?>.</p>
        <?php else: ?>
            <!-- Paso 2: seleccionar nuevo horario -->
            <form class="filtros filtros-codigo" method="get" action="<?= App::url('/citas/reprogramar') ?>">
                <input type="hidden" name="codigo" value="<?= e($cita['codigo']) ?>">
                <label class="field<?= $invalido('fecha') ?>">
                    <span>Nueva fecha</span>
                    <input type="date" name="fecha" value="<?= e($fecha) ?>" min="<?= date('Y-m-d') ?>" required>
                </label>
                <button type="submit" class="btn-borde">Ver horarios</button>
            </form>

            <?php if ($cupos !== null): ?>
                <form method="post" action="<?= App::url('/citas/reprogramar') ?>" class="form-cupos">
                    <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                    <input type="hidden" name="codigo" value="<?= e($cita['codigo']) ?>">
                    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                    <h2 class="subtitulo">Horarios libres de <?= e($cita['medico']) ?> el <?= e(date('d/m/Y', strtotime($fecha))) ?></h2>
                    <?php if ($cupos === []): ?>
                        <p class="vacio-dia">No hay horarios libres en esa fecha.</p>
                    <?php else: ?>
                        <div class="cupos<?= $invalido('hora_inicio') ?>" role="radiogroup" aria-label="Nuevo horario">
                            <?php foreach ($cupos as $cupo): ?>
                                <?php $actual = $fecha === $cita['fecha'] && $cupo['hora_inicio'] === $cita['hora_inicio']; ?>
                                <label class="cupo<?= $actual ? ' cupo-actual' : '' ?>">
                                    <input type="radio" name="hora_inicio" value="<?= e($cupo['hora_inicio']) ?>" required<?= $actual ? ' disabled' : '' ?>>
                                    <span><?= e($cupo['hora_inicio']) ?><?= $actual ? ' (actual)' : '' ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-acciones">
                            <a class="btn-borde" href="<?= App::url('/agenda?id_medico=' . (int) $cita['id_medico'] . '&fecha=' . $cita['fecha']) ?>">Volver a la agenda</a>
                            <button type="submit" class="btn-primary btn-chico" name="btnReprogramar">Reprogramar</button>
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
