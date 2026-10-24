<?php

use App\Core\App;
use function App\Core\e;

$registros = $registros ?? [];
$total = $total ?? 0;
$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$consulta = [
    'usuario' => $filtros['id_usuario'],
    'modulo'  => $filtros['modulo'],
    'desde'   => $filtros['desde'],
    'hasta'   => $filtros['hasta'],
];
$paginas = max(1, (int) ceil($total / $porPagina));
$urlPagina = fn (int $p) => App::url('/bitacora/filtrar?' . http_build_query($consulta + ['pagina' => $p]));
?>
<section class="hoja" data-ui="UI_Bitacora">
    <header class="hoja-cabecera">
        <h1>Registro de actividades</h1>
        <a class="btn-borde" id="btnExportar" href="<?= App::url('/bitacora/exportar?' . http_build_query($consulta)) ?>">⬇ Exportar CSV</a>
    </header>

    <form class="filtros filtros-bitacora" id="filtros" method="get" action="<?= App::url('/bitacora/filtrar') ?>">
        <label class="field">
            <span>Usuario</span>
            <select name="usuario">
                <option value="">Todos</option>
                <?php foreach ($usuarios ?? [] as $u): ?>
                    <option value="<?= e((string) $u['id_usuario']) ?>"<?= (string) $filtros['id_usuario'] === (string) $u['id_usuario'] ? ' selected' : '' ?>><?= e($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Módulo</span>
            <select name="modulo">
                <option value="">Todos</option>
                <?php foreach ($modulos ?? [] as $m): ?>
                    <option value="<?= e($m) ?>"<?= $filtros['modulo'] === $m ? ' selected' : '' ?>><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field<?= $invalido('desde') ?>">
            <span>Desde</span>
            <input type="date" name="desde" value="<?= e($filtros['desde']) ?>">
        </label>
        <label class="field<?= $invalido('hasta') ?>">
            <span>Hasta</span>
            <input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>">
        </label>
        <button type="submit" class="btn-primary btn-chico">Filtrar</button>
    </form>

    <table class="cuentas" id="tablaBitacora">
        <thead>
            <tr>
                <th>Fecha y hora</th>
                <th>Usuario</th>
                <th>Módulo</th>
                <th>Acción</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($registros === []): ?>
                <tr><td colspan="5" class="vacio">Sin resultados</td></tr>
            <?php endif; ?>
            <?php foreach ($registros as $fila): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i:s', strtotime($fila['fecha_hora']))) ?></td>
                    <td><?= e($fila['usuario']) ?></td>
                    <td><?= e($fila['modulo']) ?></td>
                    <td><?= e(trim($fila['accion'] . ' ' . ($fila['detalle'] ?? ''))) ?></td>
                    <td><?= e($fila['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <footer class="tabla-pie">
        <span>Mostrando <?= count($registros) ?> de <?= $total ?> registros · Solo lectura</span>
        <?php if ($paginas > 1): ?>
            <nav class="paginas">
                <?php if ($pagina > 1): ?><a class="btn-borde" href="<?= $urlPagina($pagina - 1) ?>">Anterior</a><?php endif; ?>
                <span>Página <?= $pagina ?> de <?= $paginas ?></span>
                <?php if ($pagina < $paginas): ?><a class="btn-borde" href="<?= $urlPagina($pagina + 1) ?>">Siguiente</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </footer>
</section>
