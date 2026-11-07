<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Cita;
use function App\Core\e;

$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
?>
<section class="hoja" data-ui="UI_CancelarCita">
    <header class="hoja-cabecera">
        <div>
            <h1>Cancelar cita</h1>
            <p class="lede">La cita no se elimina: queda cancelada con su motivo y el horario se libera.</p>
        </div>
    </header>

    <!-- Paso 1: buscar cita -->
    <form class="filtros filtros-codigo" method="get" action="<?= App::url('/citas/cancelar') ?>">
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
        <!-- Paso 2: mostrar fecha, médico y paciente -->
        <?php require __DIR__ . '/_resumen_cita.php'; ?>

        <?php if (!$cancelable): ?>
            <p class="alert error">La cita ya está <?= e(mb_strtolower(Cita::ESTADOS[$cita['estado']])) ?>; no se puede cancelar.</p>
        <?php else: ?>
            <!-- Pasos 3 y 4: ingresar motivo y confirmar -->
            <form method="post" action="<?= App::url('/citas/cancelar') ?>">
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <input type="hidden" name="codigo" value="<?= e($cita['codigo']) ?>">
                <label class="field<?= $invalido('motivo') ?>">
                    <span>Motivo de la cancelación *</span>
                    <textarea name="motivo" rows="3" maxlength="200" required><?= e($motivo) ?></textarea>
                </label>
                <div class="modal-acciones">
                    <a class="btn-borde" href="<?= App::url('/agenda?id_medico=' . (int) $cita['id_medico'] . '&fecha=' . $cita['fecha']) ?>">Volver a la agenda</a>
                    <button type="submit" class="btn-primary btn-chico btn-peligro-solido" name="btnCancelar"
                            data-confirmar="¿Cancelar la cita <?= e($cita['codigo']) ?>? El horario quedará libre.">Cancelar cita</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
