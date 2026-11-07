<?php

use App\Core\App;
use App\Core\Session;
use App\Models\SolicitudEspera;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$fecha = fn (?string $f) => $f ? date('d/m/Y', strtotime($f)) : '';
?>
<section class="hoja" data-ui="UI_ListaEspera">
    <header class="hoja-cabecera">
        <div>
            <h1>Lista de espera</h1>
            <p class="lede">Al liberarse un horario compatible se ofrece al primero de la cola, con <?= SolicitudEspera::HORAS_OFERTA ?> horas para responder.</p>
        </div>
        <a class="btn-primary btn-chico" data-fab href="<?= App::url('/lista-espera?nuevo=1') ?>">Inscribir paciente</a>
    </header>

    <form class="filtros filtros-codigo" method="get" action="<?= App::url('/lista-espera') ?>">
        <label class="field">
            <span>Estado</span>
            <select name="estado" data-autoenviar>
                <option value="">Vigentes</option>
                <?php foreach (SolicitudEspera::ESTADOS as $valor => $texto): ?>
                    <option value="<?= $valor ?>"<?= $estado === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="tabla-responsive">
    <table class="cuentas" id="tablaEspera">
        <thead>
            <tr><th>Paciente</th><th>Especialidad</th><th>Preferencias</th><th>Ingreso</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php if ($solicitudes === []): ?>
                <tr><td colspan="6" class="vacio">No hay solicitudes en la lista de espera.</td></tr>
            <?php endif; ?>
            <?php foreach ($solicitudes as $i => $s): ?>
                <tr>
                    <td class="quien">
                        <strong><?= e($s['paciente']) ?></strong>
                        <span><?= e($s['paciente_codigo']) ?> · <?= e($s['telefono']) ?></span>
                    </td>
                    <td><?= e($s['especialidad']) ?></td>
                    <td>
                        <?= e($s['medico_preferido'] ?: 'Cualquier médico') ?>
                        <?php if ($s['fecha_desde'] || $s['fecha_hasta']): ?>
                            <br><small><?= e(trim(($s['fecha_desde'] ? 'desde ' . $fecha($s['fecha_desde']) : '') . ' ' . ($s['fecha_hasta'] ? 'hasta ' . $fecha($s['fecha_hasta']) : ''))) ?></small>
                        <?php endif; ?>
                        <?php if ($s['preferencias']): ?><br><small><?= e($s['preferencias']) ?></small><?php endif; ?>
                    </td>
                    <td><?= e($s['ingreso']) ?></td>
                    <td>
                        <span class="estado-pill estado-espera-<?= e(strtolower($s['estado'])) ?>"><?= e(SolicitudEspera::ESTADOS[$s['estado']]) ?></span>
                        <?php if ($s['estado'] === 'OFERTADA'): ?>
                            <br><small>Oferta: <?= e($fecha($s['oferta_fecha']) . ' ' . $s['oferta_inicio'] . ' · ' . $s['oferta_medico']) ?><br>Responder hasta <?= e($s['oferta_vence']) ?></small>
                        <?php elseif ($s['estado'] === 'ASIGNADA' && $s['cita_codigo']): ?>
                            <br><small>Cita <?= e($s['cita_codigo']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="acciones">
                        <?php if (in_array($s['estado'], ['ACTIVA', 'OFERTADA'], true)): ?>
                            <form method="post" action="<?= App::url('/lista-espera') ?>" class="acciones-espera">
                                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                <input type="hidden" name="id_solicitud" value="<?= (int) $s['id_solicitud'] ?>">
                                <?php if ($s['estado'] === 'OFERTADA'): ?>
                                    <button type="submit" name="accion" value="aceptar" class="btn-primary btn-chico">Aceptar oferta</button>
                                    <button type="submit" name="accion" value="rechazar" class="btn-borde">Rechazar</button>
                                <?php endif; ?>
                                <button type="submit" name="accion" value="cancelar" class="btn-icono btn-peligro" title="Retirar de la lista"
                                        aria-label="Retirar a <?= e($s['paciente']) ?>" data-confirmar="¿Retirar a <?= e($s['paciente']) ?> de la lista de espera?"><?= vs_icono('quitar') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($abrirForm): ?>
    <dialog class="modal" data-modal>
        <form method="post" action="<?= App::url('/lista-espera') ?>">
            <header class="hoja-cabecera">
                <h2>Inscribir en lista de espera</h2>
            </header>
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="accion" value="registrar">
            <!-- Paso 1: paciente y especialidad. Paso 2: preferencias -->
            <label class="field<?= $invalido('paciente') ?>"><span>Paciente (CI o código) *</span>
                <input type="text" name="paciente" value="<?= e($form['paciente']) ?>" required></label>
            <div class="modal-grid">
                <label class="field<?= $invalido('id_especialidad') ?>"><span>Especialidad *</span>
                    <select name="id_especialidad" data-especialidad required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($especialidades as $esp): ?>
                            <option value="<?= (int) $esp['id_especialidad'] ?>"<?= (string) $form['id_especialidad'] === (string) $esp['id_especialidad'] ? ' selected' : '' ?>><?= e($esp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="field<?= $invalido('id_medico') ?>"><span>Médico (opcional)</span>
                    <select name="id_medico" data-medico>
                        <option value="">Cualquier médico</option>
                        <?php foreach ($medicos as $m): ?>
                            <option value="<?= (int) $m['id_medico'] ?>" data-especialidades="<?= e(implode(',', $m['ids_especialidad'])) ?>"<?= (string) $form['id_medico'] === (string) $m['id_medico'] ? ' selected' : '' ?>><?= e($m['nombres'] . ' ' . $m['apellidos']) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="field<?= $invalido('fecha_desde') ?>"><span>Desde</span>
                    <input type="date" name="fecha_desde" value="<?= e($form['fecha_desde']) ?>" min="<?= date('Y-m-d') ?>"></label>
                <label class="field<?= $invalido('fecha_hasta') ?>"><span>Hasta</span>
                    <input type="date" name="fecha_hasta" value="<?= e($form['fecha_hasta']) ?>" min="<?= date('Y-m-d') ?>"></label>
            </div>
            <label class="field<?= $invalido('preferencias') ?>"><span>Preferencias</span>
                <input type="text" name="preferencias" value="<?= e($form['preferencias']) ?>" maxlength="200" placeholder="Ej. solo por la mañana"></label>
            <div class="modal-acciones">
                <a class="btn-borde" href="<?= App::url('/lista-espera') ?>">Cancelar</a>
                <button type="submit" class="btn-primary btn-chico">Inscribir</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
