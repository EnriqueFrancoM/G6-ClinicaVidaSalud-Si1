<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$usuarios = $usuarios ?? [];
$roles = $roles ?? [];
$medicos = $medicos ?? [];
$pacientes = $pacientes ?? [];
$filtros = $filtros ?? ['buscar' => '', 'rol' => null, 'estado' => ''];
$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
?>
<section class="hoja" data-ui="UI_GestionarUsuarios">
    <header class="hoja-cabecera">
        <h1>Usuarios registrados</h1>
        <a class="btn-primary btn-chico" data-fab href="<?= App::url('/usuarios?nuevo=1') ?>">Nuevo usuario</a>
    </header>

    <form class="filtros" method="get" action="<?= App::url('/usuarios') ?>">
        <label class="field">
            <span>Buscar</span>
            <input type="search" name="buscar" value="<?= e($filtros['buscar']) ?>" placeholder="nombre de usuario o correo">
        </label>
        <label class="field">
            <span>Rol</span>
            <select name="rol" data-autoenviar>
                <option value="">Todos</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= e((string) $rol['id_rol']) ?>"<?= (int) $filtros['rol'] === (int) $rol['id_rol'] ? ' selected' : '' ?>><?= e($rol['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Estado</span>
            <select name="estado" data-autoenviar>
                <option value="">Todos</option>
                <option value="1"<?= $filtros['estado'] === '1' ? ' selected' : '' ?>>Activo</option>
                <option value="0"<?= $filtros['estado'] === '0' ? ' selected' : '' ?>>Inactivo</option>
            </select>
        </label>
    </form>

    <div class="tabla-responsive">
    <table class="cuentas" id="tablaUsuarios">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($usuarios === []): ?>
                <tr><td colspan="5" class="vacio">No hay usuarios que coincidan con el filtro.</td></tr>
            <?php endif; ?>
            <?php foreach ($usuarios as $fila): ?>
                <?php $marca = match ($fila['rol']) {
                    'Administrador' => 'admin',
                    'Médico' => 'medico',
                    'Recepcionista' => 'recepcion',
                    'Paciente' => 'paciente',
                    default => '',
                }; ?>
                <tr>
                    <td class="quien"><strong><?= e($fila['username']) ?></strong></td>
                    <td><?= e($fila['email']) ?></td>
                    <td><span class="marca-rol <?= e($marca) ?>"><?= e($fila['rol']) ?></span></td>
                    <td><span class="estado-pill<?= $fila['estado'] ? '' : ' bloqueada' ?>"><?= $fila['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="acciones">
                        <a class="btn-icono" href="<?= App::url('/usuarios?editar=' . (int) $fila['id_usuario']) ?>" title="Editar" aria-label="Editar a <?= e($fila['username']) ?>"><?= vs_icono('editar') ?></a>
                        <?php if (!($fila['estado'] && (int) $fila['id_usuario'] === $idSesion)): ?>
                            <form method="post" action="<?= App::url('/usuarios') ?>">
                                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                <input type="hidden" name="accion" value="estado">
                                <input type="hidden" name="id_usuario" value="<?= e((string) $fila['id_usuario']) ?>">
                                <input type="hidden" name="estado" value="<?= $fila['estado'] ? '0' : '1' ?>">
                                <button type="submit" class="btn-icono<?= $fila['estado'] ? ' btn-peligro' : '' ?>" title="<?= $fila['estado'] ? 'Desactivar' : 'Activar' ?>" aria-label="<?= $fila['estado'] ? 'Desactivar el acceso de ' . e($fila['username']) : 'Activar el acceso de ' . e($fila['username']) ?>" data-confirmar="<?= $fila['estado'] ? '¿Desactivar el acceso de ' . e($fila['username']) . '?' : '¿Activar el acceso de ' . e($fila['username']) . '?' ?>"><?= vs_icono($fila['estado'] ? 'bloquear' : 'activar') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($form !== null): ?>
    <?php $nuevo = (int) $form['id_usuario'] === 0; ?>
    <dialog class="modal" data-modal>
        <form id="formUsuario" method="post" action="<?= App::url('/usuarios') ?>">
            <h2><?= $nuevo ? 'Registrar usuario' : 'Editar usuario' ?></h2>
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_usuario" value="<?= e((string) $form['id_usuario']) ?>">

            <div class="modal-grid">
                <label class="field<?= $invalido('username') ?>">
                    <span>Nombre de usuario *</span>
                    <input type="text" name="username" value="<?= e($form['username']) ?>" maxlength="40" required>
                </label>
                <label class="field<?= $invalido('email') ?>">
                    <span>Correo electrónico *</span>
                    <input type="email" name="email" value="<?= e($form['email']) ?>" maxlength="100" required>
                </label>
                <label class="field<?= $invalido('id_rol') ?>">
                    <span>Rol *</span>
                    <select name="id_rol" id="cboRol" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= e((string) $rol['id_rol']) ?>"<?= (string) $form['id_rol'] === (string) $rol['id_rol'] ? ' selected' : '' ?>><?= e($rol['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field<?= $invalido('estado') ?>">
                    <span>Estado</span>
                    <select name="estado">
                        <option value="1"<?= $form['estado'] === '1' ? ' selected' : '' ?>>Activo</option>
                        <option value="0"<?= $form['estado'] === '0' ? ' selected' : '' ?>>Inactivo</option>
                    </select>
                </label>
            </div>

            <label class="field<?= $invalido('vinculo') ?>">
                <span>Vincular con médico/paciente (opcional)</span>
                <select name="vinculo">
                    <option value="">— ninguno —</option>
                    <optgroup label="Médicos">
                        <?php foreach ($medicos as $m): ?>
                            <?php $valor = 'medico:' . $m['id_medico']; ?>
                            <?php if ($m['id_usuario'] === null || $form['vinculo'] === $valor): ?>
                                <option value="<?= e($valor) ?>"<?= $form['vinculo'] === $valor ? ' selected' : '' ?>><?= e($m['codigo'] . ' – ' . $m['nombres'] . ' ' . $m['apellidos']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Pacientes">
                        <?php foreach ($pacientes as $p): ?>
                            <?php $valor = 'paciente:' . $p['id_paciente']; ?>
                            <?php if ($p['id_usuario'] === null || $form['vinculo'] === $valor): ?>
                                <option value="<?= e($valor) ?>"<?= $form['vinculo'] === $valor ? ' selected' : '' ?>><?= e($p['codigo'] . ' – ' . $p['nombres'] . ' ' . $p['apellidos']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </label>

            <?php if ($nuevo): ?>
                <p class="nota">Se generará una contraseña temporal. La persona deberá cambiarla en el primer ingreso.</p>
            <?php endif; ?>

            <div class="modal-acciones">
                <a class="btn-borde" href="<?= App::url('/usuarios') ?>">Cancelar</a>
                <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
