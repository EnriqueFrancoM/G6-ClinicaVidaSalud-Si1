<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$medicos = $medicos ?? [];
$horarios = $horarios ?? [];
$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$editando = (int) $form['id_horario'] > 0;

$medicoActual = null;
foreach ($medicos as $m) {
    if ((int) $m['id_medico'] === $idMedico) {
        $medicoActual = $m;
    }
}

// Vista semanal: horas de los horarios activos y días de lunes a sábado estrictamente (1 a 6).
$minutos = fn (string $h) => (int) substr($h, 0, 2) * 60 + (int) substr($h, 3, 2);
$activos = array_filter($horarios, fn ($h) => $h['estado']);
$columnas = array_slice($dias, 0, 6, true);

// Horas base típicas de atención médica (mañana y tarde) más cualquier hora configurada.
$horas = array_fill_keys([8, 9, 10, 11, 14, 15, 16, 17], true);
foreach ($activos as $h) {
    for ($hora = intdiv($minutos($h['hora_inicio']), 60); $hora * 60 < $minutos($h['hora_fin']); $hora++) {
        $horas[$hora] = true;
    }
}
ksort($horas);

$celda = function (int $dia, int $hora) use ($activos, $minutos): ?array {
    foreach ($activos as $h) {
        if ((int) $h['dia_semana'] === $dia && $minutos($h['hora_inicio']) < ($hora + 1) * 60 && $minutos($h['hora_fin']) > $hora * 60) {
            $dur = (int) $h['duracion_cita_min'];
            $texto = 60 % $dur === 0 ? (60 / $dur) . ' citas/h' : "cada $dur min";
            return ['texto' => $texto, 'horario' => $h];
        }
    }
    return null;
};
?>
<div data-ui="UI_Horarios">
    <form class="hoja selector-medico barra-medico" method="get" action="<?= App::url('/horarios') ?>">
        <div>
            <h1>Horario de atención</h1>
            <p class="lede"><?= $medicoActual ? e($medicoActual['nombres'] . ' ' . $medicoActual['apellidos'] . ($medicoActual['especialidades'] ? ' (' . $medicoActual['especialidades'] . ')' : '')) : 'Elija un médico' ?></p>
        </div>
        <label class="field">
            <span>Médico</span>
            <select name="medico" id="cboMedico" data-autoenviar>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= e((string) $m['id_medico']) ?>"<?= (int) $m['id_medico'] === $idMedico ? ' selected' : '' ?>>
                        <?= e($m['codigo'] . ' – ' . $m['nombres'] . ' ' . $m['apellidos'] . ($m['especialidades'] ? ' (' . $m['especialidades'] . ')' : '') . ($m['estado'] ? '' : ' · inactivo')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <section class="hoja semana-hero">
        <header class="hoja-cabecera">
            <div>
                <h1>Semana</h1>
                <p class="lede">Horario de atención de lunes a sábado. Las celdas verdes representan turnos con agenda configurada.</p>
            </div>
            <div class="acciones-horario">
                <button type="button" class="btn-primary btn-chico" id="btnModalHorario" data-abrir-dialogo="#modalHorario">
                    <?= vs_icono('agregar') ?><span>Agregar horario</span>
                </button>
                <button type="button" class="btn-borde btn-chico" id="btnModalBloqueo" data-abrir-dialogo="#modalBloqueo">
                    <?= vs_icono('bloquear') ?><span>Bloqueos de agenda</span>
                </button>
            </div>
        </header>

        <div class="semana-scroll">
            <table class="semana">
                <thead>
                    <tr>
                        <th class="col-hora">Hora</th>
                        <?php foreach ($columnas as $nombre): ?>
                            <th>
                                <span class="dia-largo"><?= e($nombre) ?></span>
                                <span class="dia-corto"><?= e(mb_substr($nombre, 0, 3)) ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_keys($horas) as $hora): ?>
                        <tr>
                            <th class="col-hora"><?= sprintf('%02d:00', $hora) ?></th>
                            <?php foreach (array_keys($columnas) as $dia): ?>
                                <?php $info = $celda($dia, $hora); ?>
                                <?php if ($info !== null): ?>
                                    <td class="ocupado" data-abrir-dialogo="#modalHorario" title="Horario: <?= e($info['horario']['hora_inicio']) ?> - <?= e($info['horario']['hora_fin']) ?> (<?= (int) $info['horario']['duracion_cita_min'] ?> min). Clic para gestionar.">
                                        <?= e($info['texto']) ?>
                                    </td>
                                <?php else: ?>
                                    <td></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Modal 1: Agregar / Editar horario y listado de horarios -->
    <dialog class="modal modal-ancho" id="modalHorario"<?= !empty($abrirHorario) ? ' data-modal' : '' ?>>
        <form id="formHorario" method="post" action="<?= App::url('/horarios') ?>">
            <div class="modal-cabecera-titulo">
                <h2><?= $editando ? 'Editar horario' : 'Agregar horario' ?></h2>
                <button type="button" class="btn-cerrar" data-cerrar-modal aria-label="Cerrar">&times;</button>
            </div>

            <?php if ($medicoActual !== null && !$medicoActual['estado'] && !$editando): ?>
                <p class="alert error">El médico está inactivo; no se permiten nuevos horarios.</p>
            <?php endif; ?>

            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_medico" value="<?= e((string) $idMedico) ?>">
            <input type="hidden" name="id_horario" value="<?= e((string) $form['id_horario']) ?>">

            <div class="modal-grid">
                <label class="field<?= $invalido('dia_semana') ?>">
                    <span>Día *</span>
                    <select name="dia_semana">
                        <?php foreach ($columnas as $n => $nombre): ?>
                            <option value="<?= $n ?>"<?= (int) $form['dia_semana'] === $n ? ' selected' : '' ?>><?= e($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field<?= $invalido('duracion_cita_min') ?>">
                    <span>Duración cita</span>
                    <select name="duracion_cita_min">
                        <?php foreach ($duraciones as $d): ?>
                            <option value="<?= $d ?>"<?= (int) $form['duracion_cita_min'] === $d ? ' selected' : '' ?>><?= $d ?> min</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field<?= $invalido('hora_inicio') ?>">
                    <span>Hora inicio *</span>
                    <input type="time" name="hora_inicio" value="<?= e($form['hora_inicio']) ?>" required>
                </label>
                <label class="field<?= $invalido('hora_fin') ?>">
                    <span>Hora fin *</span>
                    <input type="time" name="hora_fin" value="<?= e($form['hora_fin']) ?>" required>
                </label>
            </div>
            <p class="nota">La hora fin debe ser posterior a la hora inicio y no puede solaparse con otro horario del mismo día.</p>

            <div class="modal-acciones">
                <?php if ($editando): ?>
                    <a class="btn-borde" href="<?= App::url('/horarios?medico=' . $idMedico) ?>">Cancelar edición</a>
                <?php else: ?>
                    <button type="button" class="btn-borde" data-cerrar-modal>Cerrar</button>
                <?php endif; ?>
                <button type="submit" class="btn-primary btn-chico" name="btnGuardar">
                    <?= vs_icono('agregar') ?><span><?= $editando ? 'Actualizar horario' : 'Guardar horario' ?></span>
                </button>
            </div>
        </form>

        <?php if ($confirmacion !== null): ?>
            <!-- E3: confirmación de un cambio que afecta citas futuras -->
            <form class="confirmacion" method="post" action="<?= App::url('/horarios') ?>">
                <p><?= e($confirmacion) ?></p>
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <input type="hidden" name="confirmado" value="1">
                <?php foreach (['accion', 'id_medico', 'id_horario', 'dia_semana', 'hora_inicio', 'hora_fin', 'duracion_cita_min'] as $campo): ?>
                    <input type="hidden" name="<?= $campo ?>" value="<?= e((string) ($form[$campo] ?? '')) ?>">
                <?php endforeach; ?>
                <div class="modal-acciones">
                    <a class="btn-borde" href="<?= App::url('/horarios?medico=' . $idMedico) ?>">No, cancelar</a>
                    <button type="submit" class="btn-primary btn-chico"><?= vs_icono('activar') ?><span>Sí, continuar</span></button>
                </div>
            </form>
        <?php endif; ?>

        <div class="modal-seccion">
            <h3>Horarios registrados del médico</h3>
            <div class="tabla-responsive">
                <table class="cuentas" id="tablaHorarios">
                    <thead>
                        <tr><th>Día</th><th>Inicio</th><th>Fin</th><th>Duración</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if ($horarios === []): ?>
                            <tr><td colspan="6" class="vacio">El médico no tiene horarios registrados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($horarios as $h): ?>
                            <tr<?= (int) $form['id_horario'] === (int) $h['id_horario'] ? ' class="seleccionado"' : '' ?>>
                                <td><?= e($dias[(int) $h['dia_semana']] ?? 'Día ' . $h['dia_semana']) ?></td>
                                <td><?= e($h['hora_inicio']) ?></td>
                                <td><?= e($h['hora_fin']) ?></td>
                                <td><?= (int) $h['duracion_cita_min'] ?> min</td>
                                <td><span class="estado-pill<?= $h['estado'] ? '' : ' bloqueada' ?>"><?= $h['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
                                <td class="acciones">
                                    <a class="btn-icono" href="<?= App::url('/horarios?medico=' . $idMedico . '&editar=' . (int) $h['id_horario']) ?>" title="Editar" aria-label="Editar horario del <?= e($dias[(int) $h['dia_semana']] ?? '') ?>"><?= vs_icono('editar') ?></a>
                                    <?php if ($h['estado']): ?>
                                        <form method="post" action="<?= App::url('/horarios') ?>">
                                            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                            <input type="hidden" name="accion" value="desactivar">
                                            <input type="hidden" name="id_medico" value="<?= e((string) $idMedico) ?>">
                                            <input type="hidden" name="id_horario" value="<?= e((string) $h['id_horario']) ?>">
                                            <button type="submit" class="btn-icono btn-peligro" title="Desactivar" aria-label="Desactivar el horario del <?= e($dias[(int) $h['dia_semana']] ?? '') ?>" data-confirmar="¿Desactivar el horario del <?= e($dias[(int) $h['dia_semana']] ?? '') ?>?"><?= vs_icono('bloquear') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </dialog>

    <!-- Modal 2: Bloqueos de agenda -->
    <dialog class="modal modal-ancho" id="modalBloqueo"<?= !empty($abrirBloqueo) ? ' data-modal' : '' ?>>
        <form id="formBloqueo" method="post" action="<?= App::url('/horarios/bloqueo') ?>">
            <div class="modal-cabecera-titulo">
                <h2>Bloqueos de agenda</h2>
                <button type="button" class="btn-cerrar" data-cerrar-modal aria-label="Cerrar">&times;</button>
            </div>
            <p class="lede">Feriados generales o ausencias de este médico que excluyen la disponibilidad.</p>
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="id_medico" value="<?= e((string) $idMedico) ?>">
            <div class="modal-grid">
                <label class="field<?= $invalido('bloqueo_fecha') ?>">
                    <span>Fecha *</span>
                    <input type="date" name="fecha" value="<?= e($formBloqueo['fecha']) ?>" min="<?= date('Y-m-d') ?>" required>
                </label>
                <label class="field">
                    <span>Aplica a</span>
                    <select name="alcance">
                        <option value="medico"<?= $formBloqueo['alcance'] !== 'general' ? ' selected' : '' ?>>Este médico (ausencia)</option>
                        <option value="general"<?= $formBloqueo['alcance'] === 'general' ? ' selected' : '' ?>>Todos los médicos (feriado)</option>
                    </select>
                </label>
            </div>
            <label class="field<?= $invalido('bloqueo_motivo') ?>">
                <span>Motivo *</span>
                <input type="text" name="motivo" value="<?= e($formBloqueo['motivo']) ?>" maxlength="120" placeholder="Ej.: Feriado, congreso, vacaciones" required>
            </label>
            <div class="modal-acciones">
                <button type="button" class="btn-borde" data-cerrar-modal>Cerrar</button>
                <button type="submit" class="btn-primary btn-chico" name="btnBloquear"><?= vs_icono('bloquear') ?><span>Registrar bloqueo</span></button>
            </div>
        </form>

        <div class="modal-seccion">
            <h3>Bloqueos registrados</h3>
            <div class="tabla-responsive">
                <table class="cuentas" id="tablaBloqueos">
                    <thead>
                        <tr><th>Fecha</th><th>Motivo</th><th>Aplica a</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (($bloqueos ?? []) === []): ?>
                            <tr><td colspan="4" class="vacio">Sin bloqueos próximos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($bloqueos ?? [] as $b): ?>
                            <tr>
                                <td><?= e(date('d/m/Y', strtotime($b['fecha']))) ?></td>
                                <td><?= e($b['motivo']) ?></td>
                                <td><?= $b['id_medico'] === null ? 'Todos (feriado)' : 'Este médico' ?></td>
                                <td class="acciones">
                                    <form method="post" action="<?= App::url('/horarios/bloqueo/quitar') ?>">
                                        <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                        <input type="hidden" name="id_medico" value="<?= e((string) $idMedico) ?>">
                                        <input type="hidden" name="id_bloqueo" value="<?= e((string) $b['id_bloqueo']) ?>">
                                        <button type="submit" class="btn-icono btn-peligro" title="Quitar" aria-label="Quitar el bloqueo del <?= e(date('d/m/Y', strtotime($b['fecha']))) ?>" data-confirmar="¿Quitar el bloqueo del <?= e(date('d/m/Y', strtotime($b['fecha']))) ?>?"><?= vs_icono('quitar') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </dialog>
</div>
