<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер');
?>
<section class="welcome-card reveal">
    <div class="welcome-card__content">
        <span class="eyebrow-pill eyebrow-pill--dark"><span></span> Система работает штатно</span>
        <h2><?= e($greeting) ?>, <?= e($currentUser->firstName) ?>!</h2>
        <p><?= $currentUser->isStudent() ? 'Здесь вы можете связаться с сотрудниками автошколы и своим инструктором.' : 'Всё необходимое для управления первым этапом CRM — в одном месте.' ?></p>
        <a class="button button--light" href="<?= e(url('/chat')) ?>">Открыть сообщения <?= icon('chevron-right', 17) ?></a>
    </div>
    <div class="welcome-card__visual" aria-hidden="true">
        <div class="road-sign"><span>Ц</span></div>
        <div class="road-line road-line--one"></div><div class="road-line road-line--two"></div><div class="road-line road-line--three"></div>
    </div>
</section>

<?php if ($currentUser->isAdmin()): ?>
    <section class="metric-grid stagger-group">
        <article class="metric-card reveal">
            <div class="metric-card__top"><span class="metric-icon metric-icon--orange"><?= icon('users') ?></span><span class="trend trend--up">Все роли</span></div>
            <strong><?= $stats['total'] ?></strong><p>Пользователей</p>
        </article>
        <article class="metric-card reveal">
            <div class="metric-card__top"><span class="metric-icon metric-icon--blue"><?= icon('user') ?></span><span class="trend">Активные</span></div>
            <strong><?= $stats['students'] ?></strong><p>Курсантов</p>
        </article>
        <article class="metric-card reveal">
            <div class="metric-card__top"><span class="metric-icon metric-icon--green"><?= icon('shield') ?></span><span class="trend">Команда</span></div>
            <strong><?= $stats['staff'] ?></strong><p>Сотрудников</p>
        </article>
        <article class="metric-card reveal">
            <div class="metric-card__top"><span class="metric-icon metric-icon--red"><?= icon('lock') ?></span><span class="trend">Контроль</span></div>
            <strong><?= $stats['blocked'] ?></strong><p>Заблокировано</p>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel reveal">
            <div class="panel__header">
                <div><p class="page-eyebrow">Последние изменения</p><h3>Новые пользователи</h3></div>
                <a class="text-link" href="<?= e(url('/users')) ?>">Все пользователи <?= icon('chevron-right', 15) ?></a>
            </div>
            <div class="people-list">
                <?php if (!$recentUsers): ?><div class="empty-inline">Пользователи пока не добавлены.</div><?php endif; ?>
                <?php foreach ($recentUsers as $user): ?>
                    <div class="person-row">
                        <span class="avatar avatar--soft"><?= e(initials($user->firstName, $user->lastName)) ?></span>
                        <div class="person-row__copy"><strong><?= e($user->fullName()) ?></strong><small><?= e($user->roleTitle()) ?></small></div>
                        <span class="status-pill status-pill--<?= e($user->status) ?>"><i></i><?= $user->status === 'active' ? 'Активен' : 'Заблокирован' ?></span>
                        <time><?= e(format_date($user->createdAt, 'd.m.Y')) ?></time>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="panel quick-panel reveal">
            <div class="panel__header"><div><p class="page-eyebrow">Быстрые действия</p><h3>Начните работу</h3></div></div>
            <a class="quick-action" href="<?= e(url('/users')) ?>" data-open-create-user>
                <span class="quick-action__icon"><?= icon('plus') ?></span>
                <span><strong>Добавить пользователя</strong><small>Курсант или сотрудник</small></span>
                <?= icon('chevron-right', 17) ?>
            </a>
            <a class="quick-action" href="<?= e(url('/chat')) ?>">
                <span class="quick-action__icon quick-action__icon--dark"><?= icon('message') ?></span>
                <span><strong>Открыть сообщения</strong><small><?= $unreadMessages ? $unreadMessages . ' непрочитанных' : 'Новых сообщений нет' ?></small></span>
                <?= icon('chevron-right', 17) ?>
            </a>
        </article>
    </section>
<?php else: ?>
    <section class="role-home-grid">
        <article class="panel role-card reveal">
            <span class="metric-icon metric-icon--orange"><?= icon('message', 24) ?></span>
            <p class="page-eyebrow">Коммуникация</p><h3>Ваши сообщения</h3>
            <p><?= $unreadMessages ? 'У вас ' . $unreadMessages . ' непрочитанных сообщений.' : 'Все сообщения прочитаны.' ?></p>
            <a class="button button--secondary" href="<?= e(url('/chat')) ?>">Перейти в чат</a>
        </article>
        <article class="panel role-card reveal">
            <span class="metric-icon metric-icon--blue"><?= icon('shield', 24) ?></span>
            <p class="page-eyebrow">Ваш доступ</p><h3><?= e($currentUser->roleTitle()) ?></h3>
            <p>Учётная запись активна. Данные доступа управляются администратором автошколы.</p>
            <a class="text-link" href="<?= e(url('/change-password')) ?>">Изменить пароль <?= icon('chevron-right', 15) ?></a>
        </article>
    </section>
<?php endif; ?>

