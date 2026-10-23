<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$paso = $paso ?? 'solicitar';
$correo = $correo ?? '';
$correoListo = $correoListo ?? false;
?>
<div class="auth-shell">
    <section class="auth-hero-col">
        <div class="fluid-gradient-bg" data-reflejo>
            <img class="hero-photo" src="<?= App::url('/assets/img/login-image.jpg') ?>" alt="">
            <div class="hero-wash" aria-hidden="true"></div>
            <div class="hero-scrim" aria-hidden="true"></div>
            <div class="hero-shimmer" aria-hidden="true"></div>
            <div class="hero-glint" aria-hidden="true"></div>
            <p class="eyebrow">Clínica Vida Salud</p>
            <div class="hero-copy">
                <h1>Todo el<br>cuidado,<br>en orden.</h1>
                <p>Pacientes, médicos y citas. Sin un horario que se cruce con otro.</p>
            </div>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" stroke-dasharray="3.2 2.2"/>
                <path d="M12 7v10M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            Vida Salud
        </div>

        <?php if ($paso === 'codigo'): ?>
            <form class="auth-form" data-ui="UI_RecuperarClave" data-paso="codigo" method="post" action="<?= App::url('/recuperar/codigo') ?>">
                <h2>Código</h2>
                <p class="lede">Lo enviamos a <?= e($correo) ?>. Es de un solo uso.</p>
                <div data-avisos>
                    <?php if ($msg = Session::flash('success')): ?><div class="alert success" role="status"><?= e($msg) ?></div><?php endif; ?>
                    <?php if ($msg = Session::flash('error')): ?><div class="alert error" role="alert"><?= e($msg) ?></div><?php endif; ?>
                </div>
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <label class="field">
                    <span>Código</span>
                    <input type="text" name="txtCodigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6 dígitos" required>
                </label>
                <button class="btn-primary" type="submit" name="btnVerificar">Continuar</button>
            </form>
        <?php elseif ($paso === 'clave'): ?>
            <form class="auth-form" data-ui="UI_RecuperarClave" data-paso="clave" method="post" action="<?= App::url('/recuperar/clave') ?>">
                <h2>Nueva clave</h2>
                <p class="lede">Mínimo 8 caracteres, con mayúscula, minúscula, número y un carácter especial.</p>
                <div data-avisos>
                    <?php if ($msg = Session::flash('error')): ?><div class="alert error" role="alert"><?= e($msg) ?></div><?php endif; ?>
                </div>
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <label class="field">
                    <span>Nueva contraseña</span>
                    <span class="password-row">
                        <input type="password" name="txtContrasenaNueva" autocomplete="new-password" placeholder="Nueva contraseña" required>
                        <button class="ghost-btn" type="button" data-toggle-password aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg class="eye-on" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6A3 3 0 0012 15a3 3 0 002.4-4.4"/></svg>
                        </button>
                    </span>
                </label>
                <label class="field">
                    <span>Confirmar contraseña</span>
                    <input type="password" name="txtContrasenaConfirmacion" autocomplete="new-password" placeholder="Repita la contraseña" required>
                </label>
                <button class="btn-primary" type="submit" name="btnGuardarClave">Guardar contraseña</button>
            </form>
        <?php else: ?>
            <form class="auth-form" data-ui="UI_RecuperarClave" data-paso="solicitar" method="post" action="<?= App::url('/recuperar') ?>">
                <h2>Recuperar</h2>
                <p class="lede">Le enviamos un código de un solo uso al correo de la cuenta.</p>
                <div data-avisos>
                    <?php if (!$correoListo): ?><div class="alert error" role="status">Brevo todavía no tiene clave ni remitente. Complételos en el archivo .env.</div><?php endif; ?>
                    <?php if ($msg = Session::flash('error')): ?><div class="alert error" role="alert"><?= e($msg) ?></div><?php endif; ?>
                </div>
                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                <label class="field">
                    <span>Usuario o correo</span>
                    <input type="text" name="txtUsuario" autocomplete="username" placeholder="Ingrese su usuario o correo" required autofocus>
                </label>
                <button class="btn-primary" type="submit" name="btnEnviarCodigo">Enviar código</button>
            </form>
        <?php endif; ?>

        <p class="auth-note"><a href="<?= App::url('/login') ?>">Volver a iniciar sesión</a></p>
    </section>
</div>
