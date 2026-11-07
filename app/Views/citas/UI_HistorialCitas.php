<?php

use App\Core\App;
use App\Models\Cita;
use function App\Core\e;

$p = $historial['paciente'] ?? null;
$citas = $historial['citas'] ?? [];
?>
<section class="hoja" data-ui="UI_HistorialCitas">
    <header class="hoja-cabecera">
        <div>
            <h1>Historial de citas del paciente</h1>
            <p class="lede">Todas las citas del paciente, incluidas las pasadas y canceladas, en orden cronológico.</p>
        </div>
    </header>

    <!-- Pasos 1 y 2: buscar paciente y seleccionar período -->
    <form class="filtros filtros-bitacora filtros-medicos" method="get" action="<?= App::url('/citas/historial') ?>">
        <label class="field">
            <span>Paciente (CI o código)</span>
            <input type="search" name="criterio" value="<?= e($filtro['criterio']) ?>" placeholder="Ej. 6321458 o PAC-00001" required>
        </label>
        <label class="field">
            <span>Desde</span>
            <input type="date" name="desde" value="<?= e($filtro['desde']) ?>">
        </label>
        <label class="field">
            <span>Hasta</span>
            <input type="date" name="hasta" value="<?= e($filtro['hasta']) ?>">
        </label>
        <button type="submit" class="btn-primary btn-chico">Consultar</button>
    </form>

    <?php if ($p !== null): ?>
        <p class="aviso-rango">
            <strong><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></strong> · <?= e($p['codigo']) ?> · CI <?= e((string) $p['ci']) ?>
            <?= in_array($p['estado'], [true, 't', '1', 1], true) ? '' : ' · <strong>Paciente inactivo</strong>' ?>
            · <a href="<?= App::url('/pacientes/ficha?id=' . (int) $p['id_paciente']) ?>">Ver ficha</a>
        </p>

        <div class="tabla-responsive">
        <table class="cuentas" id="tablaHistorialCitas">
            <thead>
                <tr><th>Código</th><th>Fecha</th><th>Hora</th><th>Médico</th><th>Especialidad</th><th>Motivo</th><th>Estado</th></tr>
            </thead>
            <tbody>
                <?php if ($citas === []): ?>
                    <tr><td colspan="7" class="vacio"><?= e($aviso ?? 'Sin resultados.') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($citas as $c): ?>
                    <tr>
                        <td class="quien" data-label="Código"><strong><?= e($c['codigo']) ?></strong></td>
                        <td data-label="Fecha"><?= e(date('d/m/Y', strtotime($c['fecha']))) ?></td>
                        <td data-label="Hora"><?= e($c['hora_inicio']) ?></td>
                        <td data-label="Médico"><?= e($c['medico']) ?></td>
                        <td data-label="Especialidad"><?= e($c['especialidad']) ?></td>
                        <td data-label="Motivo"><?= e($c['estado'] === 'CANCELADA' && $c['motivo_cancelacion'] ? ($c['motivo'] ? $c['motivo'] . ' — ' : '') . 'Cancelada: ' . $c['motivo_cancelacion'] : ($c['motivo'] ?: '—')) ?></td>
                        <td data-label="Estado">
                            <span class="estado-pill estado-<?= e(strtolower($c['estado'])) ?>"><?= e(Cita::ESTADOS[$c['estado']]) ?></span>
                            <?php if ($c['cambios'] !== []): ?>
                                <details class="cambios">
                                    <summary><?= count($c['cambios']) ?> cambio(s)</summary>
                                    <ol>
                                        <?php foreach ($c['cambios'] as $h): ?>
                                            <li><time><?= e($h['fecha_hora']) ?></time>
                                                <?= e(($h['estado_anterior'] ? (Cita::ESTADOS[$h['estado_anterior']] ?? $h['estado_anterior']) . ' → ' : '') . (Cita::ESTADOS[$h['estado_nuevo']] ?? $h['estado_nuevo'])) ?>
                                                · <?= e($h['usuario']) ?><?= $h['observacion'] ? ' · ' . e($h['observacion']) : '' ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php elseif ($filtro['criterio'] === ''): ?>
        <p class="vacio-dia">Ingrese el CI o el código del paciente.</p>
    <?php endif; ?>
</section>
