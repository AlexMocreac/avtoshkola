<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $base = dirname(__DIR__) . '/Views/';
        $viewPath = $base . $view . '.php';
        $layoutPath = $base . $layout . '.php';
        if (!is_file($viewPath) || !is_file($layoutPath)) {
            throw new RuntimeException('Шаблон не найден.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();
        require $layoutPath;
    }
}

