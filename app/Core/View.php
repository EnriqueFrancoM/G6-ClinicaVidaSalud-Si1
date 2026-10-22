<?php

namespace App\Core;

// Renderiza vistas PHP dentro de un layout.
class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        extract($data);
        ob_start();
        require BASE_PATH . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }
        require BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
    }
}

// Escapa texto para imprimirlo en HTML.
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
