<?php
$selectedLeadName = $selectedLead ? \App\Models\Lead::displayName($selectedLead) : '';
$selectedDirection = $selectedLead['direction'] ?? 'driving_school';
$selectedSubject = $selectedLead ? 'Обучение — ' . ($directions[$selectedDirection] ?? 'Автошкола') : '';
?>
<section class="page-actions reveal">
    <div><h2>Журнал заключённых договоров</h2><p>Общий реестр договоров, созданных из CRM и, в дальнейшем, из учебных групп.</p></div>
    <span class="journal-source-note"><?= icon('file', 17) ?> Создание — из лида или учебной группы</span>
</section>

<section class="panel reveal">
    <form class="filter-bar filter-bar--contracts" method="get" action="<?= e(url('/crm/contracts')) ?>">
        <label class="search-field"><?= icon('search', 18) ?><input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="ФИО, телефон или номер договора"></label>
        <?= date_picker('date_from', $filters['date_from'], false, false, 'Дата договора от') ?>
        <?= date_picker('date_to', $filters['date_to'], false, false, 'Дата договора до') ?>
        <select name="status" aria-label="Статус"><option value="">Все статусы</option><?php foreach ($statuses as $key => $title): ?><option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($title) ?></option><?php endforeach; ?></select>
        <button class="button button--secondary" type="submit">Применить</button>
        <div class="column-settings" data-column-settings>
            <button class="button button--ghost" type="button" aria-expanded="false" data-column-settings-toggle><?= icon('settings', 16) ?> Столбцы</button>
            <div class="column-settings__menu is-hidden" data-column-settings-menu>
                <strong>Показывать в журнале</strong>
                <?php foreach (['client' => 'ФИО', 'phone' => 'Телефон', 'number' => 'Номер договора', 'registration' => 'Рег. номер', 'signed' => 'Дата договора', 'ends' => 'Дата окончания', 'direction' => 'Направление', 'manager' => 'Менеджер', 'amount' => 'Сумма', 'status' => 'Статус'] as $column => $label): ?>
                    <label><input type="checkbox" value="<?= e($column) ?>" checked data-contract-column-toggle> <span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
    </form>

    <div class="data-table-wrap contracts-table-wrap">
        <table class="data-table contracts-table" data-contract-table>
            <thead><tr>
                <th data-contract-column="client">ФИО</th><th data-contract-column="phone">Телефон</th><th data-contract-column="number">Номер договора</th><th data-contract-column="registration">Рег. номер</th><th data-contract-column="signed">Дата договора</th><th data-contract-column="ends">Дата окончания</th><th data-contract-column="direction">Направление</th><th data-contract-column="manager">Менеджер</th><th data-contract-column="amount">Сумма</th><th data-contract-column="status">Статус</th><th><span class="sr-only">Действия</span></th>
            </tr></thead>
            <tbody>
            <?php if (!$contracts): ?><tr><td colspan="11"><div class="empty-state"><span><?= icon('file', 28) ?></span><h3>Договоров не найдено</h3><p>Измените фильтры или создайте договор из карточки лида.</p></div></td></tr><?php endif; ?>
            <?php foreach ($contracts as $contract): ?>
                <?php $contractJson = e(json_encode($contract, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>
                <tr id="contract-<?= (int) $contract['id'] ?>">
                    <td data-label="ФИО" data-contract-column="client"><?php if ($contract['lead_id']): ?><button class="table-person-link" type="button" data-lead-details="<?= $contract['lead_id'] ?>"><?= e($contract['counterparty']) ?></button><?php else: ?><strong><?= e($contract['counterparty']) ?></strong><?php endif; ?><small><?= e($contract['subject']) ?></small></td>
                    <td data-label="Телефон" data-contract-column="phone"><?php $phone = $contract['client_phone'] ?: $contract['lead_phone']; ?><?php if ($phone): ?><a class="contract-phone" href="tel:<?= e(preg_replace('/[^+0-9]/', '', $phone)) ?>"><?= icon('phone', 14) ?> <?= e($phone) ?></a><?php else: ?>—<?php endif; ?></td>
                    <td data-label="Номер договора" data-contract-column="number"><strong>№<?= e($contract['contract_number']) ?></strong><?php if (!empty($contractFiles[(int) $contract['id']])): ?><div class="contract-files"><?php foreach ($contractFiles[(int) $contract['id']] as $file): ?><a href="<?= e(url('/crm/contracts/files/' . $file['id'])) ?>" title="Скачать <?= e($file['original_name']) ?>"><?= icon('paperclip', 13) ?><span><?= e($file['original_name']) ?></span><small><?= e(format_bytes((int) $file['size_bytes'])) ?></small></a><?php endforeach; ?></div><?php endif; ?></td>
                    <td data-label="Рег. номер" data-contract-column="registration"><?= $contract['registration_number'] ? e($contract['registration_number']) : '—' ?></td>
                    <td data-label="Дата договора" data-contract-column="signed"><strong><?= e(format_date($contract['signed_on'], 'd.m.Y')) ?></strong></td>
                    <td data-label="Дата окончания" data-contract-column="ends"><?= $contract['ends_on'] ? e(format_date($contract['ends_on'], 'd.m.Y')) : '—' ?></td>
                    <td data-label="Направление" data-contract-column="direction"><span class="direction-chip"><?= e($directions[$contract['direction']] ?? $contract['direction']) ?></span></td>
                    <td data-label="Менеджер" data-contract-column="manager"><?= e($contract['manager_name'] ?: '—') ?></td>
                    <td data-label="Сумма" data-contract-column="amount"><?= $contract['amount'] !== null ? e(number_format((float) $contract['amount'], 2, ',', ' ')) . ' ₽' : '—' ?></td>
                    <td data-label="Статус" data-contract-column="status"><span class="status-pill status-pill--contract-<?= e($contract['status']) ?>"><i></i><?= e($statuses[$contract['status']] ?? $contract['status']) ?></span></td>
                    <td><div class="table-actions"><button class="icon-button" type="button" title="Редактировать" data-record-edit="contract" data-record="<?= $contractJson ?>" data-dialog-open="contractModal"><?= icon('edit', 17) ?></button><form method="post" action="<?= e(url('/crm/contracts/' . $contract['id'] . '/archive')) ?>" data-confirm-message="Перенести договор в архив?"><?= csrf_field() ?><button class="icon-button icon-button--danger" type="submit" title="В архив"><?= icon('trash', 17) ?></button></form></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="modal" id="contractModal">
    <div class="modal__surface modal__surface--wide">
        <div class="modal__header"><div><p class="page-eyebrow" id="contractModalEyebrow">Новая запись</p><h2 id="contractModalTitle">Оформить договор</h2><p>Номер сформируется автоматически, если оставить поле пустым.</p></div><button class="icon-button modal__close" type="button" data-dialog-close><?= icon('x', 20) ?></button></div>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('/crm/contracts')) ?>" id="contractForm" data-create-action="<?= e(url('/crm/contracts')) ?>" data-update-template="<?= e(url('/crm/contracts/__id__/update')) ?>" data-auto-open="<?= $selectedLead ? '1' : '0' ?>" data-submit-loading>
            <?= csrf_field() ?><input type="hidden" name="lead_id" value="<?= $selectedLead ? (int) $selectedLead['id'] : '' ?>">
            <div class="modal__body form-grid">
                <?php if ($selectedLead): ?><div class="contract-source field--wide"><span class="avatar avatar--soft"><?= e(initials($selectedLead['first_name'] ?: 'Л', $selectedLead['last_name'] ?: '')) ?></span><span><small>Договор из CRM</small><strong><?= e($selectedLeadName) ?></strong><em><?= e($selectedLead['phone'] ?: 'Телефон не указан') ?></em></span></div><?php endif; ?>
                <label class="field"><span>Номер договора</span><input name="contract_number" maxlength="80" placeholder="Присвоится автоматически"></label>
                <label class="field"><span>Регистрационный номер</span><input name="registration_number" maxlength="80" placeholder="Заполняется вручную"></label>
                <label class="field"><span>ФИО клиента *</span><input name="counterparty" maxlength="190" value="<?= e($selectedLeadName) ?>" required></label>
                <label class="field"><span>Телефон</span><input name="client_phone" type="tel" maxlength="18" autocomplete="tel" placeholder="+7 (900) 000-00-00" value="<?= e($selectedLead['phone'] ?? '') ?>"></label>
                <label class="field field--wide"><span>Предмет договора *</span><input name="subject" maxlength="255" value="<?= e($selectedSubject) ?>" required></label>
                <label class="field"><span>Направление *</span><select name="direction" required><?php foreach ($directions as $key => $title): ?><option value="<?= e($key) ?>" <?= $selectedDirection === $key ? 'selected' : '' ?>><?= e($title) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Статус *</span><select name="status" required><?php foreach ($statuses as $key => $title): ?><option value="<?= e($key) ?>" <?= $key === 'active' ? 'selected' : '' ?>><?= e($title) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Дата заключения *</span><?= date_picker('signed_on', date('Y-m-d'), false, true, 'Дата заключения') ?></label>
                <label class="field"><span>Начало действия</span><?= date_picker('starts_on', '', false, false, 'Начало действия') ?></label>
                <label class="field"><span>Окончание действия</span><?= date_picker('ends_on', '', false, false, 'Окончание действия') ?></label>
                <label class="field"><span>Сумма, ₽</span><input type="number" name="amount" min="0" step="0.01"></label>
                <label class="field field--wide"><span>Примечание</span><textarea name="notes" rows="4" maxlength="5000"></textarea></label>
                <label class="field field--wide"><span>Файлы договора</span><span class="file-upload"><input class="sr-only" type="file" name="files[]" multiple accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx" data-file-input><span class="file-upload__icon"><?= icon('upload', 21) ?></span><span class="file-upload__copy"><strong>Выберите один или несколько файлов</strong><small data-file-summary>PDF, изображения и документы · до 10 файлов · по 20 МБ</small></span><span class="file-upload__action">Выбрать</span></span></label>
            </div>
            <div class="modal__footer"><button class="button button--secondary" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit" id="contractFormSubmit">Заключить договор</button></div>
        </form>
    </div>
</dialog>

<?php require __DIR__ . '/_lead_details.php'; ?>
