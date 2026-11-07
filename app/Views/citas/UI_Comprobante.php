<?php

use App\Core\App;
use function App\Core\e;
?>
<section class="hoja comprobante" data-ui="UI_Comprobante">
    <header class="hoja-cabecera no-print">
        <div>
            <h1>Comprobante de cita</h1>
            <p class="lede">Presente este código en recepción al llegar a la clínica.</p>
        </div>
        <div class="acciones-cabecera">
            <a class="btn-borde" href="<?= App::url($volver) ?>">Volver</a>
            <button type="button" class="btn-primary btn-chico" onclick="window.print()">Imprimir</button>
        </div>
    </header>

    <div class="comprobante-cuerpo">
        <div class="comprobante-qr">
            <?= $qr ?>
            <strong class="codigo-cita"><?= e($cita['codigo']) ?></strong>
        </div>
        <div>
            <p class="eyebrow-cita"><?= e((string) App::config('app.name')) ?></p>
            <?php require __DIR__ . '/_resumen_cita.php'; ?>
            <p class="nota">Llegue 10 minutos antes. Si no puede asistir, cancele la cita para liberar el horario.</p>
        </div>
    </div>
</section>
