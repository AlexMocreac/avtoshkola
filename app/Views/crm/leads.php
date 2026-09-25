<section class="page-actions reveal">
    <div><h2>Воронка клиентов</h2><p>Простая канбан-воронка: от первого обращения до договора или отказа.</p></div>
    <button class="button button--primary" type="button" data-record-create="lead" data-dialog-open="leadModal"><?= icon('plus', 18) ?> Добавить лида</button>
</section>

<nav class="direction-tabs reveal" aria-label="Направления CRM">
    <?php foreach ($directions as $key => $title): ?>
        <a class="direction-tab <?= $filters['direction'] === $key ? 'is-active' : '' ?>" href="<?= e(url('/crm/leads?direction=' . $key)) ?>">
            <span><?= e($title) ?></span>
            <small><?= $directionTotals[$key] ?> лидов</small>
        </a>
    <?php endforeach; ?>
</nav>

<section class="panel reveal crm-search-panel">
    <form class="filter-bar filter-bar--lead-board" method="get" action="<?= e(url('/crm/leads')) ?>">
        <input type="hidden" name="direction" value="<?= e($filters['direction']) ?>">
        <label class="search-field"><?= icon('search', 18) ?><input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="ФИО, телефон, компания или email"></label>
        <button class="button button--secondary" type="submit">Найти</button>
        <?php if ($filters['search'] !== ''): ?><a class="button button--ghost" href="<?= e(url('/crm/leads?direction=' . $filters['direction'])) ?>">Сбросить</a><?php endif; ?>
    </form>
</section>

<section class="lead-board reveal" aria-label="Воронка <?= e($directions[$filters['direction']]) ?>"<?= $focusedLeadId ? ' data-auto-open-lead="' . (int) $focusedLeadId . '"' : '' ?>>
    <?php foreach ($statuses as $statusKey => $statusTitle): ?>
        <section class="lead-column lead-column--<?= e($statusKey) ?>">
            <header class="lead-column__header"><span><?= e($statusTitle) ?></span><b><?= $stats[$statusKey] ?></b></header>
            <div class="lead-column__body">
                <?php if (!$leadsByStatus[$statusKey]): ?><p class="lead-column__empty">Пока пусто</p><?php endif; ?>
                <?php foreach ($leadsByStatus[$statusKey] as $lead): ?>
                    <?php $leadJson = e(json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>
                    <article class="lead-kanban-card" id="lead-<?= (int) $lead['id'] ?>">
                        <div class="lead-kanban-card__top">
                            <button class="lead-person" type="button" data-lead-details="<?= $lead['id'] ?>">
                                <span class="avatar avatar--soft"><?= e(initials($lead['first_name'] ?: ($lead['company_name'] ?: 'Л'), $lead['last_name'] ?: '')) ?></span>
                                <span><strong><?= e(\App\Models\Lead::displayName($lead)) ?></strong><small><?= e($lead['company_name'] ?: ($lead['source'] ?: 'Источник не указан')) ?></small></span>
                            </button>
                            <span class="lead-kanban-card__number">#<?= $lead['id'] ?></span>
                        </div>
                        <div class="lead-kanban-card__contacts">
                            <?php if ($lead['phone']): ?><a href="tel:<?= e(preg_replace('/[^+0-9]/', '', $lead['phone'])) ?>"><?= icon('phone', 14) ?> <?= e($lead['phone']) ?></a><?php endif; ?>
                            <?php if ($lead['email']): ?><a href="mailto:<?= e($lead['email']) ?>"><?= icon('mail', 14) ?> <?= e($lead['email']) ?></a><?php endif; ?>
                        </div>
                        <div class="lead-kanban-card__meta">
                            <span><?= icon('user', 14) ?> <?= e($lead['assignee_name'] ?: 'Без ответственного') ?></span>
                            <?php if ($lead['next_contact_at']): ?><span><?= icon('clock', 14) ?> <?= e(format_date($lead['next_contact_at'])) ?></span><?php endif; ?>
                        </div>
                        <footer class="lead-kanban-card__actions">
                            <button class="icon-button" type="button" title="Карточка и история" data-lead-details="<?= $lead['id'] ?>"><?= icon('activity', 16) ?></button>
                            <?php if ($lead['phone']): ?><a class="icon-button" href="sms:<?= e(preg_replace('/[^+0-9]/', '', $lead['phone'])) ?>" title="Написать SMS"><?= icon('message', 16) ?></a><?php endif; ?>
                            <?php if (!in_array($lead['status'], ['contract', 'refused'], true)): ?><a class="button button--small button--contract" href="<?= e(url('/crm/contracts?create=1&lead_id=' . $lead['id'])) ?>"><?= icon('file', 14) ?> Договор</a><?php endif; ?>
                            <button class="icon-button" type="button" title="Редактировать" data-record-edit="lead" data-record="<?= $leadJson ?>" data-dialog-open="leadModal"><?= icon('edit', 16) ?></button>
                            <form method="post" action="<?= e(url('/crm/leads/' . $lead['id'] . '/archive')) ?>" data-confirm-message="Перенести лида в архив?"><?= csrf_field() ?><button class="icon-button icon-button--danger" type="submit" title="В архив"><?= icon('trash', 16) ?></button></form>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</section>

<dialog class="modal" id="leadModal">
    <div class="modal__surface modal__surface--wide">
        <div class="modal__header"><div><p class="page-eyebrow" id="leadModalEyebrow">Новый контакт</p><h2 id="leadModalTitle">Добавить лида</h2><p>Контактные данные, направление, источник и следующий шаг.</p></div><button class="icon-button modal__close" type="button" data-dialog-close><?= icon('x', 20) ?></button></div>
        <form method="post" action="<?= e(url('/crm/leads')) ?>" id="leadForm" data-create-action="<?= e(url('/crm/leads')) ?>" data-update-template="<?= e(url('/crm/leads/__id__/update')) ?>" data-submit-loading>
            <?= csrf_field() ?>
            <div class="modal__body form-grid">
                <label class="field"><span>Имя</span><input name="first_name" maxlength="80"></label>
                <label class="field"><span>Фамилия</span><input name="last_name" maxlength="80"></label>
                <label class="field"><span>Отчество</span><input name="middle_name" maxlength="80"></label>
                <label class="field"><span>Организация</span><input name="company_name" maxlength="190"></label>
                <label class="field"><span>Телефон</span><input name="phone" type="tel" maxlength="18" autocomplete="tel" placeholder="+7 (900) 000-00-00"></label>
                <label class="field"><span>Email</span><input name="email" type="email" maxlength="190"></label>
                <label class="field"><span>Источник</span><input name="source" maxlength="80" placeholder="Сайт, звонок, рекомендация"></label>
                <label class="field"><span>Направление *</span><select name="direction" required><?php foreach ($directions as $key => $title): ?><option value="<?= e($key) ?>" <?= $filters['direction'] === $key ? 'selected' : '' ?>><?= e($title) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Этап *</span><select name="status" required><?php foreach ($statuses as $key => $title): ?><option value="<?= e($key) ?>"><?= e($title) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Ответственный</span><select name="assigned_to"><option value="">Не назначен</option><?php foreach ($staff as $employee): ?><option value="<?= $employee->id ?>"><?= e($employee->fullName()) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Следующий контакт</span><?= date_picker('next_contact_at', '', true, false, 'Следующий контакт') ?></label>
                <label class="field field--wide"><span>Заметки</span><textarea name="notes" rows="4" maxlength="5000"></textarea></label>
            </div>
            <div class="modal__footer"><button class="button button--secondary" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit" id="leadFormSubmit"><span class="button__spinner" aria-hidden="true"></span><span data-submit-label>Добавить лида</span></button></div>
        </form>
    </div>
</dialog>

<?php require __DIR__ . '/_lead_details.php'; ?>
