<?php
$formatBalance = static function (float $amount, string $unit): string {
    $value = rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',');
    return $unit === 'money' ? $value . ' ₽' : ($unit === 'hours' ? $value . ' ч' : $value);
};
require __DIR__ . '/../classes/_balance_helpers.php';
?>
<section class="section-heading reveal">
    <div><h2>Курсанты</h2><p>Карточки курсантов, классы, контрольные точки, балансы и расчёты.</p></div>
    <?php if ($currentUser->canManageUsers()): ?><a class="button button--primary" href="<?= e(url('/students')) ?>"><?= icon('plus', 18) ?> Создать учётную запись</a><?php endif; ?>
</section>

<section class="panel users-panel reveal">
    <form class="filter-bar stage-filter" method="get" action="<?= e(url('/education/students')) ?>">
        <label class="search-field"><?= icon('search', 18) ?><input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="ФИО, телефон, логин или группа"></label>
        <label class="compact-select"><span>Класс</span><select name="class_id" onchange="this.form.submit()"><option value="">Все классы</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= (int) $filters['class_id'] === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['group_number']) ?></option><?php endforeach; ?></select></label>
        <label class="compact-select"><span>Обучение</span><select name="training_status" onchange="this.form.submit()"><option value="">Все статусы</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['training_status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <button class="button button--secondary" type="submit">Найти</button>
    </form>
    <div class="table-summary"><span>Найдено: <strong><?= count($students) ?></strong></span><small>Карточки курсантов</small></div>
    <div class="data-table-wrap"><table class="data-table stage-table class-roster-table"><thead><tr><th>Курсант</th><th>Класс</th><th>Статус</th><th>Доступ</th><th>Контрольные точки</th><th>Балансы</th><th>Расчёты</th><th></th></tr></thead><tbody>
        <?php foreach ($students as $student): $studentId = (int) $student['id']; $accessActive = $student['status'] === 'active' && empty($student['access_expired']); $accessLabel = $accessActive ? 'Доступ открыт' : 'Доступ закрыт';
            $userData = array_intersect_key($student, array_flip(['id', 'login', 'email', 'phone', 'first_name', 'last_name', 'middle_name', 'role', 'access_months']));
            $userData['photo_url'] = !empty($student['photo_path']) ? url('/education/students/' . $studentId . '/photo') . '?v=' . rawurlencode(basename($student['photo_path'])) : null;
            $userJson = json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
            <tr<?= $currentUser->canManageUsers() ? ' data-user-row data-class-student-edit data-user="' . e($userJson) . '"' : '' ?>>
                <td><div class="table-person"><span class="avatar avatar--soft avatar--sm"><?= e(initials($student['first_name'], $student['last_name'])) ?></span><span><a class="table-person-link" href="<?= e(url('/education/students/' . $studentId)) ?>"><?= e(trim($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''))) ?></a><small>@<?= e($student['login']) ?> · <?= e($student['phone'] ?: 'телефон не указан') ?></small></span></div></td>
                <td data-label="Класс"><?= $student['group_number'] ? '<strong>' . e($student['group_number']) . '</strong><small class="table-subline">' . e($student['class_name']) . '</small>' : '<span class="muted">Без класса</span>' ?></td>
                <td data-label="Статус"><span class="status-pill status-pill--<?= ($student['training_status'] ?? 'active') === 'active' ? 'active' : 'blocked' ?>"><i></i><?= e($statuses[$student['training_status'] ?? 'active'] ?? 'Обучается') ?></span></td>
                <td data-label="Доступ"><span class="student-access-icon <?= $accessActive ? 'is-active' : 'is-inactive' ?>" aria-label="<?= e($accessLabel) ?>" title="<?= e($accessLabel) ?>"><?= icon('smartphone', 20) ?></span></td>
                <td data-label="Контрольные точки"><div class="checkpoint-strip"><?php foreach ($checkpoints as $checkpoint): $done = !empty($checkpointStates[$studentId][(int) $checkpoint['id']]['is_completed']); ?><span class="checkpoint-chip <?= $done ? 'is-complete' : '' ?>" title="<?= e($checkpoint['name']) ?>"><?= e($checkpoint['code']) ?></span><?php endforeach; ?><?php if (!$checkpoints): ?><span class="table-subline">Не настроены</span><?php endif; ?></div></td>
                <td data-label="Балансы"><div class="balance-strip"><?php $renderStudentBalances($studentId); ?></div></td>
                <td data-label="Расчёты"><strong><?= e(number_format((float) $student['paid_total'], 0, ',', ' ')) ?> ₽</strong><small class="table-subline">из <?= e(number_format((float) $student['sold_total'], 0, ',', ' ')) ?> ₽</small></td>
                <td><?php if ($currentUser->canManageUsers()): ?><button type="button" class="table-action" data-user-edit title="Редактировать" aria-label="Редактировать"><?= icon('edit', 17) ?></button><?php endif; ?><a class="table-action" href="<?= e(url('/education/students/' . $studentId)) ?>" aria-label="Открыть карточку"><?= icon('chevron-right', 17) ?></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="8"><div class="empty-state empty-state--table"><span><?= icon('users', 27) ?></span><h3>Курсанты не найдены</h3><p>Измените фильтры или создайте учётную запись курсанта.</p></div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<?php if ($currentUser->canManageUsers()): ?>
<?php $showStudentPhoto = true; $showSalesAction = $currentUser->canManageEducation(); $group = 'students'; $assignableRoles = [\App\Models\User::ROLE_STUDENT => \App\Models\User::ROLES[\App\Models\User::ROLE_STUDENT]]; require __DIR__ . '/../../users/_form_modal.php'; ?>
<?php endif; ?>
<?php if ($currentUser->canManageEducation() && $currentUser->canManageUsers()): require __DIR__ . '/../classes/_sales_modal.php'; endif; ?>
