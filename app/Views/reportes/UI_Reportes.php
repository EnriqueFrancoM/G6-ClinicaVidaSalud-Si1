<?php

use App\Core\App;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$exportar = fn (string $formato) => App::url('/reportes?' . http_build_query(array_merge($filtro, ['formato' => $formato])));
?>
<section class="hoja" data-ui="UI_Reportes">
    <header class="hoja-cabecera">
        <div>
            <h1>Generar reportes</h1>
            <p class="lede">Elija el reporte y los filtros; la vista previa y el archivo exportado contienen el mismo conjunto.</p>
        </div>
    </header>

    <!-- Pasos 1 y 2: elegir reporte e ingresar filtros -->
    <form class="filtros filtros-reporte" method="get" action="<?= App::url('/reportes') ?>">
        <label class="field">
            <span>Reporte</span>
            <select name="tipo">
                <?php foreach ($tipos as $valor => $texto): ?>
                    <option value="<?= $valor ?>"<?= $filtro['tipo'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
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
        <label class="field">
            <span>Estado</span>
            <select name="estado">
                <option value="">Todos</option>
                <?php foreach ($estados as $valor => $texto): ?>
                    <option value="<?= $valor ?>"<?= $filtro['estado'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn-primary btn-chico">Previsualizar</button>
    </form>

    <?php if ($resultado !== null): ?>
        <!-- Paso 5: previsualizar. Paso 6: exportar -->
        <header class="hoja-cabecera reporte-cabecera">
            <div>
                <h2><?= e($resultado['titulo']) ?></h2>
                <p class="nota"><?= e($resultado['subtitulo']) ?></p>
            </div>
            <div class="acciones-cabecera">
                <?php foreach ($formatos as $formato => $texto): ?>
                    <a class="btn-borde" href="<?= e($exportar($formato)) ?>"><?= vs_icono('descargar') ?> <?= e(strtoupper($formato)) ?></a>
                <?php endforeach; ?>
            </div>
        </header>

        <div class="tabla-responsive">
        <table class="cuentas" id="tablaReporte">
            <thead>
                <tr><?php foreach ($resultado['columnas'] as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php if ($resultado['filas'] === []): ?>
                    <tr><td colspan="<?= count($resultado['columnas']) ?>" class="vacio"><?= e($aviso ?? 'Sin resultados.') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($resultado['filas'] as $fila): ?>
                    <tr><?php foreach ($fila as $valor): ?><td><?= e((string) $valor) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if (!empty($resultado['resumen'])): ?>
            <dl class="ficha resumen-reporte">
                <?php foreach ($resultado['resumen'] as $etiqueta => $valor): ?>
                    <div><dt><?= e($etiqueta) ?></dt><dd><strong><?= e((string) $valor) ?></strong></dd></div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    <?php endif; ?>
</section>
