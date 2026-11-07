<?php

use App\Core\App;
use App\Models\Cita;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$citas = $agenda['citas'] ?? [];
$pendiente = fn (array $c) => in_array($c['estado'], Cita::PENDIENTES, true);
?>
<section class="hoja" data-ui="UI_AgendaMedica">
    <header class="hoja-cabecera">
        <div>
            <h1>Agenda médica</h1>
            <p class="lede"><?= $esMedico ? 'Sus citas por fecha y estado.' : 'Citas por médico, fecha y estado, ordenadas por hora.' ?></p>
        </div>
        <?php if ($puedeGestionar): ?>
            <a class="btn-primary btn-chico" data-fab href="<?= App::url('/citas/programar') ?>">Programar cita</a>
        <?php endif; ?>
    </header>

    <!-- Pasos 1 y 2: médico, fecha y estado -->
    <form class="filtros filtros-bitacora filtros-medicos" method="get" action="<?= App::url('/agenda') ?>">
        <label class="field">
            <span>Médico</span>
            <select name="id_medico"<?= $esMedico ? ' disabled' : '' ?> data-autoenviar>
                <?php if ($puedeGestionar && !$esMedico): ?>
                    <option value="0"<?= (int) $filtro['id_medico'] === 0 ? ' selected' : '' ?>>Todos los médicos</option>
                <?php endif; ?>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= (int) $m['id_medico'] ?>"<?= (int) $filtro['id_medico'] === (int) $m['id_medico'] ? ' selected' : '' ?>><?= e($m['codigo'] . ' – ' . $m['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Fecha</span>
            <input type="date" name="fecha" value="<?= e($filtro['fecha']) ?>" required>
        </label>
        <label class="field">
            <span>Estado</span>
            <select name="estado">
                <option value="">Todos</option>
                <?php foreach (Cita::ESTADOS as $valor => $texto): ?>
                    <option value="<?= $valor ?>"<?= $filtro['estado'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn-primary btn-chico">Consultar</button>
    </form>

    <?php if ($agenda === null && !empty($aviso)): ?>
        <p class="alert error" role="alert"><?= e($aviso) ?></p>
    <?php endif; ?>

    <?php if ($agenda !== null): ?>
        <?php $todos = !empty($agenda['todos']); ?>
        <p class="aviso-rango">
            <strong><?= e($agenda['medico']['nombre']) ?></strong> · <?= e(date('d/m/Y', strtotime($filtro['fecha']))) ?> ·
            <?php if ($todos): ?>
                Citas de todos los médicos ese día.
            <?php elseif ($agenda['bloqueado']): ?>
                Agenda bloqueada ese día (feriado o ausencia).
            <?php elseif ($agenda['horario'] === []): ?>
                Sin horario de atención ese día.
            <?php else: ?>
                Atiende <?= e(implode(', ', array_map(fn ($h) => $h['hora_inicio'] . '–' . $h['hora_fin'], $agenda['horario']))) ?>
            <?php endif; ?>
        </p>

        <div class="tabla-responsive">
        <table class="cuentas" id="tablaAgenda">
            <thead>
                <tr><th>Hora</th><?php if ($todos): ?><th>Médico</th><?php endif; ?><th>Paciente</th><th>Especialidad</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if ($citas === []): ?>
                    <tr><td colspan="<?= $todos ? 7 : 6 ?>" class="vacio"><?= e($aviso ?? 'Sin citas.') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($citas as $c): ?>
                    <tr<?= !empty($c['prioritaria']) ? ' class="fila-prioritaria"' : '' ?>>
                        <td data-label="Hora"><strong><?= e($c['hora_inicio']) ?></strong>–<?= e($c['hora_fin']) ?></td>
                        <?php if ($todos): ?><td data-label="Médico"><?= e($c['medico']) ?></td><?php endif; ?>
                        <td class="quien" data-label="Paciente">
                            <strong><?= e($c['paciente']) ?></strong>
                            <span><?= e($c['codigo']) ?> · <?= e($c['paciente_codigo']) ?><?= $c['prioritaria'] ? ' · ' : '' ?></span>
                            <?php if ($c['prioritaria']): ?><span class="marca prioridad">Prioritaria</span><?php endif; ?>
                        </td>
                        <td data-label="Especialidad"><?= e($c['especialidad']) ?></td>
                        <td data-label="Motivo"><?= e($c['motivo'] ?: '—') ?></td>
                        <td data-label="Estado"><span class="estado-pill estado-<?= e(strtolower($c['estado'])) ?>"><?= e(Cita::ESTADOS[$c['estado']]) ?></span></td>
                        <td class="acciones">
                            <?php if ($pendiente($c) && ($puedeGestionar || $puedeAtender)): ?>
                                <a class="btn-icono" href="<?= App::url('/citas/estado?codigo=' . rawurlencode($c['codigo'])) ?>" title="Asistencia y estado" aria-label="Estado de <?= e($c['codigo']) ?>"><?= vs_icono('activar') ?></a>
                            <?php endif; ?>
                            <?php if ($pendiente($c) && $puedeGestionar): ?>
                                <a class="btn-icono" href="<?= App::url('/citas/reprogramar?codigo=' . rawurlencode($c['codigo'])) ?>" title="Reprogramar" aria-label="Reprogramar <?= e($c['codigo']) ?>"><?= vs_icono('calendario') ?></a>
                                <a class="btn-icono btn-peligro" href="<?= App::url('/citas/cancelar?codigo=' . rawurlencode($c['codigo'])) ?>" title="Cancelar" aria-label="Cancelar <?= e($c['codigo']) ?>"><?= vs_icono('quitar') ?></a>
                            <?php endif; ?>
                            <?php if ($pendiente($c)): ?>
                                <a class="btn-icono" href="<?= App::url('/citas/comprobante?codigo=' . rawurlencode($c['codigo'])) ?>" title="Comprobante QR" aria-label="Comprobante de <?= e($c['codigo']) ?>"><?= vs_icono('qr') ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if (array_filter(array_column($citas, 'prioritaria'))): ?>
            <p class="nota">Prioritaria: cita de Pediatría de un menor de 5 años (RF23).</p>
        <?php endif; ?>
        <?php if ($todos && !empty($agenda['sin_citas'])): ?>
            <h2 class="subtitulo">Médicos sin citas ese día</h2>
            <ul class="sin-cupos">
                <?php foreach ($agenda['sin_citas'] as $medico): ?>
                    <li>
                        <?= e($medico['codigo'] . ' – ' . $medico['nombre']) ?>
                        <?php if ($medico['bloqueado']): ?>
                            · agenda bloqueada
                        <?php elseif ($medico['horario'] === []): ?>
                            · no atiende ese día
                        <?php else: ?>
                            · atiende <?= e(implode(', ', array_map(fn ($h) => $h['hora_inicio'] . '–' . $h['hora_fin'], $medico['horario']))) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
