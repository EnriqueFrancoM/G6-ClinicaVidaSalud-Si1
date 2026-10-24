<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$roles = $roles ?? [];
$permisos = $permisos ?? [];
$campoError = $campoError ?? null;
$editarMeta = $editarMeta ?? false;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$activo = fn ($estado) => $estado === true || $estado === 't' || $estado === '1' || $estado === 1;
$candado = ['USU_GESTIONAR', 'ROL_GESTIONAR', 'BIT_CONSULTAR'];
?>
<div class="roles-pagina" data-ui="UI_RolesPermisos">
    <form method="post" action="<?= App::url('/roles') ?>">
        <header class="hoja hoja-cabecera roles-cabecera">
            <div>
                <h1>Roles y permisos</h1>
                <p class="lede">Cada fila es un rol. El nombre se edita al pulsarlo. Los tres permisos de seguridad del administrador no se pueden quitar.</p>
            </div>
            <div class="acciones-cabecera">
                <a class="btn-borde" href="<?= App::url('/roles?nuevo=1') ?>">Nuevo rol</a>
                <button type="submit" class="btn-primary btn-chico" data-confirmar="¿Guardar los permisos de todos los roles?">Guardar permisos</button>
            </div>
        </header>
        <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
        <input type="hidden" name="accion" value="matriz">

        <div class="modulos-permiso" id="listaPermisos">
            <?php foreach ($permisos as $modulo => $lista): ?>
                <section class="hoja modulo-permiso">
                    <h2><?= e($modulo) ?></h2>
                    <table class="matriz-roles">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <?php foreach ($lista as $permiso): ?>
                                    <th class="permiso-col" title="<?= e($permiso['codigo']) ?>"><?= e($permiso['nombre']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $rol): ?>
                                <?php
                                $idRol = (int) $rol['id_rol'];
                                $esAdmin = $rol['nombre'] === 'Administrador';
                                $tiene = array_flip($rol['permisos']);
                                ?>
                                <tr class="<?= $activo($rol['estado']) ? '' : 'rol-inactivo' ?>">
                                    <th scope="row">
                                        <a class="rol-nombre" href="<?= App::url('/roles?datos=' . $idRol) ?>"><?= e($rol['nombre']) ?></a>
                                        <?php if (!$activo($rol['estado'])): ?><span class="estado-pill bloqueada">Inactivo</span><?php endif; ?>
                                    </th>
                                    <?php foreach ($lista as $permiso): ?>
                                        <?php
                                        $codigo = $permiso['codigo'];
                                        $marcado = isset($tiene[$codigo]);
                                        $fijo = $esAdmin && in_array($codigo, $candado, true);
                                        ?>
                                        <td>
                                            <?php if ($fijo): ?>
                                                <input type="hidden" name="matriz[<?= $idRol ?>][]" value="<?= (int) $permiso['id_permiso'] ?>">
                                            <?php endif; ?>
                                            <input type="checkbox"
                                                <?= $fijo ? 'disabled' : 'name="matriz[' . $idRol . '][]"' ?>
                                                value="<?= (int) $permiso['id_permiso'] ?>"
                                                <?= $marcado || $fijo ? ' checked' : '' ?>
                                                aria-label="<?= e($permiso['nombre'] . ' para ' . $rol['nombre']) ?>"
                                                <?= $fijo ? 'title="El administrador no puede perder este permiso"' : '' ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if ($editarMeta && $form !== null): ?>
        <?php $nuevo = (int) $form['id_rol'] === 0; ?>
        <?php $esAdmin = !$nuevo && $form['nombre'] === 'Administrador'; ?>
        <dialog class="modal" data-modal>
            <form method="post" action="<?= App::url('/roles') ?>">
                <h2><?= $nuevo ? 'Nuevo rol' : 'Datos de ' . e($form['nombre']) ?></h2>
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <input type="hidden" name="id_rol" value="<?= e((string) $form['id_rol']) ?>">
                <?php foreach ($form['permisos'] as $idPermiso): ?>
                    <input type="hidden" name="permisos[]" value="<?= (int) $idPermiso ?>">
                <?php endforeach; ?>
                <?php if ($esAdmin): ?>
                    <input type="hidden" name="nombre" value="<?= e($form['nombre']) ?>">
                    <input type="hidden" name="estado" value="1">
                <?php endif; ?>

                <label class="field<?= $invalido('nombre') ?>">
                    <span>Nombre del rol *</span>
                    <input type="text" name="<?= $esAdmin ? '' : 'nombre' ?>" value="<?= e($form['nombre']) ?>" maxlength="40" <?= $esAdmin ? 'disabled' : 'required' ?>>
                </label>
                <label class="field<?= $invalido('descripcion') ?>">
                    <span>Descripción</span>
                    <input type="text" name="descripcion" value="<?= e($form['descripcion']) ?>" maxlength="150">
                </label>
                <label class="field">
                    <span>Estado</span>
                    <select name="<?= $esAdmin ? '' : 'estado' ?>" <?= $esAdmin ? 'disabled' : '' ?>>
                        <option value="1"<?= $form['estado'] === '1' ? ' selected' : '' ?>>Activo</option>
                        <option value="0"<?= $form['estado'] === '0' ? ' selected' : '' ?>>Inactivo</option>
                    </select>
                </label>
                <?php if ($nuevo): ?>
                    <p class="nota">Después de crearlo, marque sus permisos en la matriz y pulse Guardar permisos.</p>
                <?php endif; ?>
                <div class="modal-acciones">
                    <a class="btn-borde" href="<?= App::url('/roles') ?>">Cancelar</a>
                    <button type="submit" class="btn-primary btn-chico">Guardar datos</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
</div>
