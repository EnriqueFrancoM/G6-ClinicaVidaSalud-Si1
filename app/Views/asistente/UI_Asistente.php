<?php

use App\Core\App;
use App\Core\Session;
use function App\Core\e;

$listo = $listo ?? false;
?>
<section class="chat" data-asistente data-url="<?= e(App::url('/asistente')) ?>" data-csrf="<?= e(Session::csrfToken()) ?>">
    <div class="chat-hilo">
        <div class="chat-columna">
            <div class="chat-vacio">
                <h1>¿En qué puedo ayudarle?</h1>
                <p>Consulte, cree, edite o dé de baja lo que su rol permite. Médicos, pacientes y horarios se desactivan. Un día festivo sí se quita.</p>
                <div class="chat-ideas">
                    <button type="button" data-idea>¿Cuántos médicos y especialidades hay?</button>
                    <button type="button" data-idea>Mostrá los horarios de pediatría</button>
                    <button type="button" data-idea>¿Qué podés hacer con mi rol?</button>
                </div>
            </div>
            <div class="chat-mensajes" role="log" aria-live="polite"></div>
        </div>
    </div>
    <div class="chat-pie">
        <?php if (!$listo): ?>
            <p class="chat-aviso">El asistente no está configurado. Agregue OPENROUTER_API_KEY en el servidor. La misma clave vale para todas las cuentas.</p>
        <?php endif; ?>
        <form class="chat-form">
            <label class="sr-only" for="asistente-mensaje">Mensaje</label>
            <textarea id="asistente-mensaje" name="mensaje" rows="1" maxlength="2000" placeholder="Escriba un mensaje"></textarea>
            <button type="submit" aria-label="Enviar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
            </button>
        </form>
    </div>
</section>
<script src="<?= App::url('/assets/js/asistente.js') ?>?v=<?= filemtime(BASE_PATH . '/public/assets/js/asistente.js') ?>"></script>
