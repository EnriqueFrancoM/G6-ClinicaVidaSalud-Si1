<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Cita;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$titulos = ['pendientes' => 'Pendientes', 'confirmadas' => 'Confirmadas', 'completadas' => 'Completadas', 'canceladas' => 'Canceladas'];
$fecha = fn (string $f) => date('d/m/Y', strtotime($f));
?>
<section class="hoja" data-ui="UI_MisCitas">
    <header class="hoja-cabecera">
        <div>
            <h1>Mis citas</h1>
            <p class="lede">Reserve una cita y siga el estado de las suyas.</p>
        </div>
        <?php if ($paciente !== null): ?>
            <a class="btn-primary btn-chico" data-fab href="<?= App::url('/portal/citas?reservar=1') ?>">Reservar cita</a>
        <?php endif; ?>
    </header>

    <?php if ($paciente === null): ?>
        <p class="aviso-rango">Complete <a href="<?= App::url('/portal/perfil') ?>">su perfil</a> para reservar citas.</p>
    <?php else: ?>
        <?php foreach ($avisos as $a): ?>
            <p class="aviso-rango">Recordatorio: cita <?= e($a['codigo']) ?> con <?= e($a['medico']) ?> el <?= e($fecha($a['fecha'])) ?> a las <?= e($a['hora_inicio']) ?>.</p>
        <?php endforeach; ?>

        <?php if ($reservar): ?>
            <!-- Pasos 2 y 3: filtrar especialidad y médico, seleccionar horario -->
            <section class="bloque-reserva">
                <h2 class="subtitulo">Reservar cita</h2>
                <form class="filtros filtros-cita" method="get" action="<?= App::url('/portal/citas') ?>" data-form-cita>
                    <label class="field<?= $invalido('id_especialidad') ?>"><span>Especialidad</span>
                        <select name="id_especialidad" data-especialidad required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= (int) $esp['id_especialidad'] ?>"<?= $filtro['id_especialidad'] === (int) $esp['id_especialidad'] ? ' selected' : '' ?>><?= e($esp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label class="field<?= $invalido('id_medico') ?>"><span>Médico</span>
                        <select name="id_medico" data-medico required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?= (int) $m['id_medico'] ?>" data-especialidades="<?= e(implode(',', $m['ids_especialidad'])) ?>"<?= $filtro['id_medico'] === (int) $m['id_medico'] ? ' selected' : '' ?>><?= e($m['nombres'] . ' ' . $m['apellidos']) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label class="field<?= $invalido('fecha') ?>"><span>Fecha</span>
                        <input type="date" name="fecha" value="<?= e($filtro['fecha']) ?>" min="<?= date('Y-m-d') ?>" required></label>
                    <button type="submit" class="btn-primary btn-chico">Ver horarios</button>
                </form>

                <?php if ($cupos !== null): ?>
                    <form method="post" action="<?= App::url('/portal/citas') ?>" class="form-cupos">
                        <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                        <input type="hidden" name="accion" value="reservar">
                        <input type="hidden" name="id_especialidad" value="<?= (int) $filtro['id_especialidad'] ?>">
                        <input type="hidden" name="id_medico" value="<?= (int) $filtro['id_medico'] ?>">
                        <input type="hidden" name="fecha" value="<?= e($filtro['fecha']) ?>">
                        <?php if ($cupos === []): ?>
                            <p class="vacio-dia">No hay horarios libres ese día. Pruebe otra fecha.</p>
                        <?php else: ?>
                            <div class="cupos<?= $invalido('hora_inicio') ?>" role="radiogroup" aria-label="Horarios libres">
                                <?php foreach ($cupos as $i => $cupo): ?>
                                    <label class="cupo">
                                        <input type="radio" name="hora_inicio" value="<?= e($cupo['hora_inicio']) ?>" required<?= $i === 0 ? ' checked' : '' ?>>
                                        <span><?= e($cupo['hora_inicio']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <label class="field<?= $invalido('motivo') ?>"><span>Motivo de la consulta</span>
                                <input type="text" name="motivo" value="<?= e($filtro['motivo']) ?>" maxlength="200"></label>
                            <div class="modal-acciones">
                                <a class="btn-borde" href="<?= App::url('/portal/citas') ?>">Cerrar</a>
                                <button type="submit" class="btn-primary btn-chico" name="btnReservar">Reservar</button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Paso 6: código y estado en mis citas -->
        <?php foreach ($titulos as $grupo => $titulo): ?>
            <h2 class="subtitulo"><?= e($titulo) ?> <small>(<?= count($grupos[$grupo]) ?>)</small></h2>
            <?php if ($grupos[$grupo] === []): ?>
                <p class="vacio-dia">Sin citas <?= e(mb_strtolower($titulo)) ?>.</p>
            <?php else: ?>
                <ol class="agenda-dia mis-citas">
                    <?php foreach ($grupos[$grupo] as $c): ?>
                        <li>
                            <time datetime="<?= e($c['fecha'] . 'T' . $c['hora_inicio']) ?>"><?= e($c['hora_inicio']) ?></time>
                            <div class="quien">
                                <strong><?= e($c['especialidad']) ?> · <?= e($c['medico']) ?></strong>
                                <span><?= e($fecha($c['fecha'])) ?> · <?= e($c['codigo']) ?><?= $c['motivo'] ? ' · ' . e($c['motivo']) : '' ?></span>
                            </div>
                            <div class="acciones-cita">
                                <span class="estado-pill estado-<?= e(strtolower($c['estado'])) ?>"><?= e(Cita::ESTADOS[$c['estado']]) ?></span>
                                <?php if (in_array($c['estado'], Cita::PENDIENTES, true) && strtotime($c['fecha'] . ' ' . $c['hora_inicio']) > time()): ?>
                                    <a class="btn-icono" href="<?= App::url('/citas/comprobante?codigo=' . rawurlencode($c['codigo'])) ?>" title="Comprobante QR" aria-label="Comprobante de <?= e($c['codigo']) ?>"><?= vs_icono('qr') ?></a>
                                    <form method="post" action="<?= App::url('/portal/citas') ?>">
                                        <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                        <input type="hidden" name="codigo" value="<?= e($c['codigo']) ?>">
                                        <?php if ($c['estado'] === 'PROGRAMADA'): ?>
                                            <button type="submit" name="accion" value="confirmar" class="btn-borde">Confirmar</button>
                                        <?php endif; ?>
                                        <button type="submit" name="accion" value="cancelar" class="btn-borde btn-peligro"
                                                data-confirmar="¿Cancelar la cita <?= e($c['codigo']) ?>? El horario quedará libre.">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
