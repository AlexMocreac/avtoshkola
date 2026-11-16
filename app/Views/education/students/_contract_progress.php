<?php
$progressNumber = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
?>
<section class="panel reveal contract-progress">
    <div class="panel__header"><div><p class="page-eyebrow">Обучение</p><h3>Занятия по договорам</h3></div></div>
    <?php if (!$contractProgress): ?><div class="empty-inline">Проданные услуги с учебными начислениями пока не добавлены.</div><?php endif; ?>
    <?php foreach ($contractProgress as $group): ?>
        <div class="contract-progress__group"><h4>Договор № <?= e($group['contract']['contract_number']) ?></h4>
        <div class="data-table-wrap"><table class="data-table stage-table"><thead><tr><th>Тип баланса</th><th>Назначено</th><th>Оплачено</th><th>Проведено</th><?php if ($canEditContractProgress): ?><th><span class="sr-only">Действия</span></th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($group['balances'] as $balance): $type = $balance['type']; $contractId = (int) $group['contract']['id']; $typeId = (int) $type['id']; $unit = $type['unit'] === 'hours' ? 'ч' : ($type['unit'] === 'money' ? '₽' : ''); ?>
            <tr><td><span class="balance-code" style="--chip-color:<?= e($type['color']) ?>"><?= e($type['code']) ?></span> <strong><?= e($type['name']) ?></strong></td><td><?= e($progressNumber($balance['assigned'])) ?> <?= e($unit) ?></td><td><?= e($progressNumber($balance['paid'])) ?> <?= e($unit) ?></td><td><?= e($progressNumber($balance['manual'] + $balance['automatic'])) ?> <?= e($unit) ?></td><?php if ($canEditContractProgress): ?><td><button type="button" class="table-action" data-dialog-open="progress-<?= $contractId ?>-<?= $typeId ?>" aria-label="Редактировать прогресс: <?= e($type['name']) ?>" title="Редактировать"><?= icon('edit', 17) ?></button></td><?php endif; ?></tr>
        <?php endforeach; ?></tbody></table></div></div>
        <?php if ($canEditContractProgress): foreach ($group['balances'] as $balance): $type = $balance['type']; $contractId = (int) $group['contract']['id']; $typeId = (int) $type['id']; ?>
            <dialog class="modal" id="progress-<?= $contractId ?>-<?= $typeId ?>" aria-labelledby="progress-title-<?= $contractId ?>-<?= $typeId ?>"><form class="modal__surface" method="post" action="<?= e(url('/education/students/' . $progressStudentId . '/contracts/' . $contractId . '/balances/' . $typeId . '/progress')) ?>"><?= csrf_field() ?><div class="modal__header"><div><p class="page-eyebrow">Договор № <?= e($group['contract']['contract_number']) ?></p><h2 id="progress-title-<?= $contractId ?>-<?= $typeId ?>">Редактировать прогресс</h2><p><?= e($type['name']) ?></p></div><button type="button" class="icon-button modal__close" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div><div class="modal__body"><label class="field"><span class="field__label">Проведено вручную</span><span class="field__control"><input name="amount" type="number" min="0" max="9999999999.99" step="0.01" value="<?= e((string) $balance['manual']) ?>" required></span></label><p>Автоматически учтённые занятия добавляются отдельно.</p></div><div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary">Сохранить</button></div></form></dialog>
        <?php endforeach; endif; ?>
    <?php endforeach; ?>
</section>
