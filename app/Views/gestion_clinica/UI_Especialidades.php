<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$especialidades = $especialidades ?? [];
$mostrarForm = $mostrarForm ?? false;
$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$nueva = $form === null || (int) $form['id_especialidad'] === 0;
?>
<div data-ui="UI_Especialidades">
    <section class="hoja">
        <header class="hoja-cabecera">
            <h1>Especialidades</h1>
            <a class="btn-primary btn-chico" href="<?= App::url('/especialidades?nuevo=1') ?>">Nueva especialidad</a>
        </header>
        <div class="tabla-responsive">
        <table class="cuentas" id="tablaEspecialidades">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($especialidades === []): ?>
                    <tr><td colspan="5" class="vacio">Todavía no hay especialidades. Use Nueva especialidad.</td></tr>
                <?php endif; ?>
                <?php foreach ($especialidades as $fila): ?>
                    <tr<?= $form !== null && (int) $form['id_especialidad'] === (int) $fila['id_especialidad'] ? ' class="seleccionado"' : '' ?>>
                        <td><?= e(sprintf('ESP-%02d', $fila['id_especialidad'])) ?></td>
                        <td class="quien"><strong><?= e($fila['nombre']) ?></strong></td>
                        <td><?= e((string) $fila['descripcion']) ?></td>
                        <td><span class="estado-pill<?= $fila['estado'] ? '' : ' bloqueada' ?>"><?= $fila['estado'] ? 'Activa' : 'Inactiva' ?></span></td>
                        <td class="acciones">
                            <a class="btn-icono" href="<?= App::url('/especialidades?editar=' . (int) $fila['id_especialidad']) ?>" title="Editar" aria-label="Editar <?= e($fila['nombre']) ?>"><?= vs_icono('editar') ?></a>
                            <form method="post" action="<?= App::url('/especialidades') ?>">
                                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                <input type="hidden" name="accion" value="estado">
                                <input type="hidden" name="id_especialidad" value="<?= e((string) $fila['id_especialidad']) ?>">
                                <input type="hidden" name="estado" value="<?= $fila['estado'] ? '0' : '1' ?>">
                                <button type="submit" class="btn-icono<?= $fila['estado'] ? ' btn-peligro' : '' ?>" title="<?= $fila['estado'] ? 'Desactivar' : 'Activar' ?>" aria-label="<?= ($fila['estado'] ? 'Desactivar ' : 'Activar ') . e($fila['nombre']) ?>" data-confirmar="<?= ($fila['estado'] ? '¿Desactivar ' : '¿Activar ') . e($fila['nombre']) . '?' ?>"><?= vs_icono($fila['estado'] ? 'bloquear' : 'activar') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <?php if ($mostrarForm && $form !== null): ?>
        <dialog class="modal" data-modal>
            <form id="formEspecialidad" method="post" action="<?= App::url('/especialidades') ?>">
                <h2><?= $nueva ? 'Nueva especialidad' : 'Editar especialidad' ?></h2>
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_especialidad" value="<?= e((string) $form['id_especialidad']) ?>">

                <label class="field<?= $invalido('nombre') ?>">
                    <span>Nombre *</span>
                    <input type="text" name="nombre" value="<?= e($form['nombre']) ?>" maxlength="60" required>
                </label>
                <label class="field<?= $invalido('descripcion') ?>">
                    <span>Descripción (opcional)</span>
                    <textarea name="descripcion" maxlength="200" rows="3"><?= e($form['descripcion']) ?></textarea>
                </label>
                <label class="field">
                    <span>Estado</span>
                    <select name="estado">
                        <option value="1"<?= $form['estado'] === '1' ? ' selected' : '' ?>>Activa</option>
                        <option value="0"<?= $form['estado'] === '0' ? ' selected' : '' ?>>Inactiva</option>
                    </select>
                </label>

                <div class="modal-acciones">
                    <a class="btn-borde" href="<?= App::url('/especialidades') ?>">Cancelar</a>
                    <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
</div>
