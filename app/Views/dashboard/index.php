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

<?php if ($currentUser->canManageUsers()): ?>
    <section class="metric-grid metric-grid--two stagger-group">
        <article class="metric-card reveal">
            <div class="metric-card__top"><span class="metric-icon metric-icon--blue"><?= icon('user') ?></span><span class="trend">Активные</span></div>
            <strong><?= $stats['students'] ?></strong><p><a href="<?= e(url('/students')) ?>">Курсантов</a></p>
        </article>
        <article class="metric-card reveal">
            <div class="metric-card__top"><span class="metric-icon metric-icon--green"><?= icon('shield') ?></span><span class="trend">Активные</span></div>
            <strong><?= $stats['staff'] ?></strong><p><a href="<?= e(url('/staff')) ?>">Сотрудников</a></p>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel reveal">
            <div class="panel__header">
                <div><p class="page-eyebrow">Последние изменения</p><h3>Новые курсанты</h3></div>
                <a class="text-link" href="<?= e(url('/students')) ?>">Курсанты <?= icon('chevron-right', 15) ?></a>
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
            <a class="quick-action" href="<?= e(url('/students')) ?>">
                <span class="quick-action__icon"><?= icon('plus') ?></span>
                <span><strong>Добавить курсанта</strong><small>Указать срок обучения</small></span>
                <?= icon('chevron-right', 17) ?>
            </a>
            <?php if (!$currentUser->isAdmin()): ?>
            <a class="quick-action" href="<?= e(url('/staff')) ?>">
                <span class="quick-action__icon quick-action__icon--dark"><?= icon('shield') ?></span>
                <span><strong>Добавить сотрудника</strong><small>Назначить роль</small></span>
                <?= icon('chevron-right', 17) ?>
            </a>
            <?php endif; ?>
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
            <p>Учётная запись активна. Данные доступа управляются руководством и администраторами автошколы.</p>
            <a class="text-link" href="<?= e(url('/change-password')) ?>">Изменить пароль <?= icon('chevron-right', 15) ?></a>
        </article>
    </section>
<?php endif; ?>
