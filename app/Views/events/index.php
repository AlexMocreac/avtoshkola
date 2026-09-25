<div class="control-grid">
    <section class="panel reveal">
        <div class="panel__header panel__header--wrap">
            <div>
                <p class="page-eyebrow">Аудит системы</p>
                <h2>Все действия пользователей</h2>
                <p class="section-note">Для каждого действия сохранены инициатор, объект и точные изменения полей.</p>
            </div>
        </div>

        <form class="filter-bar filter-bar--events" method="get" action="<?= e(url('/events')) ?>">
            <label class="search-field"><?= icon('search', 18) ?><input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Действие, пользователь"></label>
            <select name="action" aria-label="Тип действия">
                <option value="">Все действия</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action['action_key']) ?>" <?= $filters['action'] === $action['action_key'] ? 'selected' : '' ?>><?= e($action['action_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= date_picker('date_from', $filters['date_from'], false, false, 'Дата от') ?>
            <?= date_picker('date_to', $filters['date_to'], false, false, 'Дата до') ?>
            <button class="button button--secondary" type="submit">Применить</button>
        </form>

        <div class="timeline-list">
            <?php if (!$events): ?>
                <div class="empty-state"><span><?= icon('activity', 28) ?></span><h3>Событий не найдено</h3><p>Измените фильтры или выполните действие в системе.</p></div>
            <?php endif; ?>
            <?php foreach ($events as $event): ?>
                <article class="timeline-item">
                    <span class="timeline-item__marker"><?= icon('activity', 17) ?></span>
                    <div class="timeline-item__body">
                        <div class="timeline-item__top">
                            <strong class="timeline-item__title">
                                <span><?= e($event['action_name']) ?></span>
                                <?php if ($event['entity_name']): ?>:
                                    <?php if ($event['entity_url']): ?><a href="<?= e(url($event['entity_url'])) ?>"><?= e($event['entity_name']) ?></a><?php else: ?><span><?= e($event['entity_name']) ?></span><?php endif; ?>
                                <?php endif; ?>
                            </strong>
                            <time datetime="<?= e($event['created_at']) ?>"><?= e(format_date($event['created_at'])) ?></time>
                        </div>
                        <p class="timeline-item__actor">
                            <?php if ($event['actor_url']): ?><a href="<?= e(url($event['actor_url'])) ?>"><b><?= e($event['actor_name']) ?></b></a><?php else: ?><b><?= e($event['actor_name']) ?></b><?php endif; ?>
                            <?php if ($event['actor_role']): ?> <span>· <?= e($event['actor_role']) ?></span><?php endif; ?>
                        </p>
                        <?php if ($event['changes']): ?><dl class="event-changes">
                            <?php foreach ($event['changes'] as $change): ?><div class="event-change">
                                <dt><?= e($change['field']) ?></dt>
                                <dd><span class="event-change__old"><?= e($change['from']) ?></span><span class="event-change__arrow" aria-label="изменено на">→</span><span class="event-change__new"><?= e($change['to']) ?></span></dd>
                            </div><?php endforeach; ?>
                        </dl><?php endif; ?>
                        <?php if ($event['legacy_change_details_missing']): ?><small class="event-change-note">Детализация недоступна для записи, созданной до обновления журнала.</small><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="panel deadline-panel reveal">
        <div class="panel__header">
            <div><p class="page-eyebrow">Ближайшие сроки</p><h3>Предупреждения</h3></div>
            <a class="text-link" href="<?= e(url('/warnings')) ?>">Все <?= icon('chevron-right', 15) ?></a>
        </div>
        <div class="deadline-list deadline-list--compact">
            <?php if (!$warnings): ?><div class="empty-inline">На ближайшие 30 дней сроков нет.</div><?php endif; ?>
            <?php foreach ($warnings as $warning): ?>
                <a class="deadline-card deadline-card--<?= e($warning['urgency']) ?>" href="<?= e(url($warning['url'])) ?>">
                    <span class="deadline-card__icon"><?= icon('clock', 18) ?></span>
                    <span><small><?= e($warning['source_title']) ?></small><strong><?= e($warning['title']) ?></strong><time><?= e(format_date($warning['due_at'], 'd.m.Y H:i')) ?></time></span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>
</div>
