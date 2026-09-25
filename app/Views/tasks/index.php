<section class="page-actions reveal">
    <div><h2>Задачи сотрудников</h2><p>Назначайте нескольких исполнителей, контролируйте сроки и создавайте ежемесячные серии.</p></div>
    <button class="button button--primary" type="button" data-dialog-open="taskModal"><?= icon('plus', 18) ?> Поставить задачу</button>
</section>

<nav class="segmented reveal" aria-label="Фильтр задач">
    <a class="<?= $filters['status'] === 'open' && $filters['mine'] !== '1' ? 'is-active' : '' ?>" href="<?= e(url('/tasks?status=open')) ?>">В работе</a>
    <a class="<?= $filters['status'] === 'open' && $filters['mine'] === '1' ? 'is-active' : '' ?>" href="<?= e(url('/tasks?status=open&mine=1')) ?>">Мои задачи</a>
    <a class="<?= $filters['status'] === 'completed' ? 'is-active' : '' ?>" href="<?= e(url('/tasks?status=completed')) ?>">Выполненные</a>
</nav>

<section class="task-board stagger-group">
    <?php if (!$tasks): ?><div class="panel empty-state reveal"><span><?= icon('check-square', 28) ?></span><h3>Задач в этом списке нет</h3><p>Создайте новую задачу или выберите другой фильтр.</p></div><?php endif; ?>
    <?php foreach ($tasks as $task): ?>
        <?php
        $isOverdue = $task['status'] === 'open' && $task['due_at'] && strtotime($task['due_at']) < time();
        $isSoon = $task['status'] === 'open' && $task['due_at'] && !$isOverdue && strtotime($task['due_at']) <= strtotime('+3 days');
        ?>
        <article class="task-card reveal <?= $isOverdue ? 'task-card--overdue' : ($isSoon ? 'task-card--soon' : '') ?>" id="task-<?= (int) $task['id'] ?>">
            <div class="task-card__top">
                <div class="task-card__badges">
                    <span class="status-pill status-pill--<?= e($task['status']) ?>"><i></i><?= $task['status'] === 'completed' ? 'Выполнена' : 'В работе' ?></span>
                    <?php if ($task['confidential']): ?><span class="tag tag--private"><?= icon('lock', 13) ?> Конфиденциально</span><?php endif; ?>
                    <?php if ($task['recurrence_group']): ?><span class="tag"><?= icon('repeat', 13) ?> Каждый месяц</span><?php endif; ?>
                </div>
                <span class="task-card__number">#<?= $task['id'] ?></span>
            </div>
            <p class="task-card__description"><?= nl2br(e($task['description'])) ?></p>
            <div class="task-card__details">
                <div><span><?= icon('users', 16) ?></span><p><small>Исполнители</small><strong><?= e($task['assignee_names']) ?></strong></p></div>
                <div><span><?= icon('calendar', 16) ?></span><p><small>Дата постановки</small><strong><?= e(format_date($task['task_date'], 'd.m.Y')) ?></strong></p></div>
                <div><span><?= icon('clock', 16) ?></span><p><small>Крайний срок</small><strong><?= $task['due_at'] ? e(format_date($task['due_at'])) : 'Не установлен' ?></strong></p></div>
            </div>
            <div class="task-card__footer">
                <small>Поставил: <?= e($task['creator_name']) ?> · <?= e(format_date($task['created_at'])) ?></small>
                <?php if ($task['status'] === 'open' && (int) $task['is_assignee'] === 1): ?>
                    <form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/complete')) ?>" data-confirm-message="Отметить задачу выполненной?"><?= csrf_field() ?><button class="button button--primary button--small" type="submit"><?= icon('check', 16) ?> Выполнено</button></form>
                <?php elseif ($task['status'] === 'completed'): ?>
                    <span class="completion-mark"><?= icon('check', 15) ?> <?= e(format_date($task['completed_at'])) ?></span>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<dialog class="modal" id="taskModal">
    <div class="modal__surface modal__surface--wide">
        <div class="modal__header"><div><p class="page-eyebrow">Новая задача</p><h2>Поставить задачу</h2><p>Конфиденциальную задачу увидят только выбранные исполнители.</p></div><button class="icon-button modal__close" type="button" data-dialog-close><?= icon('x', 20) ?></button></div>
        <form method="post" action="<?= e(url('/tasks')) ?>" id="taskForm" data-submit-loading>
            <?= csrf_field() ?>
            <div class="modal__body form-grid">
                <label class="field field--wide"><span>Описание задачи *</span><textarea name="description" rows="5" maxlength="5000" required placeholder="Что нужно сделать и какой результат ожидается"></textarea></label>
                <label class="field"><span>Дата постановки *</span><?= date_picker('task_date', date('Y-m-d'), false, true, 'Дата постановки') ?></label>
                <label class="field"><span>Крайний срок</span><?= date_picker('due_at', '', true, false, 'Крайний срок') ?></label>
                <fieldset class="field field--wide assignee-picker" data-assignee-picker data-endpoint="<?= e(url('/api/staff/search')) ?>">
                    <legend>Исполнители *</legend>
                    <div class="assignee-search">
                        <?= icon('search', 17) ?>
                        <input type="search" autocomplete="off" placeholder="Начните вводить имя или фамилию" aria-label="Поиск исполнителей" aria-autocomplete="list" data-assignee-search>
                        <span class="assignee-search__loader" aria-hidden="true" data-assignee-loader></span>
                    </div>
                    <div class="assignee-suggestions is-hidden" role="listbox" data-assignee-suggestions></div>
                    <div class="assignee-selected" aria-live="polite" data-assignee-selected></div>
                    <p class="field__hint">Введите минимум 2 символа. Результаты загружаются по мере ввода.</p>
                </fieldset>
                <label class="check-line field--wide"><input type="checkbox" name="confidential" value="1"><span><?= icon('lock', 17) ?></span><span><strong>Конфиденциальная задача</strong><small>Скрыть её от всех, кроме исполнителей</small></span></label>
                <label class="check-line field--wide"><input type="checkbox" name="recurrence" value="monthly" id="taskRecurrence"><span><?= icon('repeat', 17) ?></span><span><strong>Повторять каждый месяц</strong><small>Система сразу создаст задачи на выбранный период</small></span></label>
                <div class="recurrence-fields field--wide is-hidden" id="recurrenceFields">
                    <label class="field"><span>День месяца</span><input type="number" name="recurrence_day" min="1" max="31" value="<?= e(date('j')) ?>"></label>
                    <label class="field"><span>Повторять до</span><?= date_picker('recurrence_until', date('Y-m-d', strtotime('+1 year')), false, false, 'Повторять до') ?></label>
                </div>
            </div>
            <div class="modal__footer"><button class="button button--secondary" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Создать задачу</button></div>
        </form>
    </div>
</dialog>
