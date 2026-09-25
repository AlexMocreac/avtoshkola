<?php
$overdue = count(array_filter($warnings, static fn (array $item): bool => $item['urgency'] === 'overdue'));
$urgent = count(array_filter($warnings, static fn (array $item): bool => $item['urgency'] === 'urgent'));
?>
<section class="page-actions reveal">
    <div><h2>Единый контроль сроков</h2><p>Договоры, задачи, контакты с лидами и другие события на ближайшие 30 дней.</p></div>
    <button class="button button--primary" type="button" data-dialog-open="deadlineModal"><?= icon('plus', 18) ?> Добавить событие</button>
</section>

<section class="metric-grid metric-grid--three compact-metrics stagger-group">
    <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--orange"><?= icon('bell') ?></span><span class="trend">Требуют внимания</span></div><strong><?= count($warnings) ?></strong><p>Всего предупреждений</p></article>
    <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--red"><?= icon('clock') ?></span><span class="trend">Срок прошёл</span></div><strong><?= $overdue ?></strong><p>Просрочено</p></article>
    <article class="metric-card reveal"><div class="metric-card__top"><span class="metric-icon metric-icon--blue"><?= icon('activity') ?></span><span class="trend">До трёх дней</span></div><strong><?= $urgent ?></strong><p>Срочные</p></article>
</section>

<section class="panel reveal">
    <div class="panel__header"><div><p class="page-eyebrow">По сроку</p><h3>Все предупреждения</h3></div></div>
    <div class="deadline-list">
        <?php if (!$warnings): ?><div class="empty-state"><span><?= icon('check', 28) ?></span><h3>Сроки под контролем</h3><p>На ближайшие 30 дней предупреждений нет.</p></div><?php endif; ?>
        <?php foreach ($warnings as $warning): ?>
            <article class="deadline-row deadline-row--<?= e($warning['urgency']) ?>"<?= $warning['source'] === 'event' ? ' id="deadline-' . (int) $warning['id'] . '"' : '' ?>>
                <span class="deadline-row__icon"><?= icon($warning['source'] === 'contract' ? 'file' : ($warning['source'] === 'task' ? 'check-square' : 'clock'), 20) ?></span>
                <div class="deadline-row__copy">
                    <div><span class="tag tag--<?= e($warning['urgency']) ?>"><?= e($warning['source_title']) ?></span><time><?= e(format_date($warning['due_at'], 'd.m.Y H:i')) ?></time></div>
                    <strong><?= e($warning['title']) ?></strong>
                    <?php if ($warning['description']): ?><p><?= e($warning['description']) ?></p><?php endif; ?>
                </div>
                <div class="deadline-row__actions">
                    <?php if ($warning['source'] === 'event'): ?>
                        <form method="post" action="<?= e(url('/warnings/' . $warning['id'] . '/complete')) ?>"><?= csrf_field() ?><button class="button button--secondary button--small" type="submit"><?= icon('check', 16) ?> Закрыть</button></form>
                    <?php else: ?>
                        <a class="button button--secondary button--small" href="<?= e(url($warning['url'])) ?>">Открыть</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<dialog class="modal" id="deadlineModal">
    <div class="modal__surface">
        <div class="modal__header"><div><p class="page-eyebrow">Новый срок</p><h2>Добавить событие</h2><p>Оно появится в предупреждениях за 30 дней до срока.</p></div><button class="icon-button modal__close" type="button" data-dialog-close><?= icon('x', 20) ?></button></div>
        <form method="post" action="<?= e(url('/warnings')) ?>" data-submit-loading>
            <?= csrf_field() ?>
            <div class="modal__body form-grid">
                <label class="field field--wide"><span>Название *</span><input name="title" maxlength="190" required placeholder="Например, продлить ОСАГО"></label>
                <label class="field"><span>Категория</span><input name="category" maxlength="80" placeholder="ОСАГО, аренда, документ"></label>
                <label class="field"><span>Срок *</span><?= date_picker('due_at', '', true, true, 'Срок события') ?></label>
                <label class="field field--wide"><span>Затронутый пользователь</span><select name="impacted_user_id"><option value="">Не указан</option><?php foreach ($staff as $employee): ?><option value="<?= $employee->id ?>"><?= e($employee->fullName()) ?></option><?php endforeach; ?></select></label>
                <label class="field field--wide"><span>Описание</span><textarea name="description" rows="4" maxlength="5000" placeholder="Что нужно сделать к указанной дате"></textarea></label>
            </div>
            <div class="modal__footer"><button class="button button--secondary" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Добавить событие</button></div>
        </form>
    </div>
</dialog>
