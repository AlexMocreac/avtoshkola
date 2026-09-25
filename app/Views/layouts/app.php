<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\Conversation;
use App\Models\Warning;

$currentUser = $currentUser ?? Auth::user();
$flashes = Flash::pull();
$layoutUnread = isset($unreadMessages) ? (int) $unreadMessages : Conversation::totalUnread($currentUser->id);
$layoutWarnings = isset($warningCount) ? (int) $warningCount : ($currentUser->canUseCrm() ? Warning::count($currentUser) : 0);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="theme-color" content="#4768db">
    <title><?= e($pageTitle ?? 'CRM') ?> · <?= e(env('APP_NAME', 'Автошкола ЦОВ')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body" data-base-url="<?= e(base_path()) ?>" data-user-id="<?= $currentUser->id ?>">
    <div class="app-shell" id="appShell">
        <aside class="sidebar" id="sidebar" aria-label="Основная навигация">
            <div class="sidebar__header">
                <a class="brand" href="<?= e(url('/dashboard')) ?>" aria-label="Центр Обучения Вождению">
                    <span class="brand__mark"><span>Ц</span></span>
                    <span class="brand__copy">
                        <strong>Центр</strong>
                        <small>Обучения Вождению</small>
                    </span>
                </a>
                <button class="icon-button sidebar__collapse" id="sidebarCollapse" type="button" aria-label="Свернуть меню" title="Свернуть меню">
                    <?= icon('chevron-left', 18) ?>
                </button>
            </div>

            <nav class="sidebar__nav">
                <p class="nav-label">Рабочее пространство</p>
                <a class="nav-item <?= is_active_route('/dashboard') ? 'is-active' : '' ?>" href="<?= e(url('/dashboard')) ?>">
                    <span class="nav-item__icon"><?= icon('grid') ?></span>
                    <span class="nav-item__label">Главная</span>
                </a>
                <?php if ($currentUser->canUseCrm()): ?>
                    <a class="nav-item <?= is_active_route('/events') ? 'is-active' : '' ?>" href="<?= e(url('/events')) ?>">
                        <span class="nav-item__icon"><?= icon('activity') ?></span>
                        <span class="nav-item__label">Лента событий</span>
                    </a>
                    <a class="nav-item <?= is_active_route('/warnings') ? 'is-active' : '' ?>" href="<?= e(url('/warnings')) ?>">
                        <span class="nav-item__icon"><?= icon('bell') ?></span>
                        <span class="nav-item__label">Предупреждения</span>
                        <span class="nav-item__badge <?= $layoutWarnings === 0 ? 'is-hidden' : '' ?>"><?= $layoutWarnings ?></span>
                    </a>
                    <p class="nav-label nav-label--section">CRM</p>
                    <a class="nav-item <?= is_active_route('/crm/leads') ? 'is-active' : '' ?>" href="<?= e(url('/crm/leads')) ?>">
                        <span class="nav-item__icon"><?= icon('users') ?></span>
                        <span class="nav-item__label">Клиенты и лиды</span>
                    </a>
                    <a class="nav-item <?= is_active_route('/crm/contracts') ? 'is-active' : '' ?>" href="<?= e(url('/crm/contracts')) ?>">
                        <span class="nav-item__icon"><?= icon('file') ?></span>
                        <span class="nav-item__label">Договоры</span>
                    </a>
                <?php endif; ?>
                <?php if ($currentUser->canUseTasks()): ?>
                    <a class="nav-item <?= is_active_route('/tasks') ? 'is-active' : '' ?>" href="<?= e(url('/tasks')) ?>">
                        <span class="nav-item__icon"><?= icon('check-square') ?></span>
                        <span class="nav-item__label">Задачи</span>
                    </a>
                <?php endif; ?>
                <?php if ($currentUser->canManageUsers()): ?>
                    <p class="nav-label nav-label--section">Пользователи</p>
                    <a class="nav-item <?= is_active_route('/students') ? 'is-active' : '' ?>" href="<?= e(url('/students')) ?>">
                        <span class="nav-item__icon"><?= icon('users') ?></span>
                        <span class="nav-item__label">Курсанты</span>
                    </a>
                    <a class="nav-item <?= is_active_route('/staff') ? 'is-active' : '' ?>" href="<?= e(url('/staff')) ?>">
                        <span class="nav-item__icon"><?= icon('shield') ?></span>
                        <span class="nav-item__label">Сотрудники</span>
                    </a>
                <?php endif; ?>
                <a class="nav-item <?= is_active_route('/chat') ? 'is-active' : '' ?>" href="<?= e(url('/chat')) ?>">
                    <span class="nav-item__icon"><?= icon('message') ?></span>
                    <span class="nav-item__label">Сообщения</span>
                    <span class="nav-item__badge <?= $layoutUnread === 0 ? 'is-hidden' : '' ?>" id="navUnreadBadge"><?= $layoutUnread ?></span>
                </a>
            </nav>

            <div class="sidebar__footer">
                <a class="user-mini" href="<?= e(url('/change-password')) ?>">
                    <span class="avatar avatar--warm"><?= e(initials($currentUser->firstName, $currentUser->lastName)) ?></span>
                    <span class="user-mini__copy">
                        <strong><?= e($currentUser->shortName()) ?></strong>
                        <small><?= e($currentUser->roleTitle()) ?></small>
                    </span>
                    <span class="user-mini__arrow"><?= icon('chevron-right', 16) ?></span>
                </a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="sidebar__logout">
                    <?= csrf_field() ?>
                    <button class="nav-item nav-item--button" type="submit">
                        <span class="nav-item__icon"><?= icon('logout') ?></span>
                        <span class="nav-item__label">Выйти</span>
                    </button>
                </form>
            </div>
        </aside>

        <button class="sidebar-overlay" id="sidebarOverlay" type="button" aria-label="Закрыть меню"></button>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar__title-group">
                    <button class="icon-button topbar__menu" id="mobileMenuButton" type="button" aria-label="Открыть меню">
                        <?= icon('menu') ?>
                    </button>
                    <div>
                        <?php if (!empty($pageEyebrow)): ?><p class="page-eyebrow"><?= e($pageEyebrow) ?></p><?php endif; ?>
                        <h1><?= e($pageTitle ?? 'CRM') ?></h1>
                    </div>
                </div>
                <div class="topbar__actions">
                    <?php if ($currentUser->canUseCrm()): ?>
                    <a class="icon-button topbar__notification" href="<?= e(url('/warnings')) ?>" aria-label="Предупреждения: <?= $layoutWarnings ?>" title="Предупреждения">
                        <?= icon('clock') ?>
                        <span class="notification-count <?= $layoutWarnings === 0 ? 'is-hidden' : '' ?>"><?= $layoutWarnings ?></span>
                    </a>
                    <?php endif; ?>
                    <a class="icon-button topbar__notification" href="<?= e(url('/chat')) ?>" aria-label="Сообщения" title="Сообщения">
                        <?= icon('bell') ?>
                        <span class="notification-dot <?= $layoutUnread === 0 ? 'is-hidden' : '' ?>" id="topUnreadDot"></span>
                    </a>
                    <div class="topbar__profile">
                        <span class="avatar avatar--dark"><?= e(initials($currentUser->firstName, $currentUser->lastName)) ?></span>
                        <span class="topbar__profile-copy">
                            <strong><?= e($currentUser->shortName()) ?></strong>
                            <small><?= e($currentUser->roleTitle()) ?></small>
                        </span>
                    </div>
                </div>
            </header>

            <div class="page-content">
                <?= $content ?>
            </div>
        </main>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite">
        <?php foreach ($flashes as $flash): ?>
            <div class="toast toast--<?= e($flash['type']) ?>" data-toast><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
