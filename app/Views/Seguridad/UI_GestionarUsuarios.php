<?php

use App\Core\Session;
use function App\Core\e;

$usuarios = $usuarios ?? [];
?>
<section class="hoja">
    <h1>Usuarios</h1>
    <p class="lede">Al tercer intento fallido se espera 15 segundos, al cuarto 30, y al quinto la cuenta se bloquea. Solo un administrador puede desbloquearla.</p>
    <table class="cuentas">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $fila): ?>
                <tr>
                    <td><?= e($fila['username']) ?></td>
                    <td><?= e($fila['email']) ?></td>
                    <td><?= e($fila['rol']) ?></td>
                    <td><span class="estado-pill<?= $fila['estado'] ? '' : ' bloqueada' ?>"><?= $fila['estado'] ? 'Activa' : 'Bloqueada' ?></span></td>
                    <td>
                        <?php if (!$fila['estado']): ?>
                            <form method="post" action="<?= \App\Core\App::url('/usuarios/desbloquear') ?>">
                                <input type="hidden" name="_csrf" value="<?= Session::csrfToken() ?>">
                                <input type="hidden" name="id_usuario" value="<?= e((string) $fila['id_usuario']) ?>">
                                <button type="submit" name="btnDesbloquear">Desbloquear</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
