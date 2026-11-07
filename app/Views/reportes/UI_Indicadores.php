<?php

use App\Core\App;
use App\Models\Cita;
use function App\Core\e;

$i = $indicadores;
$porcentaje = fn (?float $v) => $v === null ? 'Sin datos' : number_format($v, 1, ',', '.') . ' %';
$barra = fn (?float $v) => $v === null ? 0 : max(0, min(100, $v));
?>
<section class="hoja" data-ui="UI_Indicadores">
    <header class="hoja-cabecera">
        <div>
            <h1>Panel de indicadores</h1>
            <p class="lede">Inasistencia, ocupación por médico, demanda por especialidad y uso de la lista de espera.</p>
        </div>
    </header>

    <!-- Pasos 1 y 2: período y filtros -->
    <form class="filtros filtros-indicadores" method="get" action="<?= App::url('/indicadores') ?>">
        <label class="field"><span>Desde</span><input type="date" name="desde" value="<?= e($filtro['desde']) ?>" required></label>
        <label class="field"><span>Hasta</span><input type="date" name="hasta" value="<?= e($filtro['hasta']) ?>" required></label>
        <label class="field">
            <span>Médico</span>
            <select name="id_medico">
                <option value="">Todos</option>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= (int) $m['id_medico'] ?>"<?= $filtro['id_medico'] === (string) $m['id_medico'] ? ' selected' : '' ?>><?= e($m['nombres'] . ' ' . $m['apellidos']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Especialidad</span>
            <select name="id_especialidad">
                <option value="">Todas</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= (int) $esp['id_especialidad'] ?>"<?= $filtro['id_especialidad'] === (string) $esp['id_especialidad'] ? ' selected' : '' ?>><?= e($esp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn-primary btn-chico">Calcular</button>
    </form>

    <?php if ($aviso): ?>
        <p class="aviso-rango"><?= e($aviso) ?></p>
    <?php endif; ?>

    <?php if ($i !== null): ?>
        <!-- Paso 6: tarjetas con denominadores explícitos -->
        <div class="stat-row indicadores">
            <article class="stat-card lift-card">
                <span>Inasistencia</span>
                <strong><?= e($porcentaje($i['inasistencia']['valor'])) ?></strong>
                <small><?= (int) $i['inasistencia']['numerador'] ?> inasistencias de <?= (int) $i['inasistencia']['denominador'] ?> citas concluidas (atendidas + inasistencias)</small>
            </article>
            <article class="stat-card lift-card">
                <span>Ocupación de la agenda</span>
                <strong><?= e($porcentaje($i['ocupacion']['valor'])) ?></strong>
                <small><?= (int) $i['ocupacion']['numerador'] ?> citas no canceladas de <?= (int) $i['ocupacion']['denominador'] ?> cupos
                    (<?= (int) $i['ocupacion']['capacidad'] ?> ofrecidos − <?= (int) $i['ocupacion']['bloqueados'] ?> bloqueados)</small>
            </article>
            <article class="stat-card lift-card">
                <span>Cancelación</span>
                <strong><?= e($porcentaje($i['cancelacion']['valor'])) ?></strong>
                <small><?= (int) $i['cancelacion']['numerador'] ?> canceladas de <?= (int) $i['cancelacion']['denominador'] ?> citas</small>
            </article>
            <article class="stat-card lift-card">
                <span>Lista de espera: asignación</span>
                <strong><?= e($porcentaje($i['espera']['valor'])) ?></strong>
                <small><?= (int) $i['espera']['numerador'] ?> asignadas de <?= (int) $i['espera']['denominador'] ?> solicitudes · <?= (int) $i['espera']['vigentes'] ?> vigentes</small>
            </article>
        </div>

        <div class="dos-columnas columnas-iguales indicadores-tablas">
            <section>
                <h2 class="subtitulo">Ocupación por médico</h2>
                <div class="tabla-responsive">
                <table class="cuentas">
                    <thead><tr><th>Médico</th><th>Citas / cupos</th><th>Ocupación</th></tr></thead>
                    <tbody>
                        <?php if ($i['porMedico'] === []): ?>
                            <tr><td colspan="3" class="vacio">Sin datos en el período.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($i['porMedico'] as $m): ?>
                            <tr>
                                <td><?= e($m['medico']) ?></td>
                                <td><?= (int) $m['ocupadas'] ?> / <?= (int) $m['cupos'] ?></td>
                                <td><span class="barra" style="--valor: <?= $barra($m['indicador']) ?>%"></span> <?= e($porcentaje($m['indicador'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </section>
            <section>
                <h2 class="subtitulo">Demanda por especialidad</h2>
                <div class="tabla-responsive">
                <table class="cuentas">
                    <thead><tr><th>Especialidad</th><th>Citas</th><th>Participación</th></tr></thead>
                    <tbody>
                        <?php if ($i['demanda'] === []): ?>
                            <tr><td colspan="3" class="vacio">Sin datos en el período.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($i['demanda'] as $d): ?>
                            <tr>
                                <td><?= e($d['especialidad']) ?></td>
                                <td><?= (int) $d['citas'] ?></td>
                                <td><span class="barra" style="--valor: <?= $barra($d['indicador']) ?>%"></span> <?= e($porcentaje($d['indicador'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </section>
        </div>

        <h2 class="subtitulo">Citas del período por estado (total <?= (int) $i['total'] ?>)</h2>
        <dl class="ficha resumen-reporte">
            <?php foreach ($i['estados'] as $estado => $total): ?>
                <div><dt><?= e(Cita::ESTADOS[$estado]) ?></dt><dd><strong><?= (int) $total ?></strong></dd></div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</section>
