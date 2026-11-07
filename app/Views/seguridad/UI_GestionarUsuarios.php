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
$errorForm = $errorForm ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$inicialesDe = function (string $texto): string {
    $partes = preg_split('/[.\s_-]+/', $texto) ?: [];
    $iniciales = '';
    foreach (array_slice(array_filter($partes), 0, 2) as $parte) {
        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    return $iniciales !== '' ? $iniciales : '·';
};
$activos = count(array_filter($usuarios, fn ($fila) => $fila['estado']));
$vinculados = count(array_filter($usuarios, fn ($fila) => !empty($fila['id_medico']) || !empty($fila['id_paciente'])));
?>
<section class="hoja" data-ui="UI_GestionarUsuarios">
    <header class="hoja-cabecera">
        <div>
            <h1>Usuarios registrados</h1>
            <p class="lede">Cuentas de acceso, rol y vínculo con una ficha de médico o paciente.</p>
        </div>
        <a class="btn-primary btn-chico" data-fab href="<?= App::url('/usuarios?nuevo=1') ?>">Nuevo usuario</a>
    </header>

    <div class="stat-row">
        <article class="stat-card lift-card"><span>En esta vista</span><strong><?= count($usuarios) ?></strong></article>
        <article class="stat-card lift-card"><span>Activos</span><strong><?= $activos ?></strong></article>
        <article class="stat-card lift-card"><span>Inactivos</span><strong><?= count($usuarios) - $activos ?></strong></article>
        <article class="stat-card lift-card"><span>Con ficha vinculada</span><strong><?= $vinculados ?></strong></article>
    </div>

    <form class="filtros filtros-medicos" method="get" action="<?= App::url('/usuarios') ?>">
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
        <button type="submit" class="btn-primary btn-chico">Buscar</button>
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
                    <td class="quien">
                        <div class="quien-persona">
                            <span class="persona-marca" aria-hidden="true"><?= e($inicialesDe($fila['username'])) ?></span>
                            <div>
                                <strong><?= e($fila['username']) ?></strong>
                                <span><?= !empty($fila['id_medico']) ? 'Vinculado a un médico' : (!empty($fila['id_paciente']) ? 'Vinculado a un paciente' : 'Sin ficha vinculada') ?></span>
                            </div>
                        </div>
                    </td>
                    <td data-label="Correo"><?= e($fila['email']) ?></td>
                    <td data-label="Rol"><span class="marca-rol <?= e($marca) ?>"><?= e($fila['rol']) ?></span></td>
                    <td data-label="Estado"><span class="estado-pill<?= $fila['estado'] ? '' : ' bloqueada' ?>"><?= $fila['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="acciones">
                        <a class="btn-icono" href="<?= App::url('/usuarios?editar=' . (int) $fila['id_usuario']) ?>" title="Editar" aria-label="Editar a <?= e($fila['username']) ?>"><?= vs_icono('editar') ?></a>
                        <?php if ((int) $fila['id_usuario'] !== $idSesion): ?>
                            <form method="post" action="<?= App::url('/usuarios') ?>">
                                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                <input type="hidden" name="accion" value="reenviar">
                                <input type="hidden" name="id_usuario" value="<?= e((string) $fila['id_usuario']) ?>">
                                <button type="submit" class="btn-icono" title="Reenviar clave al correo" aria-label="Reenviar la clave temporal de <?= e($fila['username']) ?>" data-confirmar="¿Enviar una clave temporal nueva a <?= e($fila['email']) ?>?"><?= vs_icono('correo') ?></button>
                            </form>
                        <?php endif; ?>
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
            <?php if ($errorForm): ?>
                <p class="alert error" role="alert"><?= e($errorForm) ?></p>
            <?php endif; ?>
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_usuario" value="<?= e((string) $form['id_usuario']) ?>">

            <h3 class="subtitulo">Cuenta</h3>
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

            <h3 class="subtitulo">Vínculo clínico</h3>
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
                <p class="aviso-rango">Se generará una clave temporal y se enviará a este correo. La persona deberá cambiarla en el primer ingreso.</p>
            <?php endif; ?>

            <div class="modal-acciones">
                <a class="btn-borde" href="<?= App::url('/usuarios') ?>">Cancelar</a>
                <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
