<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер');
?>
<section class="welcome-card reveal">
    <div class="welcome-card__content">
        <span class="eyebrow-pill eyebrow-pill--dark"><span></span> Система работает штатно</span>
        <h2><?= e($greeting) ?>, <?= e($currentUser->firstName) ?>!</h2>
        <p><?= $currentUser->isStudent() ? 'Здесь вы можете связаться с сотрудниками автошколы и своим инструктором.' : 'Лиды, договоры, задачи и важные сроки собраны в одном рабочем пространстве.' ?></p>
        <a class="button button--light" href="<?= e(url($currentUser->canUseTasks() ? '/tasks' : '/chat')) ?>"><?= $currentUser->canUseTasks() ? 'Открыть задачи' : 'Открыть сообщения' ?> <?= icon('chevron-right', 17) ?></a>
    </div>
    <div class="welcome-card__visual" aria-hidden="true"><div class="road-sign"><span>Ц</span></div><div class="road-line road-line--one"></div><div class="road-line road-line--two"></div><div class="road-line road-line--three"></div></div>
</section>

<?php if ($currentUser->canUseCrm()): ?>
    <section class="metric-grid metric-grid--four stagger-group">
        <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--blue"><?= icon('user') ?></span><span class="trend">Активные</span></div><strong><?= $stats['students'] ?></strong><p><a href="<?= e(url('/students')) ?>">Курсанты</a></p></article>
        <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--green"><?= icon('users') ?></span><span class="trend">В воронке</span></div><strong><?= array_sum($leadStats) ?></strong><p><a href="<?= e(url('/crm/leads')) ?>">Клиенты и лиды</a></p></article>
        <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--orange"><?= icon('file') ?></span><span class="trend">Действуют</span></div><strong><?= $activeContracts ?></strong><p><a href="<?= e(url('/crm/contracts')) ?>">Договоры</a></p></article>
        <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--red"><?= icon('bell') ?></span><span class="trend">30 дней</span></div><strong><?= $warningCount ?></strong><p><a href="<?= e(url('/warnings')) ?>">Ближайшие сроки</a></p></article>
    </section>

    <section class="dashboard-grid dashboard-grid--balanced">
        <article class="panel reveal">
            <div class="panel__header"><div><p class="page-eyebrow">Последние действия</p><h3>Лента событий</h3></div><a class="text-link" href="<?= e(url('/events')) ?>">Все события <?= icon('chevron-right', 15) ?></a></div>
            <div class="timeline-list timeline-list--dashboard">
                <?php if (!$recentEvents): ?><div class="empty-inline">События появятся после первых действий.</div><?php endif; ?>
                <?php foreach ($recentEvents as $event): ?><div class="timeline-item"><span class="timeline-item__marker"><?= icon('activity', 16) ?></span><div class="timeline-item__body"><div class="timeline-item__top"><strong class="timeline-item__title"><span><?= e($event['action_name']) ?></span><?php if ($event['entity_name']): ?>: <?php if ($event['entity_url']): ?><a href="<?= e(url($event['entity_url'])) ?>"><?= e($event['entity_name']) ?></a><?php else: ?><span><?= e($event['entity_name']) ?></span><?php endif; ?><?php endif; ?></strong><time><?= e(format_date($event['created_at'], 'd.m H:i')) ?></time></div><p class="timeline-item__actor"><?php if ($event['actor_url']): ?><a href="<?= e(url($event['actor_url'])) ?>"><b><?= e($event['actor_name']) ?></b></a><?php else: ?><b><?= e($event['actor_name']) ?></b><?php endif; ?><?php if ($event['actor_role']): ?> <span>· <?= e($event['actor_role']) ?></span><?php endif; ?></p><?php if ($event['changes']): ?><?php $change = $event['changes'][0]; ?><dl class="event-changes event-changes--compact"><div class="event-change event-change--compact"><dt><?= e($change['field']) ?></dt><dd><span class="event-change__old"><?= e($change['from']) ?></span><span class="event-change__arrow">→</span><span class="event-change__new"><?= e($change['to']) ?></span><?php if (count($event['changes']) > 1): ?><em>+<?= count($event['changes']) - 1 ?></em><?php endif; ?></dd></div></dl><?php endif; ?></div></div><?php endforeach; ?>
            </div>
        </article>
        <article class="panel reveal">
            <div class="panel__header"><div><p class="page-eyebrow">Требуют внимания</p><h3>Ближайшие сроки</h3></div><a class="text-link" href="<?= e(url('/warnings')) ?>">Все сроки <?= icon('chevron-right', 15) ?></a></div>
            <div class="deadline-list deadline-list--compact"><?php if (!$warnings): ?><div class="empty-inline">На ближайшие 30 дней сроков нет.</div><?php endif; ?><?php foreach ($warnings as $warning): ?><a class="deadline-card deadline-card--<?= e($warning['urgency']) ?>" href="<?= e(url($warning['url'])) ?>"><span class="deadline-card__icon"><?= icon('clock', 18) ?></span><span><small><?= e($warning['source_title']) ?></small><strong><?= e($warning['title']) ?></strong><time><?= e(format_date($warning['due_at'], 'd.m.Y H:i')) ?></time></span></a><?php endforeach; ?></div>
        </article>
    </section>
<?php elseif ($currentUser->canUseTasks()): ?>
    <section class="metric-grid metric-grid--two stagger-group">
        <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--blue"><?= icon('check-square') ?></span><span class="trend">В работе</span></div><strong><?= $openTasks ?></strong><p><a href="<?= e(url('/tasks')) ?>">Доступные задачи</a></p></article>
        <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--orange"><?= icon('message') ?></span><span class="trend">Новые</span></div><strong><?= $unreadMessages ?></strong><p><a href="<?= e(url('/chat')) ?>">Сообщения</a></p></article>
    </section>
    <section class="role-home-grid"><article class="panel role-card reveal"><span class="metric-icon metric-icon--blue"><?= icon('check-square', 24) ?></span><p class="page-eyebrow">Работа</p><h3>Задачи</h3><p>Просматривайте общие и назначенные вам задачи, контролируйте сроки и отмечайте выполнение.</p><a class="button button--secondary" href="<?= e(url('/tasks')) ?>">Перейти к задачам</a></article><article class="panel role-card reveal"><span class="metric-icon metric-icon--orange"><?= icon('message', 24) ?></span><p class="page-eyebrow">Коммуникация</p><h3>Сообщения</h3><p><?= $unreadMessages ? 'У вас ' . $unreadMessages . ' непрочитанных сообщений.' : 'Все сообщения прочитаны.' ?></p><a class="button button--secondary" href="<?= e(url('/chat')) ?>">Перейти в чат</a></article></section>
<?php else: ?>
    <section class="role-home-grid"><article class="panel role-card reveal"><span class="metric-icon metric-icon--orange"><?= icon('message', 24) ?></span><p class="page-eyebrow">Коммуникация</p><h3>Ваши сообщения</h3><p><?= $unreadMessages ? 'У вас ' . $unreadMessages . ' непрочитанных сообщений.' : 'Все сообщения прочитаны.' ?></p><a class="button button--secondary" href="<?= e(url('/chat')) ?>">Перейти в чат</a></article><article class="panel role-card reveal"><span class="metric-icon metric-icon--blue"><?= icon('shield', 24) ?></span><p class="page-eyebrow">Ваш доступ</p><h3><?= e($currentUser->roleTitle()) ?></h3><p>Учётная запись активна. Данные доступа управляются руководством и администраторами автошколы.</p><a class="text-link" href="<?= e(url('/change-password')) ?>">Изменить пароль <?= icon('chevron-right', 15) ?></a></article></section>
<?php endif; ?>
