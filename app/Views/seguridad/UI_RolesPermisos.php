<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$roles = $roles ?? [];
$permisos = $permisos ?? [];
$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$activo = fn ($estado) => $estado === true || $estado === 't' || $estado === '1' || $estado === 1;
?>
<div class="roles-layout" data-ui="UI_RolesPermisos">
    <section class="hoja">
        <header class="hoja-cabecera">
            <h1>Roles</h1>
            <a class="btn-primary btn-chico" href="<?= App::url('/roles?nuevo=1') ?>">+ Nuevo</a>
        </header>
        <table class="cuentas" id="tablaRoles">
            <tbody>
                <?php foreach ($roles as $rol): ?>
                    <?php $seleccionado = $form !== null && (int) $form['id_rol'] === (int) $rol['id_rol']; ?>
                    <tr<?= $seleccionado ? ' class="seleccionado"' : '' ?>>
                        <td><a href="<?= App::url('/roles?rol=' . (int) $rol['id_rol']) ?>"><?= e($rol['nombre']) ?></a></td>
                        <td><span class="estado-pill<?= $activo($rol['estado']) ? '' : ' bloqueada' ?>"><?= $activo($rol['estado']) ? 'Activo' : 'Inactivo' ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="hoja">
        <?php if ($form === null): ?>
            <p class="lede">Seleccione un rol de la lista o elija "Nuevo".</p>
        <?php else: ?>
            <form method="post" action="<?= App::url('/roles') ?>">
                <header class="hoja-cabecera">
                    <h1><?= (int) $form['id_rol'] === 0 ? 'Nuevo rol' : 'Permisos del rol: ' . e($form['nombre']) ?></h1>
                </header>
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <input type="hidden" name="id_rol" value="<?= e((string) $form['id_rol']) ?>">

                <div class="rol-datos">
                    <label class="field<?= $invalido('nombre') ?>">
                        <span>Nombre del rol *</span>
                        <input type="text" name="nombre" value="<?= e($form['nombre']) ?>" maxlength="40" required>
                    </label>
                    <label class="field<?= $invalido('descripcion') ?>">
                        <span>Descripción</span>
                        <input type="text" name="descripcion" value="<?= e($form['descripcion']) ?>" maxlength="150">
                    </label>
                    <label class="field">
                        <span>Estado</span>
                        <select name="estado">
                            <option value="1"<?= $form['estado'] === '1' ? ' selected' : '' ?>>Activo</option>
                            <option value="0"<?= $form['estado'] === '0' ? ' selected' : '' ?>>Inactivo</option>
                        </select>
                    </label>
                </div>

                <div class="matriz<?= $invalido('permisos') ?>" id="listaPermisos">
                    <?php foreach ($permisos as $modulo => $lista): ?>
                        <fieldset>
                            <legend><?= e($modulo) ?></legend>
                            <?php foreach ($lista as $permiso): ?>
                                <label class="permiso">
                                    <input type="checkbox" name="permisos[]" value="<?= e((string) $permiso['id_permiso']) ?>"<?= in_array((int) $permiso['id_permiso'], $form['permisos'], true) ? ' checked' : '' ?>>
                                    <span><?= e($permiso['nombre']) ?> <small><?= e($permiso['codigo']) ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                </div>

                <div class="modal-acciones">
                    <a class="btn-borde" href="<?= App::url('/roles' . ((int) $form['id_rol'] > 0 ? '?rol=' . (int) $form['id_rol'] : '')) ?>">Cancelar</a>
                    <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar cambios</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
