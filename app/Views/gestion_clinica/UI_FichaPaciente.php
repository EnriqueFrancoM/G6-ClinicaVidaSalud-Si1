<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$p = $paciente;
$fecha = fn (?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$valor = fn ($v) => ($v === null || $v === '') ? '—' : $v;
$edad = (new DateTime($p['fecha_nacimiento']))->diff(new DateTime('today'))->y;
?>
<section class="hoja" data-ui="UI_Pacientes">
    <header class="hoja-cabecera">
        <h1>Ficha del paciente <?= e($p['codigo']) ?></h1>
        <div class="acciones-cabecera">
            <a class="btn-borde" href="<?= App::url('/pacientes') ?>">Volver</a>
            <a class="btn-borde" href="<?= App::url('/pacientes?editar=' . (int) $p['id_paciente']) ?>">Editar</a>
            <?php if (!$verHistorial): ?>
                <a class="btn-primary btn-chico" href="<?= App::url('/pacientes/ficha?id=' . (int) $p['id_paciente'] . '&historial=1') ?>">Historial de citas</a>
            <?php endif; ?>
        </div>
    </header>

    <dl class="ficha">
        <div><dt>Paciente</dt><dd><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></dd></div>
        <div><dt>CI</dt><dd><?= e($valor($p['ci'])) ?></dd></div>
        <div><dt>Nacimiento</dt><dd><?= e($fecha($p['fecha_nacimiento'])) ?> (<?= $edad ?> años)</dd></div>
        <div><dt>Sexo</dt><dd><?= e($sexos[$p['sexo']] ?? '—') ?></dd></div>
        <div><dt>Teléfono</dt><dd><?= e($p['telefono']) ?></dd></div>
        <div><dt>Correo</dt><dd><?= e($valor($p['email'])) ?></dd></div>
        <div><dt>Dirección</dt><dd><?= e($valor($p['direccion'])) ?></dd></div>
        <div><dt>Estado</dt><dd><span class="estado-pill<?= $p['estado'] ? '' : ' bloqueada' ?>"><?= $p['estado'] ? 'Activo' : 'Inactivo' ?></span></dd></div>
        <div><dt>Tipo de sangre</dt><dd><?= e($valor($p['tipo_sangre'])) ?></dd></div>
        <div><dt>Alergias</dt><dd><?= e($valor($p['alergias'])) ?></dd></div>
        <div><dt>Enfermedades crónicas</dt><dd><?= e($valor($p['enfermedades_cronicas'])) ?></dd></div>
        <div><dt>Contacto de emergencia</dt><dd><?= $p['contacto_nombre'] ? e($p['contacto_nombre'] . ' (' . $p['contacto_parentesco'] . ') · ' . $p['contacto_telefono']) : '—' ?></dd></div>
    </dl>

    <?php if ($p['estado']): ?>
        <!-- E3: el paciente no se elimina; se desactiva y conserva su historial (RN11) -->
        <form method="post" action="<?= App::url('/pacientes') ?>" class="modal-acciones">
            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
            <input type="hidden" name="accion" value="desactivar">
            <input type="hidden" name="id_paciente" value="<?= (int) $p['id_paciente'] ?>">
            <button type="submit" class="btn-borde">Desactivar paciente</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($verHistorial): ?>
    <section class="hoja hoja-separada">
        <header class="hoja-cabecera">
            <h1>Historial de citas</h1>
        </header>
        <table class="cuentas" id="tablaHistorial">
            <thead>
                <tr><th>Código</th><th>Fecha</th><th>Hora</th><th>Médico</th><th>Especialidad</th><th>Motivo</th><th>Estado</th></tr>
            </thead>
            <tbody>
                <?php if ($p['historial'] === []): ?>
                    <tr><td colspan="7" class="vacio">El paciente no tiene citas registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($p['historial'] as $c): ?>
                    <tr>
                        <td><?= e($c['codigo']) ?></td>
                        <td><?= e($fecha($c['fecha'])) ?></td>
                        <td><?= e($c['hora_inicio']) ?></td>
                        <td><?= e($c['medico']) ?></td>
                        <td><?= e($c['especialidad']) ?></td>
                        <td><?= e($valor($c['estado'] === 'CANCELADA' && $c['motivo_cancelacion'] ? $c['motivo'] . ' — cancelada: ' . $c['motivo_cancelacion'] : $c['motivo'])) ?></td>
                        <td><span class="estado-pill<?= in_array($c['estado'], ['CANCELADA', 'INASISTENCIA'], true) ? ' bloqueada' : '' ?>"><?= e($c['estado']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
