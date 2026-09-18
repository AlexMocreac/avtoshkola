<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (preg_match('#(?:^|/)\.#', $requestPath) || preg_match('#^/(?:app|bin|database|routes|storage|tests)(?:/|$)#', $requestPath)) {
        http_response_code(404);
        exit;
    }
    $requestFile = __DIR__ . $requestPath;
    if (is_file($requestFile)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;

$router = new Router();
require __DIR__ . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], request_path());
