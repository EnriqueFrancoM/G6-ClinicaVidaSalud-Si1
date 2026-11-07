<?php

use App\Core\App;
use function App\Core\e;
?>
<div class="registro-pagina respuesta-recordatorio">
    <a class="auth-brand" href="<?= App::url('/login') ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" stroke-dasharray="3.2 2.2"/>
            <path d="M12 7v10M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Vida Salud
    </a>
    <section class="hoja">
        <h1><?= $ok ? 'Respuesta registrada' : 'No se pudo registrar' ?></h1>
        <p class="alert <?= $ok ? 'success' : 'error' ?>"><?= e($mensaje) ?></p>
        <p class="nota">Puede revisar sus citas iniciando sesión en el portal del paciente.</p>
        <a class="btn-primary btn-chico" href="<?= App::url('/login') ?>">Ir al portal</a>
    </section>
</div>
