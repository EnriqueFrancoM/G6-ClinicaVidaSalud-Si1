<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;
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

        <form class="auth-form" data-ui="UI_IniciarSesion" method="post" action="<?= App::url('/login') ?>">
            <h2>Iniciar sesión</h2>
            <p class="lede">Usuario o correo, y su contraseña.</p>

            <div data-avisos>
                <?php $espera = Session::flash('espera'); ?>
                <?php if ($msg = Session::flash('success')): ?><div class="alert success" role="status"><?= e($msg) ?></div><?php endif; ?>
                <?php if ($msg = Session::flash('error')): ?><div class="alert error" role="alert"<?= $espera !== null ? ' data-espera="' . e((string) $espera) . '"' : '' ?>><?= e($msg) ?></div><?php endif; ?>
            </div>

            <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">

            <label class="field">
                <span>Usuario o correo</span>
                <input type="text" name="txtUsuario" autocomplete="username" placeholder="Ingrese su usuario o correo" required autofocus>
            </label>

            <label class="field">
                <span>Contraseña</span>
                <span class="password-row">
                    <input type="password" name="txtContrasena" autocomplete="current-password" placeholder="Ingrese su contraseña" required>
                    <button class="ghost-btn" type="button" data-toggle-password aria-label="Mostrar contraseña" aria-pressed="false">
                        <svg class="eye-on" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M3 3l18 18"/>
                            <path d="M10.6 10.6A3 3 0 0012 15a3 3 0 002.4-4.4"/>
                            <path d="M9.9 5.2A10.8 10.8 0 0112 5c6.5 0 10 7 10 7a18.6 18.6 0 01-4.1 4.8"/>
                            <path d="M6.1 6.1C3.7 7.8 2 12 2 12s3.5 7 10 7a10.7 10.7 0 004.2-.8"/>
                        </svg>
                    </button>
                </span>
            </label>

            <button class="btn-primary" type="submit" name="btnIngresar">Iniciar sesión</button>
        </form>

        <p class="auth-note">¿Olvidó su contraseña? Contacte al administrador.</p>
    </section>
</div>
