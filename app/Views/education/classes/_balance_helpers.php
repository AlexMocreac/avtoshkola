<?php
$renderStudentBalances = static function (int $studentId) use ($contractProgressByStudent, $balanceTypes, $balanceValues, $formatBalance): void {
    $groups = $contractProgressByStudent[$studentId] ?? [];
    if ($groups) {
        foreach ($groups as $group) foreach ($group['balances'] as $balance) {
            $type = $balance['type'];
            ?><span class="balance-chip" title="<?= e($type['name'] . ' · договор № ' . $group['contract']['contract_number'] . ' · проведено / назначено') ?>"><b style="--chip-color:<?= e($type['color']) ?>"><?= e($type['code']) ?></b><span><?= e($formatBalance($balance['manual'] + $balance['automatic'], $type['unit'])) ?> / <?= e($formatBalance($balance['assigned'], $type['unit'])) ?></span></span><?php
        }
    } else {
        $shown = false;
        foreach ($balanceTypes as $type) {
            $amount = (float) ($balanceValues[$studentId][(int) $type['id']] ?? 0);
            if (!$amount) continue;
            $shown = true;
            ?><span class="balance-chip" title="<?= e($type['name'] . ' · прежний баланс') ?>"><b style="--chip-color:<?= e($type['color']) ?>"><?= e($type['code']) ?></b><span><?= e($formatBalance($amount, $type['unit'])) ?></span></span><?php
        }
        if (!$shown) echo '<span class="table-subline">Не настроены</span>';
    }
};
