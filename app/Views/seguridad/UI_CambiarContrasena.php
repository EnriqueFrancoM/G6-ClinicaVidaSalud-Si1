<?php

use App\Core\App;
use App\Core\Session;

$campoError = $campoError ?? null;
$invalido = fn (string $campo) => $campoError === $campo ? ' is-invalid' : '';
?>
<section class="hoja hoja-angosta" data-ui="UI_CambiarContrasena">
    <header class="hoja-cabecera">
        <h1>Actualizar mi contraseña</h1>
    </header>
    <?php if ($primerIngreso ?? false): ?>
        <p class="lede">Es su primer ingreso: debe cambiar la contraseña temporal para continuar.</p>
    <?php endif; ?>

    <form method="post" action="<?= App::url('/cambiar-contrasena') ?>">
        <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">

        <label class="field<?= $invalido('actual') ?>">
            <span>Contraseña actual *</span>
            <input type="password" name="txtActual" autocomplete="current-password" required>
        </label>
        <label class="field<?= $invalido('nueva') ?>">
            <span>Nueva contraseña *</span>
            <input type="password" name="txtNueva" autocomplete="new-password" minlength="8" required>
            <small class="ayuda">Mínimo 8 caracteres, al menos una mayúscula, un número y un símbolo.</small>
        </label>
        <label class="field<?= $invalido('confirmacion') ?>">
            <span>Confirmar nueva contraseña *</span>
            <input type="password" name="txtConfirmar" autocomplete="new-password" required>
        </label>

        <div class="modal-acciones">
            <?php if (!($primerIngreso ?? false)): ?>
                <a class="btn-borde" href="<?= App::url('/dashboard') ?>">Cancelar</a>
            <?php endif; ?>
            <button type="submit" class="btn-primary btn-chico" name="btnGuardar">Guardar cambios</button>
        </div>
    </form>
</section>
