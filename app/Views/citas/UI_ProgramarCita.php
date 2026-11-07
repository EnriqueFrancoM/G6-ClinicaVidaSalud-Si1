<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$cupos = $cupos ?? null;
?>
<section class="hoja" data-ui="UI_ProgramarCita">
    <header class="hoja-cabecera">
        <div>
            <h1>Programar cita médica</h1>
            <p class="lede">Elija paciente, especialidad, médico y fecha. Solo se muestran los horarios libres.</p>
        </div>
    </header>

    <?php if (!empty($registrada)): ?>
        <!-- Paso 6: código único de la cita -->
        <div class="resultado-cita">
            <p>Cita registrada con el código</p>
            <strong class="codigo-cita"><?= e($registrada['codigo']) ?></strong>
            <p><?= e($registrada['paciente']) ?> · <?= e($registrada['medico']) ?> · <?= e(date('d/m/Y', strtotime($registrada['fecha']))) ?> <?= e($registrada['hora_inicio']) ?></p>
            <div class="acciones-cabecera">
                <a class="btn-borde" href="<?= App::url('/citas/comprobante?codigo=' . rawurlencode($registrada['codigo'])) ?>">Comprobante con QR</a>
                <a class="btn-primary btn-chico" href="<?= App::url('/citas/programar') ?>">Programar otra</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pasos 1 a 4: los filtros recargan los horarios disponibles -->
    <form class="filtros filtros-cita" method="get" action="<?= App::url('/citas/programar') ?>" data-form-cita>
        <label class="field<?= $invalido('paciente') ?>">
            <span>Paciente (CI o código) *</span>
            <input type="search" name="paciente" value="<?= e($filtro['paciente']) ?>" placeholder="Ej. 6321458 o PAC-00001" required>
        </label>
        <label class="field<?= $invalido('id_especialidad') ?>">
            <span>Especialidad *</span>
            <select name="id_especialidad" data-especialidad required>
                <option value="">Seleccione…</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= (int) $esp['id_especialidad'] ?>"<?= $filtro['id_especialidad'] === (int) $esp['id_especialidad'] ? ' selected' : '' ?>><?= e($esp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field<?= $invalido('id_medico') ?>">
            <span>Médico *</span>
            <select name="id_medico" data-medico required>
                <option value="">Seleccione…</option>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= (int) $m['id_medico'] ?>" data-especialidades="<?= e(implode(',', $m['ids_especialidad'])) ?>"<?= $filtro['id_medico'] === (int) $m['id_medico'] ? ' selected' : '' ?>><?= e($m['nombres'] . ' ' . $m['apellidos']) ?> · <?= e((string) $m['especialidades']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field<?= $invalido('fecha') ?>">
            <span>Fecha *</span>
            <input type="date" name="fecha" value="<?= e($filtro['fecha']) ?>" min="<?= date('Y-m-d') ?>" required>
        </label>
        <input type="hidden" name="motivo" value="<?= e($filtro['motivo']) ?>">
        <button type="submit" class="btn-primary btn-chico">Consultar horarios</button>
    </form>

    <?php if ($avisoPaciente): ?>
        <p class="alert error"><?= e($avisoPaciente) ?></p>
    <?php elseif ($paciente !== null): ?>
        <p class="aviso-rango">Paciente: <strong><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></strong> · <?= e($paciente['codigo']) ?> · CI <?= e((string) $paciente['ci']) ?></p>
    <?php endif; ?>

    <?php if ($cupos !== null): ?>
        <!-- Paso 4: elegir hora. Paso 5: validar nuevamente el cupo y registrar -->
        <form method="post" action="<?= App::url('/citas/programar') ?>" class="form-cupos">
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="id_paciente" value="<?= (int) $paciente['id_paciente'] ?>">
            <input type="hidden" name="paciente" value="<?= e($filtro['paciente']) ?>">
            <input type="hidden" name="id_especialidad" value="<?= (int) $filtro['id_especialidad'] ?>">
            <input type="hidden" name="id_medico" value="<?= (int) $filtro['id_medico'] ?>">
            <input type="hidden" name="fecha" value="<?= e($filtro['fecha']) ?>">

            <h2 class="subtitulo">Horarios libres del <?= e(date('d/m/Y', strtotime($filtro['fecha']))) ?></h2>
            <?php if ($cupos === []): ?>
                <p class="vacio-dia">No hay horarios libres para ese médico en la fecha elegida (sin atención, feriado, bloqueo o agenda completa).</p>
            <?php else: ?>
                <div class="cupos<?= $invalido('hora_inicio') ?>" role="radiogroup" aria-label="Horarios libres">
                    <?php foreach ($cupos as $i => $cupo): ?>
                        <label class="cupo">
                            <input type="radio" name="hora_inicio" value="<?= e($cupo['hora_inicio']) ?>" required<?= $i === 0 ? ' checked' : '' ?>>
                            <span><?= e($cupo['hora_inicio']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label class="field<?= $invalido('motivo') ?>">
                    <span>Motivo de la consulta</span>
                    <input type="text" name="motivo" value="<?= e($filtro['motivo']) ?>" maxlength="200">
                </label>
                <div class="modal-acciones">
                    <button type="submit" class="btn-primary btn-chico" name="btnProgramar">Registrar cita</button>
                </div>
            <?php endif; ?>
        </form>
    <?php elseif ($paciente !== null): ?>
        <p class="vacio-dia">Elija especialidad, médico y fecha y presione “Consultar horarios”.</p>
    <?php endif; ?>
</section>
