<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token = null): bool
    {
        $token ??= $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function enforce(bool $json = false): void
    {
        if (self::verify()) {
            return;
        }

        if ($json) {
            Response::json(['ok' => false, 'message' => 'Сессия устарела. Обновите страницу и повторите действие.'], 419);
        }

        http_response_code(419);
        View::render('errors/419', ['pageTitle' => 'Сессия устарела'], 'layouts/auth');
        exit;
    }
}

