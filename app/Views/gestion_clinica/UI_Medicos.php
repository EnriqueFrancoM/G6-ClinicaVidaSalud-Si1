<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$medicos = $medicos ?? [];
$especialidades = $especialidades ?? [];
$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
?>
<section class="hoja" data-ui="UI_Medicos">
    <header class="hoja-cabecera">
        <h1>Médicos</h1>
        <a class="btn-primary btn-chico" data-fab href="<?= App::url('/medicos?nuevo=1') ?>">Nuevo médico</a>
    </header>

    <form class="filtros filtros-bitacora filtros-medicos" method="get" action="<?= App::url('/medicos') ?>">
        <label class="field">
            <span>Buscar por nombre</span>
            <input type="search" name="nombre" value="<?= e($filtros['nombre']) ?>">
        </label>
        <label class="field">
            <span>Especialidad</span>
            <select name="especialidad">
                <option value="">Todas</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= e((string) $esp['id_especialidad']) ?>"<?= $filtros['especialidad'] === (int) $esp['id_especialidad'] ? ' selected' : '' ?>><?= e($esp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Estado</span>
            <select name="estado">
                <option value="1"<?= $filtros['estado'] === '1' ? ' selected' : '' ?>>Activos</option>
                <option value="0"<?= $filtros['estado'] === '0' ? ' selected' : '' ?>>Inactivos</option>
                <option value=""<?= $filtros['estado'] === '' ? ' selected' : '' ?>>Todos</option>
            </select>
        </label>
        <button type="submit" class="btn-primary btn-chico">Buscar</button>
    </form>

    <div class="tabla-responsive">
    <table class="cuentas" id="tablaMedicos">
        <thead>
                <tr>
                    <th>Médico</th>
                    <th>Reg. profesional</th>
                    <th>Especialidad</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
        </thead>
        <tbody>
            <?php if ($medicos === []): ?>
                <tr><td colspan="5" class="vacio">No hay médicos que coincidan con la búsqueda.</td></tr>
            <?php endif; ?>
            <?php foreach ($medicos as $fila): ?>
                <?php $nombre = $fila['nombres'] . ' ' . $fila['apellidos']; ?>
                <tr>
                    <td><?= e($nombre) ?> · <?= e($fila['codigo']) ?></td>
                    <td><?= e($fila['nro_registro_prof']) ?></td>
                    <td class="marcas">
                        <?php foreach (array_filter(array_map('trim', explode(',', (string) $fila['especialidades']))) as $esp): ?>
                            <span class="marca"><?= e($esp) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><span class="estado-pill<?= $fila['estado'] ? '' : ' bloqueada' ?>"><?= $fila['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="acciones">
                        <a class="btn-icono" href="<?= App::url('/medicos?editar=' . (int) $fila['id_medico']) ?>" title="Editar" aria-label="Editar a <?= e($nombre) ?>"><?= vs_icono('editar') ?></a>
                        <form method="post" action="<?= App::url('/medicos') ?>">
                            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="id_medico" value="<?= e((string) $fila['id_medico']) ?>">
                            <input type="hidden" name="estado" value="<?= $fila['estado'] ? '0' : '1' ?>">
                            <button type="submit" class="btn-icono<?= $fila['estado'] ? ' btn-peligro' : '' ?>" title="<?= $fila['estado'] ? 'Desactivar' : 'Activar' ?>" aria-label="<?= ($fila['estado'] ? 'Desactivar a ' : 'Activar a ') . e($nombre) ?>" data-confirmar="<?= ($fila['estado'] ? '¿Desactivar a ' : '¿Activar a ') . e($nombre) . '?' ?>"><?= vs_icono($fila['estado'] ? 'bloquear' : 'activar') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($form !== null): ?>
    <?php $nuevo = (int) $form['id_medico'] === 0; ?>
    <dialog class="modal modal-ancho" data-modal>
        <form id="formMedico" method="post" action="<?= App::url('/medicos') ?>">
            <h2><?= $nuevo ? 'Registrar médico' : 'Editar médico' ?></h2>
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_medico" value="<?= e((string) $form['id_medico']) ?>">
            <input type="hidden" name="codigo" value="<?= e($form['codigo']) ?>">

            <div class="modal-grid">
                <label class="field">
                    <span>Código</span>
                    <input type="text" value="<?= e($form['codigo'] . ($nuevo ? ' (automático)' : '')) ?>" disabled>
                </label>
                <label class="field<?= $invalido('nro_registro_prof') ?>">
                    <span>N.º registro profesional *</span>
                    <input type="text" name="nro_registro_prof" value="<?= e($form['nro_registro_prof']) ?>" maxlength="20" required>
                </label>
                <label class="field<?= $invalido('nombres') ?>">
                    <span>Nombres *</span>
                    <input type="text" name="nombres" value="<?= e($form['nombres']) ?>" maxlength="60" required>
                </label>
                <label class="field<?= $invalido('apellidos') ?>">
                    <span>Apellidos *</span>
                    <input type="text" name="apellidos" value="<?= e($form['apellidos']) ?>" maxlength="60" required>
                </label>
                <label class="field<?= $invalido('telefono') ?>">
                    <span>Teléfono *</span>
                    <input type="tel" name="telefono" value="<?= e($form['telefono']) ?>" maxlength="15" required>
                </label>
                <label class="field<?= $invalido('email') ?>">
                    <span>Correo electrónico</span>
                    <input type="email" name="email" value="<?= e($form['email']) ?>" maxlength="100">
                </label>
            </div>

            <fieldset class="field casillas<?= $invalido('especialidades') ?>" id="listaEspecialidades">
                <legend>Especialidad(es) *</legend>
                <?php foreach ($especialidades as $esp): ?>
                    <label class="permiso">
                        <input type="checkbox" name="especialidades[]" value="<?= e((string) $esp['id_especialidad']) ?>"<?= in_array((int) $esp['id_especialidad'], $form['especialidades'], true) ? ' checked' : '' ?>>
                        <span><?= e($esp['nombre']) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="modal-grid">
                <label class="field">
                    <span>Estado</span>
                    <select name="estado">
                        <option value="1"<?= $form['estado'] === '1' ? ' selected' : '' ?>>Activo</option>
                        <option value="0"<?= $form['estado'] === '0' ? ' selected' : '' ?>>Inactivo</option>
                    </select>
                </label>
                <?php if ($nuevo): ?>
                    <label class="field">
                        <span>Crear cuenta de usuario</span>
                        <select name="crear_cuenta">
                            <option value="0"<?= $form['crear_cuenta'] !== '1' ? ' selected' : '' ?>>No</option>
                            <option value="1"<?= $form['crear_cuenta'] === '1' ? ' selected' : '' ?>>Sí</option>
                        </select>
                    </label>
                <?php endif; ?>
            </div>

            <div class="modal-acciones">
                <a class="btn-borde" href="<?= App::url('/medicos') ?>">Cancelar</a>
                <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
