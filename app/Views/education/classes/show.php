<?php
$patternMap = [];
foreach ($patterns as $pattern) { $patternMap[(int) $pattern['weekday']] = substr((string) $pattern['starts_at'], 0, 5); }
?>
<section class="section-heading reveal">
    <div><p class="page-eyebrow">Группа <?= e($class['group_number']) ?></p><h2><?= e($class['name']) ?></h2><p><?= e($class['study_program']) ?> · <?= e($class['teacher_name']) ?></p></div>
    <div class="page-actions"><a class="button button--secondary" href="<?= e(url('/education/classes/' . $class['id'] . '/roster')) ?>" target="_blank"><?= icon('file', 18) ?> Список курсантов</a><a class="button button--secondary" href="<?= e(url('/calendar?class_id=' . $class['id'])) ?>"><?= icon('calendar', 18) ?> Календарь</a></div>
</section>

<nav class="anchor-tabs"><a href="#overview">Основное</a><a href="#students">Курсанты</a><a href="#schedule">Расписание</a><?php if ($currentUser->canManageEducation()): ?><a href="#settings">Настройки</a><?php endif; ?></nav>

<section class="detail-metrics" id="overview">
    <article class="panel"><small>Курсантов</small><strong><?= count($students) ?></strong><span>активно в классе</span></article>
    <article class="panel"><small>Занятий</small><strong><?= count($lessons) ?></strong><span>запланировано из <?= (int) $class['planned_lesson_count'] ?></span></article>
    <article class="panel"><small>Начало</small><strong><?= e(format_date($class['starts_on'], 'd.m.Y')) ?></strong><span><?= e($class['classroom'] ?: 'Класс не указан') ?></span></article>
    <article class="panel"><small>Преподаватель</small><strong class="detail-metrics__name"><?= e($class['teacher_name']) ?></strong><span><?= e($statuses[$class['status']] ?? $class['status']) ?></span></article>
</section>

<section class="panel reveal" id="students">
    <div class="panel__header"><div><p class="page-eyebrow">Состав класса</p><h3>Курсанты</h3></div><?php if ($currentUser->canManageEducation()): ?><form class="inline-enroll" method="post" action="<?= e(url('/education/classes/' . $class['id'] . '/students')) ?>"><?= csrf_field() ?><select name="student_id" required><option value="">Добавить курсанта</option><?php foreach ($availableStudents as $candidate): ?><option value="<?= (int) $candidate['id'] ?>"><?= e(trim($candidate['last_name'] . ' ' . $candidate['first_name'] . ' ' . ($candidate['middle_name'] ?? ''))) ?></option><?php endforeach; ?></select><input type="date" name="joined_on" value="<?= e(date('Y-m-d')) ?>"><button class="button button--primary" type="submit">Зачислить</button></form><?php endif; ?></div>
    <div class="data-table-wrap"><table class="data-table stage-table"><thead><tr><th>Курсант</th><th>Телефон</th><th>Зачислен</th><th>Вождение</th><th>Расчёты</th><th></th></tr></thead><tbody>
        <?php foreach ($students as $student): ?><tr><td><strong><?= e(trim($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''))) ?></strong><small class="table-subline">@<?= e($student['login']) ?></small></td><td><?= e($student['phone'] ?: '—') ?></td><td><?= e(format_date($student['joined_on'], 'd.m.Y')) ?></td><td><span class="status-pill status-pill--<?= $student['driving_allowed'] ? 'active' : 'blocked' ?>"><i></i><?= $student['driving_allowed'] ? 'Допущен' : 'Нет допуска' ?></span></td><td><strong><?= e(number_format((float) $student['paid_total'], 0, ',', ' ')) ?> ₽</strong><small class="table-subline">из <?= e(number_format((float) $student['sold_total'], 0, ',', ' ')) ?> ₽</small></td><td><a class="table-action" href="<?= e(url('/education/students/' . $student['id'])) ?>" aria-label="Открыть карточку"><?= icon('chevron-right', 17) ?></a></td></tr><?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="6"><div class="empty-inline">В классе пока нет курсантов.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<section class="panel reveal class-schedule-panel" id="schedule">
    <div class="panel__header"><div><p class="page-eyebrow">Автоматический график</p><h3>Расписание занятий</h3></div><?php if ($currentUser->canManageEducation()): ?><form method="post" action="<?= e(url('/education/classes/' . $class['id'] . '/schedule')) ?>"><?= csrf_field() ?><button class="button button--secondary" type="submit"><?= icon('repeat', 17) ?> Пересчитать</button></form><?php endif; ?></div>
    <div class="schedule-summary"><?php foreach ($patterns as $pattern): ?><span><?= e($weekdays[(int) $pattern['weekday']]) ?> · <?= e(substr((string) $pattern['starts_at'], 0, 5)) ?></span><?php endforeach; ?><span><?= (int) $class['lesson_duration_minutes'] ?> мин.</span></div>
    <div class="lesson-list"><?php foreach ($lessons as $index => $lesson): ?><article><span class="lesson-list__number"><?= $index + 1 ?></span><div><strong><?= e(format_date($lesson['starts_at'], 'd.m.Y, H:i')) ?></strong><small><?= e($lesson['title']) ?> · до <?= e(format_date($lesson['ends_at'], 'H:i')) ?></small></div><span><?= e($lesson['teacher_name']) ?></span></article><?php endforeach; ?></div>
</section>

<?php if ($currentUser->canManageEducation()): ?>
<details class="panel stage-form-panel reveal" id="settings">
    <summary><span><?= icon('settings', 18) ?></span><strong>Настройки класса</strong><small>Изменение шаблона пересчитает запланированные занятия</small></summary>
    <form method="post" action="<?= e(url('/education/classes/' . $class['id'] . '/update')) ?>" class="stage-form" data-submit-loading>
        <?= csrf_field() ?>
        <div class="form-grid form-grid--three">
            <label class="field"><span class="field__label">Название</span><span class="field__control"><input name="name" value="<?= e($class['name']) ?>" required></span></label>
            <label class="field"><span class="field__label">Номер группы</span><span class="field__control"><input name="group_number" value="<?= e($class['group_number']) ?>" required></span></label>
            <label class="field"><span class="field__label">Учебный класс</span><span class="field__control"><input name="classroom" value="<?= e($class['classroom']) ?>"></span></label>
            <label class="field"><span class="field__label">Программа</span><span class="field__control"><input name="study_program" value="<?= e($class['study_program']) ?>" required></span></label>
            <label class="field"><span class="field__label">Преподаватель</span><span class="field__control"><select name="teacher_id" required><?php foreach ($teachers as $teacher): ?><option value="<?= $teacher->id ?>" <?= (int) $class['teacher_id'] === $teacher->id ? 'selected' : '' ?>><?= e($teacher->fullName()) ?></option><?php endforeach; ?></select></span></label>
            <label class="field"><span class="field__label">Статус</span><span class="field__control"><select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $class['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></span></label>
            <label class="field"><span class="field__label">Начало обучения</span><?= date_picker('starts_on', $class['starts_on'], false, true, 'Начало обучения') ?></label>
            <label class="field"><span class="field__label">Начало вождения</span><?= date_picker('driving_starts_on', $class['driving_starts_on'], false, false, 'Начало вождения') ?></label>
            <label class="field"><span class="field__label">Внутренний экзамен</span><?= date_picker('internal_exam_on', $class['internal_exam_on'], false, false, 'Внутренний экзамен') ?></label>
            <label class="field"><span class="field__label">Регистрация в инспекции</span><?= date_picker('inspection_registration_on', $class['inspection_registration_on'], false, false, 'Регистрация в инспекции') ?></label>
            <label class="field"><span class="field__label">Всего занятий</span><span class="field__control"><input type="number" name="planned_lesson_count" value="<?= (int) $class['planned_lesson_count'] ?>" min="1" max="500" required></span></label>
            <label class="field"><span class="field__label">Длительность, минут</span><span class="field__control"><input type="number" name="lesson_duration_minutes" value="<?= (int) $class['lesson_duration_minutes'] ?>" min="15" max="480" required></span></label>
        </div>
        <fieldset class="schedule-builder"><legend>Еженедельный график</legend><div class="schedule-builder__grid"><?php foreach ($weekdays as $number => $title): $enabled = isset($patternMap[$number]); ?><div class="schedule-day"><label class="schedule-day__toggle"><input type="checkbox" data-schedule-toggle <?= $enabled ? 'checked' : '' ?>><i aria-hidden="true"><?= icon('check', 13) ?></i><strong><?= e($title) ?></strong></label><?= time_picker('schedule[' . $number . ']', $patternMap[$number] ?? '09:00', !$enabled, 'Время занятия: ' . $title) ?></div><?php endforeach; ?></div></fieldset>
        <label class="field field--wide stage-form__notes"><span class="field__label">Примечания</span><span class="field__control"><textarea name="notes" rows="3" maxlength="5000" placeholder="Дополнительная информация об учебном классе"><?= e($class['notes']) ?></textarea></span></label>
        <div class="stage-form__actions"><button class="button button--primary" type="submit">Сохранить и пересчитать</button></div>
    </form>
</details>
<?php endif; ?>
