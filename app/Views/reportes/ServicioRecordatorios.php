<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Cita;
use App\Models\Recordatorio;
use function App\Core\e;
?>
<section class="hoja" data-ui="ServicioRecordatorios">
    <header class="hoja-cabecera">
        <div>
            <h1>Recordatorios de citas</h1>
            <p class="lede">Se envían <?= Recordatorio::HORAS_ANTES ?> h antes de cada cita, por WhatsApp, correo o aviso en el portal, según las variables de entorno.
                La ejecución programada corre con <code>scripts/enviar-recordatorios.php</code>.</p>
        </div>
        <form method="post" action="<?= App::url('/recordatorios') ?>">
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <button type="submit" class="btn-primary btn-chico" name="btnProcesar">Procesar pendientes ahora</button>
        </form>
    </header>

    <?php if ($detalle !== []): ?>
        <ul class="detalle-ejecucion">
            <?php foreach ($detalle as $linea): ?><li><?= e($linea) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form class="filtros filtros-codigo" method="get" action="<?= App::url('/recordatorios') ?>">
        <label class="field">
            <span>Estado de envío</span>
            <select name="estado" data-autoenviar>
                <option value="">Todos</option>
                <?php foreach (Recordatorio::ESTADOS as $valor => $texto): ?>
                    <option value="<?= $valor ?>"<?= $estado === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="tabla-responsive">
    <table class="cuentas" id="tablaRecordatorios">
        <thead>
            <tr><th>Cita</th><th>Paciente</th><th>Correo</th><th>Canal</th><th>Programado</th><th>Envío</th></tr>
        </thead>
        <tbody>
            <?php if ($recordatorios === []): ?>
                <tr><td colspan="6" class="vacio">No hay recordatorios.</td></tr>
            <?php endif; ?>
            <?php foreach ($recordatorios as $r): ?>
                <tr>
                    <td class="quien" data-label="Cita">
                        <strong><?= e($r['codigo']) ?></strong>
                        <span><?= e(date('d/m/Y', strtotime($r['fecha'])) . ' ' . $r['hora_inicio']) ?> · <?= e(Cita::ESTADOS[$r['estado_cita']] ?? $r['estado_cita']) ?></span>
                    </td>
                    <td data-label="Paciente"><?= e($r['paciente']) ?></td>
                    <td data-label="Correo"><?= e($r['email'] ?: 'sin correo') ?></td>
                    <td data-label="Canal"><?= e(Recordatorio::CANALES[$r['canal']] ?? $r['canal']) ?></td>
                    <td data-label="Programado"><?= e($r['fecha_programada']) ?></td>
                    <td data-label="Envío">
                        <div class="envio-celda">
                            <span class="estado-pill envio-<?= e(strtolower($r['estado_envio'])) ?>"><?= e(Recordatorio::ESTADOS[$r['estado_envio']]) ?></span>
                            <?php if ($r['estado_envio'] !== 'ENVIADO'): ?>
                                <form method="post" action="<?= App::url('/recordatorios') ?>">
                                    <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                    <input type="hidden" name="id_recordatorio" value="<?= e((string) $r['id_recordatorio']) ?>">
                                    <button type="submit" class="btn-borde btn-chico" name="btnEnviar">Enviar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
