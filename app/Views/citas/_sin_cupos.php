<?php

use function App\Core\e;

$sinCupo = $sinCupo ?? null;
if ($sinCupo === null) {
    return;
}
$fechas = $sinCupo['fechas'] ?? [];
$medicos = $sinCupo['medicos'] ?? [];
$espera = $sinCupo['espera'] ?? null;
$dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$meses = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$detalle = static function (int $cantidad, string $hora): string {
    $texto = $cantidad === 1 ? '1 horario' : $cantidad . ' horarios';

    return $texto . ' · desde ' . $hora;
};
$iniciales = static function (string $nombre): string {
    $partes = preg_split('/\s+/u', trim($nombre)) ?: [];
    $letras = '';
    foreach (array_slice($partes, 0, 2) as $parte) {
        $letras .= mb_strtoupper(mb_substr($parte, 0, 1));
    }

    return $letras !== '' ? $letras : 'Dr';
};
?>
<div class="sin-cupos" role="status">
    <p class="alert error"><?= e($sinCupo['causa'] !== '' ? $sinCupo['causa'] : 'No hay horarios libres en esa fecha.') ?></p>
    <?php if ($fechas !== []): ?>
        <h3 class="subtitulo"><?= e(rtrim($sinCupo['etiqueta_fechas'] ?? 'Otras fechas con cupo para este médico:', ':')) ?></h3>
        <div class="cupos cupos-opciones">
            <?php foreach ($fechas as $opcion): ?>
                <?php $momento = strtotime($opcion['fecha']); ?>
                <a class="opcion-cupo" href="<?= e($opcion['url']) ?>">
                    <span class="opcion-marca" aria-hidden="true"><b><?= e(date('d', $momento)) ?></b><small><?= e($meses[(int) date('n', $momento)]) ?></small></span>
                    <span class="opcion-texto">
                        <strong><?= e($dias[(int) date('w', $momento)] . ' ' . date('d/m/Y', $momento)) ?></strong>
                        <small><?= e($detalle((int) $opcion['cantidad'], $opcion['hora_inicio'])) ?></small>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($medicos !== []): ?>
        <h3 class="subtitulo">Otros médicos con cupo ese día</h3>
        <div class="cupos cupos-opciones">
            <?php foreach ($medicos as $opcion): ?>
                <a class="opcion-cupo" href="<?= e($opcion['url']) ?>">
                    <span class="opcion-marca" aria-hidden="true"><b><?= e($iniciales($opcion['nombre'])) ?></b></span>
                    <span class="opcion-texto">
                        <strong><?= e($opcion['nombre']) ?></strong>
                        <small><?= e($detalle((int) $opcion['cantidad'], $opcion['hora_inicio'])) ?></small>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($espera): ?>
        <p><a class="btn-borde" href="<?= e($espera) ?>">Anotar en la lista de espera</a></p>
    <?php endif; ?>
    <?php if ($fechas === [] && $medicos === [] && !$espera): ?>
        <p class="nota">No hay otras fechas ni otros médicos con cupo en los próximos días.</p>
    <?php endif; ?>
</div>
