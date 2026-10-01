<section class="section-heading reveal">
    <div><h2>Курсанты</h2><p>Расширенные карточки, принадлежность к классу, допуск к вождению и состояние расчётов.</p></div>
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
    <div class="data-table-wrap"><table class="data-table stage-table"><thead><tr><th>Курсант</th><th>Класс</th><th>Статус</th><th>Вождение</th><th>Продано</th><th>Оплачено</th><th>Долг</th><th></th></tr></thead><tbody>
        <?php foreach ($students as $student): $debt = max(0, (float) $student['sold_total'] - (float) $student['paid_total']); ?>
            <tr><td><div class="table-person"><span class="avatar avatar--soft avatar--sm"><?= e(initials($student['first_name'], $student['last_name'])) ?></span><span><strong><?= e(trim($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''))) ?></strong><small><?= e($student['phone'] ?: '@' . $student['login']) ?></small></span></div></td><td><?= $student['group_number'] ? '<strong>' . e($student['group_number']) . '</strong><small class="table-subline">' . e($student['class_name']) . '</small>' : '<span class="muted">Без класса</span>' ?></td><td><span class="status-pill status-pill--<?= ($student['training_status'] ?? 'active') === 'active' ? 'active' : 'blocked' ?>"><i></i><?= e($statuses[$student['training_status'] ?? 'active'] ?? 'Обучается') ?></span></td><td><?= $student['driving_allowed'] ? 'Допущен' : 'Нет допуска' ?></td><td><?= e(number_format((float) $student['sold_total'], 0, ',', ' ')) ?> ₽</td><td><?= e(number_format((float) $student['paid_total'], 0, ',', ' ')) ?> ₽</td><td><strong class="<?= $debt > 0 ? 'amount-due' : 'amount-ok' ?>"><?= e(number_format($debt, 0, ',', ' ')) ?> ₽</strong></td><td><a class="table-action" href="<?= e(url('/education/students/' . $student['id'])) ?>" aria-label="Открыть карточку"><?= icon('chevron-right', 17) ?></a></td></tr>
        <?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="8"><div class="empty-state empty-state--table"><span><?= icon('users', 27) ?></span><h3>Курсанты не найдены</h3><p>Измените фильтры или создайте учётную запись курсанта.</p></div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
