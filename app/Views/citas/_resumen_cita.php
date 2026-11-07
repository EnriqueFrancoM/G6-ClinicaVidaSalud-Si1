<?php
// Resumen de una cita: fecha, médico y paciente (CU12 paso 1, CU13 paso 2, CU14 paso 1).

use App\Models\Cita;
use function App\Core\e;

$c = $cita;
?>
<dl class="ficha ficha-cita">
    <div><dt>Código</dt><dd><strong><?= e($c['codigo']) ?></strong></dd></div>
    <div><dt>Estado</dt><dd><span class="estado-pill estado-<?= e(strtolower($c['estado'])) ?>"><?= e(Cita::ESTADOS[$c['estado']] ?? $c['estado']) ?></span></dd></div>
    <div><dt>Fecha y hora</dt><dd><?= e(date('d/m/Y', strtotime($c['fecha']))) ?> · <?= e($c['hora_inicio']) ?>–<?= e($c['hora_fin']) ?></dd></div>
    <div><dt>Paciente</dt><dd><?= e($c['paciente']) ?> <small>(<?= e($c['paciente_codigo']) ?>)</small></dd></div>
    <div><dt>Médico</dt><dd><?= e($c['medico']) ?></dd></div>
    <div><dt>Especialidad</dt><dd><?= e($c['especialidad']) ?></dd></div>
    <div><dt>Motivo</dt><dd><?= e($c['motivo'] ?: '—') ?></dd></div>
    <?php if ($c['estado'] === 'CANCELADA' && $c['motivo_cancelacion']): ?>
        <div><dt>Motivo de cancelación</dt><dd><?= e($c['motivo_cancelacion']) ?></dd></div>
    <?php endif; ?>
</dl>
