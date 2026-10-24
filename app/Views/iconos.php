<?php

function vs_icono(string $nombre): string
{
    $trazo = match ($nombre) {
        'editar' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'bloquear' => '<path d="M12 3v6"/><path d="M7.1 7.1a7 7 0 1 0 9.8 0"/>',
        'activar' => '<path d="M20 6 9 17l-5-5"/>',
        'historial' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
        'quitar' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        default => '',
    };

    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $trazo . '</svg>';
}
