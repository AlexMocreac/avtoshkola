<?php

use App\Core\Csrf;
use App\Core\Flash;

$flashes = Flash::pull();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="theme-color" content="#4768db">
    <title><?= e($pageTitle ?? 'Автошкола') ?> · <?= e(env('APP_NAME', 'Автошкола ЦОВ')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body" data-base-url="<?= e(base_path()) ?>">
    <?= $content ?>

    <div class="toast-stack" id="toastStack" aria-live="polite">
        <?php foreach ($flashes as $flash): ?>
            <div class="toast toast--<?= e($flash['type']) ?>" data-toast><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
