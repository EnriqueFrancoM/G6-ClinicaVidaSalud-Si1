<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Paciente;
use function App\Core\e;

require_once __DIR__ . '/../iconos.php';

$pacientes = $pacientes ?? [];
$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$fecha = fn (?string $f) => $f ? date('d/m/Y', strtotime($f)) : '';
?>
<section class="hoja" data-ui="UI_Pacientes">
    <header class="hoja-cabecera">
        <h1>Búsqueda de pacientes</h1>
        <a class="btn-primary btn-chico" href="<?= App::url('/pacientes?nuevo=1') ?>">Nuevo paciente</a>
    </header>

    <form class="filtros filtros-bitacora filtros-medicos" method="get" action="<?= App::url('/pacientes') ?>">
        <label class="field">
            <span>CI</span>
            <input type="search" name="ci" id="txtBuscar" value="<?= e($filtros['ci']) ?>">
        </label>
        <label class="field">
            <span>Nombre o apellido</span>
            <input type="search" name="nombre" value="<?= e($filtros['nombre']) ?>">
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

    <table class="cuentas" id="tablaPacientes">
        <thead>
            <tr><th>Paciente</th><th>CI</th><th>Nacimiento</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php if ($pacientes === []): ?>
                <tr><td colspan="6" class="vacio">No se encontraron pacientes. Regístrelo con Nuevo paciente.</td></tr>
            <?php endif; ?>
            <?php foreach ($pacientes as $p): ?>
                <?php $nombre = $p['nombres'] . ' ' . $p['apellidos']; ?>
                <tr>
                    <td class="quien">
                        <a href="<?= App::url('/pacientes/ficha?id=' . (int) $p['id_paciente']) ?>"><strong><?= e($nombre) ?></strong></a>
                        <span><?= e($p['codigo']) ?></span>
                    </td>
                    <td><?= e((string) $p['ci']) ?></td>
                    <td><?= e($fecha($p['fecha_nacimiento'])) ?></td>
                    <td><?= e($p['telefono']) ?></td>
                    <td><span class="estado-pill<?= $p['estado'] ? '' : ' bloqueada' ?>"><?= $p['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="acciones">
                        <a class="btn-icono" href="<?= App::url('/pacientes?editar=' . (int) $p['id_paciente']) ?>" title="Editar" aria-label="Editar a <?= e($nombre) ?>"><?= vs_icono('editar') ?></a>
                        <a class="btn-icono" href="<?= App::url('/pacientes/ficha?id=' . (int) $p['id_paciente'] . '&historial=1') ?>" title="Historial" aria-label="Historial de <?= e($nombre) ?>"><?= vs_icono('historial') ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($form !== null): ?>
    <?php $nuevo = (int) $form['id_paciente'] === 0; ?>
    <dialog class="modal modal-paciente" data-modal>
        <form id="formPaciente" method="post" action="<?= App::url('/pacientes') ?>">
            <header class="hoja-cabecera">
                <h2><?= $nuevo ? 'Registrar paciente' : 'Editar paciente' ?></h2>
                <small class="nota">Los campos con * son obligatorios</small>
            </header>
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_paciente" value="<?= e((string) $form['id_paciente']) ?>">

            <?php if (!empty($existente)): ?>
                <!-- E1: el CI ya existe; se ofrece abrir la ficha existente -->
                <p class="alert error">Ya existe un paciente registrado con ese CI.
                    <a href="<?= App::url('/pacientes/ficha?id=' . (int) $existente['id_paciente']) ?>">Abrir ficha <?= e($existente['codigo']) ?></a></p>
            <?php endif; ?>

            <h3 class="subtitulo">Datos personales</h3>
            <div class="grilla-4">
                <label class="field<?= $invalido('ci') ?>"><span>CI *</span>
                    <input type="text" name="ci" value="<?= e($form['ci']) ?>" maxlength="15" required></label>
                <label class="field<?= $invalido('nombres') ?>"><span>Nombres *</span>
                    <input type="text" name="nombres" value="<?= e($form['nombres']) ?>" maxlength="60" required></label>
                <label class="field<?= $invalido('apellidos') ?>"><span>Apellidos *</span>
                    <input type="text" name="apellidos" value="<?= e($form['apellidos']) ?>" maxlength="60" required></label>
                <label class="field<?= $invalido('fecha_nacimiento') ?>"><span>Fecha de nacimiento *</span>
                    <input type="date" name="fecha_nacimiento" value="<?= e($form['fecha_nacimiento']) ?>" max="<?= date('Y-m-d') ?>" required></label>
                <label class="field<?= $invalido('sexo') ?>"><span>Sexo</span>
                    <select name="sexo">
                        <option value="">—</option>
                        <?php foreach ($sexos as $valor => $texto): ?>
                            <option value="<?= $valor ?>"<?= $form['sexo'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="field<?= $invalido('telefono') ?>"><span>Teléfono *</span>
                    <input type="tel" name="telefono" value="<?= e($form['telefono']) ?>" maxlength="15" required></label>
                <label class="field<?= $invalido('email') ?>"><span>Correo electrónico</span>
                    <input type="email" name="email" value="<?= e($form['email']) ?>" maxlength="100"></label>
                <?php if (!$nuevo): ?>
                    <label class="field"><span>Estado</span>
                        <select name="estado">
                            <option value="1"<?= $form['estado'] === '1' ? ' selected' : '' ?>>Activo</option>
                            <option value="0"<?= $form['estado'] === '0' ? ' selected' : '' ?>>Inactivo</option>
                        </select></label>
                <?php endif; ?>
            </div>
            <label class="field<?= $invalido('direccion') ?>"><span>Dirección</span>
                <input type="text" name="direccion" value="<?= e($form['direccion']) ?>" maxlength="150"></label>

            <h3 class="subtitulo">Información médica</h3>
            <div class="grilla-3">
                <label class="field<?= $invalido('tipo_sangre') ?>"><span>Tipo de sangre</span>
                    <select name="tipo_sangre">
                        <option value="">—</option>
                        <?php foreach (Paciente::TIPOS_SANGRE as $tipo): ?>
                            <option value="<?= $tipo ?>"<?= $form['tipo_sangre'] === $tipo ? ' selected' : '' ?>><?= $tipo ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="field"><span>Alergias</span>
                    <input type="text" name="alergias" value="<?= e($form['alergias']) ?>"></label>
                <label class="field"><span>Enfermedades crónicas</span>
                    <input type="text" name="enfermedades_cronicas" value="<?= e($form['enfermedades_cronicas']) ?>"></label>
            </div>

            <h3 class="subtitulo">Contacto de emergencia</h3>
            <div class="grilla-3">
                <label class="field<?= $invalido('contacto_nombre') ?>"><span>Nombre</span>
                    <input type="text" name="contacto_nombre" value="<?= e($form['contacto_nombre']) ?>" maxlength="100"></label>
                <label class="field<?= $invalido('contacto_parentesco') ?>"><span>Parentesco</span>
                    <input type="text" name="contacto_parentesco" value="<?= e($form['contacto_parentesco']) ?>" maxlength="30" list="parentescos"></label>
                <label class="field<?= $invalido('contacto_telefono') ?>"><span>Teléfono</span>
                    <input type="tel" name="contacto_telefono" value="<?= e($form['contacto_telefono']) ?>" maxlength="15"></label>
            </div>
            <datalist id="parentescos">
                <option value="Padre"><option value="Madre"><option value="Esposo(a)"><option value="Hijo(a)"><option value="Hermano(a)"><option value="Otro">
            </datalist>

            <div class="modal-acciones">
                <a class="btn-borde" href="<?= App::url('/pacientes') ?>">Cancelar</a>
                <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar paciente</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
