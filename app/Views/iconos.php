<?php

function vs_icono(string $nombre): string
{
    $trazo = match ($nombre) {
        'editar' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'bloquear' => '<path d="M12 3v6"/><path d="M7.1 7.1a7 7 0 1 0 9.8 0"/>',
        'activar' => '<path d="M20 6 9 17l-5-5"/>',
        'historial' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
        'quitar' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'agregar' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'calendario' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'qr' => '<rect width="6" height="6" x="3" y="3" rx="1"/><rect width="6" height="6" x="15" y="3" rx="1"/><rect width="6" height="6" x="3" y="15" rx="1"/><path d="M15 15h2v2h-2zM19 19h2v2M15 21h2M21 15v2"/>',
        'descargar' => '<path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/>',
        'correo' => '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="m3 7 9 7 9-7"/>',
        default => '',
    };

    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $trazo . '</svg>';
}
