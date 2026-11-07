<?php

use App\Core\App;
use App\Core\Session;
use App\Models\Paciente;
use function App\Core\e;

$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
$f = $form;
?>
<?php if ($registro): ?>
<div class="registro-pagina">
    <a class="auth-brand" href="<?= App::url('/login') ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" stroke-dasharray="3.2 2.2"/>
            <path d="M12 7v10M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Vida Salud
    </a>
<?php endif; ?>

<section class="hoja" data-ui="UI_PerfilPaciente">
    <header class="hoja-cabecera">
        <div>
            <h1><?= $registro ? 'Registro de paciente' : 'Mi perfil' ?></h1>
            <p class="lede"><?= $registro
                ? 'Cree su cuenta para reservar y seguir sus citas en línea.'
                : 'Sus datos personales, información médica y contacto de emergencia.' ?></p>
        </div>
    </header>

    <?php if ($registro): ?>
        <?php if ($msg = Session::flash('error')): ?><p class="alert error" role="alert"><?= e($msg) ?></p><?php endif; ?>
    <?php elseif ($perfil === null): ?>
        <p class="alert error">Su cuenta no está habilitada para el portal del paciente.</p>
    <?php elseif ($perfil['ficha'] === null): ?>
        <p class="aviso-rango">Complete su perfil para poder reservar citas.</p>
    <?php endif; ?>

    <?php if ($registro || $perfil !== null): ?>
    <form method="post" action="<?= App::url($registro ? '/registro' : '/portal/perfil') ?>">
        <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">

        <?php if ($registro): ?>
            <h3 class="subtitulo">Cuenta</h3>
            <div class="grilla-4">
                <label class="field<?= $invalido('username') ?>"><span>Usuario *</span>
                    <input type="text" name="username" value="<?= e($f['username'] ?? '') ?>" maxlength="40" autocomplete="username" required></label>
                <label class="field<?= $invalido('email') ?>"><span>Correo electrónico *</span>
                    <input type="email" name="email" value="<?= e($f['email']) ?>" maxlength="100" autocomplete="email" required></label>
                <label class="field<?= $invalido('clave') ?>"><span>Contraseña *</span>
                    <input type="password" name="clave" autocomplete="new-password" minlength="8" required>
                    <small class="ayuda">Mínimo 8 caracteres, una mayúscula, un número y un símbolo.</small></label>
                <label class="field<?= $invalido('confirmar') ?>"><span>Confirmar contraseña *</span>
                    <input type="password" name="confirmar" autocomplete="new-password" minlength="8" required></label>
            </div>
        <?php endif; ?>

        <h3 class="subtitulo">Datos personales</h3>
        <div class="grilla-4">
            <label class="field<?= $invalido('ci') ?>"><span>CI *</span>
                <input type="text" name="ci" value="<?= e($f['ci']) ?>" maxlength="15" required></label>
            <label class="field<?= $invalido('nombres') ?>"><span>Nombres *</span>
                <input type="text" name="nombres" value="<?= e($f['nombres']) ?>" maxlength="60" required></label>
            <label class="field<?= $invalido('apellidos') ?>"><span>Apellidos *</span>
                <input type="text" name="apellidos" value="<?= e($f['apellidos']) ?>" maxlength="60" required></label>
            <label class="field<?= $invalido('fecha_nacimiento') ?>"><span>Fecha de nacimiento *</span>
                <input type="date" name="fecha_nacimiento" value="<?= e($f['fecha_nacimiento']) ?>" max="<?= date('Y-m-d') ?>" required></label>
            <label class="field<?= $invalido('sexo') ?>"><span>Sexo</span>
                <select name="sexo">
                    <option value="">—</option>
                    <?php foreach ($sexos as $valor => $texto): ?>
                        <option value="<?= $valor ?>"<?= $f['sexo'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="field<?= $invalido('telefono') ?>"><span>Teléfono *</span>
                <input type="tel" name="telefono" value="<?= e($f['telefono']) ?>" maxlength="15" required></label>
            <?php if (!$registro): ?>
                <label class="field<?= $invalido('email') ?>"><span>Correo electrónico</span>
                    <input type="email" name="email" value="<?= e($f['email']) ?>" maxlength="100"></label>
            <?php endif; ?>
        </div>
        <label class="field<?= $invalido('direccion') ?>"><span>Dirección</span>
            <input type="text" name="direccion" value="<?= e($f['direccion']) ?>" maxlength="150"></label>

        <h3 class="subtitulo">Información médica</h3>
        <div class="grilla-3">
            <label class="field<?= $invalido('tipo_sangre') ?>"><span>Tipo de sangre</span>
                <select name="tipo_sangre">
                    <option value="">—</option>
                    <?php foreach (Paciente::TIPOS_SANGRE as $tipo): ?>
                        <option value="<?= $tipo ?>"<?= $f['tipo_sangre'] === $tipo ? ' selected' : '' ?>><?= $tipo ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="field"><span>Alergias</span>
                <input type="text" name="alergias" value="<?= e($f['alergias']) ?>"></label>
            <label class="field"><span>Enfermedades crónicas</span>
                <input type="text" name="enfermedades_cronicas" value="<?= e($f['enfermedades_cronicas']) ?>"></label>
        </div>

        <h3 class="subtitulo">Contacto de emergencia</h3>
        <div class="grilla-3">
            <label class="field<?= $invalido('contacto_nombre') ?>"><span>Nombre</span>
                <input type="text" name="contacto_nombre" value="<?= e($f['contacto_nombre']) ?>" maxlength="100"></label>
            <label class="field<?= $invalido('contacto_parentesco') ?>"><span>Parentesco</span>
                <input type="text" name="contacto_parentesco" value="<?= e($f['contacto_parentesco']) ?>" maxlength="30"></label>
            <label class="field<?= $invalido('contacto_telefono') ?>"><span>Teléfono</span>
                <input type="tel" name="contacto_telefono" value="<?= e($f['contacto_telefono']) ?>" maxlength="15"></label>
        </div>

        <div class="modal-acciones">
            <?php if ($registro): ?>
                <a class="btn-borde" href="<?= App::url('/login') ?>">Ya tengo cuenta</a>
                <button type="submit" class="btn-primary btn-chico" name="btnRegistrar">Crear cuenta</button>
            <?php else: ?>
                <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar perfil</button>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</section>
<?php if ($registro): ?>
</div>
<?php endif; ?>
