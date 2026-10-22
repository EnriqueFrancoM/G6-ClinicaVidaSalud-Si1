<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;
?>
<div class="login-card">
    <h1>Clínica Vida Salud</h1>
    <p>Sistema de gestión de citas médicas</p>

    <?php if ($msg = Session::flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($msg = Session::flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>

    <form method="post" action="<?= App::url('/login') ?>">
        <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
        <label>Usuario o correo
            <input type="text" name="login" required autofocus>
        </label>
        <label>Contraseña
            <input type="password" name="password" required>
        </label>
        <button type="submit">Iniciar sesión</button>
    </form>
</div>
