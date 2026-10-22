<?php use function App\Core\e; ?>
<h2>Bienvenido, <?= e($usuario['username']) ?></h2>
<p>Rol: <?= e($usuario['rol']) ?>. Selecciona una opción del menú.</p>
