<?php
$selectedFolder = null;
foreach ($folders as $folder) { if ((int) $folder['id'] === (int) $selectedFolderId) { $selectedFolder = $folder; break; } }
$formatBalance = static function (float $amount, string $unit): string {
    $value = rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',');
    return $unit === 'money' ? $value . ' ₽' : ($unit === 'hours' ? $value . ' ч' : $value);
};
?>
<section class="section-heading reveal">
    <div><h2>Учебные классы</h2><p>Классы сгруппированы по папкам; внутри сразу видны курсанты, контрольные точки и балансы.</p></div>
    <?php if ($currentUser->canManageEducation()): ?><div class="page-actions"><a class="button button--secondary" href="<?= e(url('/education/programs')) ?>"><?= icon('book-open', 17) ?> Учебные программы</a><a class="button button--secondary" href="<?= e(url('/education/checkpoints')) ?>"><?= icon('check-square', 17) ?> Контрольные точки</a></div><?php endif; ?>
</section>

<div class="catalog-layout class-browser">
    <aside class="panel catalog-folders reveal">
        <div class="panel__header"><div><p class="page-eyebrow">Навигатор</p><h3>Папки классов</h3></div><span class="catalog-count"><?= count($folders) ?></span></div>
        <nav>
            <?php foreach ($folders as $folder): ?><a class="<?= (int) $folder['id'] === (int) $selectedFolderId ? 'is-active' : '' ?>" href="<?= e(url('/education/classes?folder_id=' . $folder['id'])) ?>"><span class="folder-mark"><?= icon('folder', 19) ?></span><span><strong><?= e($folder['name']) ?></strong><small><?= (int) $folder['class_count'] ?> классов · <?= (int) $folder['student_count'] ?> курсантов</small></span><?= icon('chevron-right', 15) ?></a><?php endforeach; ?>
        </nav>
        <?php if ($currentUser->canManageEducation()): ?><form class="catalog-add-form" method="post" action="<?= e(url('/education/class-folders')) ?>"><?= csrf_field() ?><input name="name" required maxlength="120" placeholder="Новая папка"><input type="hidden" name="status" value="active"><button class="icon-button" type="submit" aria-label="Добавить папку"><?= icon('plus', 17) ?></button></form><?php endif; ?>
    </aside>

    <main class="catalog-content">
        <section class="folder-heading panel reveal">
            <span class="folder-heading__icon"><?= icon('folder', 28) ?></span>
            <div><p class="page-eyebrow">Выбранная папка</p><h2><?= e($selectedFolder['name'] ?? 'Учебные классы') ?></h2><p><?= (int) ($selectedFolder['class_count'] ?? 0) ?> классов · <?= (int) ($selectedFolder['student_count'] ?? 0) ?> курсантов</p></div>
        </section>

        <?php if ($currentUser->canManageEducation()): ?>
        <details class="panel stage-form-panel reveal" <?= !$classes && $selectedFolder ? 'open' : '' ?>>
            <summary><span><?= icon('plus', 18) ?></span><strong>Создать учебный класс</strong><small>Класс будет добавлен в папку и связан с учебной программой</small></summary>
            <form method="post" action="<?= e(url('/education/classes')) ?>" class="stage-form" data-submit-loading>
                <?= csrf_field() ?>
                <div class="form-grid form-grid--three">
                    <label class="field"><span class="field__label">Папка <b>*</b></span><span class="field__control"><select name="folder_id" required><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>" <?= (int) $folder['id'] === (int) $selectedFolderId ? 'selected' : '' ?>><?= e($folder['name']) ?></option><?php endforeach; ?></select></span></label>
                    <label class="field"><span class="field__label">Название <b>*</b></span><span class="field__control"><input name="name" required maxlength="120" placeholder="Категория B"></span></label>
                    <label class="field"><span class="field__label">Номер группы <b>*</b></span><span class="field__control"><input name="group_number" required maxlength="80" placeholder="B-2026-01"></span></label>
                    <label class="field"><span class="field__label">Учебный класс</span><span class="field__control"><input name="classroom" maxlength="190" placeholder="Кабинет 3"></span></label>
                    <label class="field"><span class="field__label">Учебная программа <b>*</b></span><span class="field__control"><select name="program_id" required><option value="">Выберите программу</option><?php foreach ($programs as $program): ?><option value="<?= (int) $program['id'] ?>"><?= e($program['folder_name'] . ' · ' . $program['name']) ?></option><?php endforeach; ?></select></span></label>
                    <label class="field"><span class="field__label">Преподаватель <b>*</b></span><span class="field__control"><select name="teacher_id" required><option value="">Выберите</option><?php foreach ($teachers as $teacher): ?><option value="<?= $teacher->id ?>"><?= e($teacher->fullName()) ?> · <?= e($teacher->roleTitle()) ?></option><?php endforeach; ?></select></span></label>
                    <label class="field"><span class="field__label">Начало обучения <b>*</b></span><?= date_picker('starts_on', date('Y-m-d'), false, true, 'Начало обучения') ?></label>
                    <label class="field"><span class="field__label">Начало вождения</span><?= date_picker('driving_starts_on', '', false, false, 'Начало вождения') ?></label>
                    <label class="field"><span class="field__label">Внутренний экзамен</span><?= date_picker('internal_exam_on', '', false, false, 'Внутренний экзамен') ?></label>
                    <label class="field"><span class="field__label">Регистрация в инспекции</span><?= date_picker('inspection_registration_on', '', false, false, 'Регистрация в инспекции') ?></label>
                    <label class="field"><span class="field__label">Всего занятий <b>*</b></span><span class="field__control"><input type="number" name="planned_lesson_count" value="20" min="1" max="500" required></span></label>
                    <label class="field"><span class="field__label">Длительность, минут <b>*</b></span><span class="field__control"><input type="number" name="lesson_duration_minutes" value="90" min="15" max="480" step="5" required></span></label>
                </div>
                <fieldset class="schedule-builder"><legend>Еженедельный график</legend><p>Отметьте дни и укажите время. Праздничные даты будут пропущены автоматически.</p><div class="schedule-builder__grid"><?php foreach ($weekdays as $number => $title): ?><div class="schedule-day"><label class="schedule-day__toggle"><input type="checkbox" data-schedule-toggle><i aria-hidden="true"><?= icon('check', 13) ?></i><strong><?= e($title) ?></strong></label><?= time_picker('schedule[' . $number . ']', '09:00', true, 'Время занятия: ' . $title) ?></div><?php endforeach; ?></div></fieldset>
                <label class="field field--wide stage-form__notes"><span class="field__label">Примечания</span><span class="field__control"><textarea name="notes" rows="3" maxlength="5000" placeholder="Дополнительная информация об учебном классе"></textarea></span></label>
                <div class="stage-form__actions"><button class="button button--primary" type="submit"><?= icon('calendar', 18) ?> Создать и построить расписание</button><?php if (!$programs): ?><a class="button button--secondary" href="<?= e(url('/education/programs')) ?>">Сначала добавить программу</a><?php endif; ?></div>
            </form>
        </details>
        <?php endif; ?>

        <div class="folder-classes stagger-group">
            <?php foreach ($classes as $class): ?>
            <article class="panel folder-class reveal">
                <header class="folder-class__header"><div><p class="page-eyebrow">Группа <?= e($class['group_number']) ?></p><h3><?= e($class['name']) ?></h3><p><?= e($class['study_program_name'] ?: $class['study_program']) ?> · <?= e($class['teacher_name']) ?></p></div><div><span class="status-pill status-pill--<?= $class['status'] === 'active' ? 'active' : 'blocked' ?>"><i></i><?= e($statuses[$class['status']] ?? $class['status']) ?></span><a class="button button--secondary" href="<?= e(url('/education/classes/' . $class['id'])) ?>">Открыть класс</a></div></header>
                <div class="data-table-wrap"><table class="data-table stage-table class-roster-table"><thead><tr><th>Курсант</th><th>Контрольные точки</th><th>Балансы</th><th>Расчёты</th><th></th></tr></thead><tbody>
                    <?php foreach ($class['students'] as $student): $studentId = (int) $student['id']; ?><tr>
                        <td><strong><?= e(trim($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''))) ?></strong><small class="table-subline">@<?= e($student['login']) ?> · <?= e($student['phone'] ?: 'телефон не указан') ?></small></td>
                        <td><div class="checkpoint-strip"><?php foreach ($checkpoints as $checkpoint): $done = !empty($checkpointStates[$studentId][(int) $checkpoint['id']]['is_completed']); ?><form method="post" action="<?= e(url('/education/students/' . $studentId . '/checkpoints/' . $checkpoint['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="completed" value="<?= $done ? '0' : '1' ?>"><input type="hidden" name="class_id" value="<?= (int) $class['id'] ?>"><button class="checkpoint-chip <?= $done ? 'is-complete' : '' ?>" type="submit" title="<?= e($checkpoint['name']) ?> · <?= $done ? 'снять отметку' : 'отметить пройденной' ?>"><?= e($checkpoint['code']) ?></button></form><?php endforeach; ?><?php if (!$checkpoints): ?><span class="table-subline">Не настроены</span><?php endif; ?></div></td>
                        <td><div class="balance-strip"><?php foreach ($balanceTypes as $type): $amount = (float) ($balanceValues[$studentId][(int) $type['id']] ?? 0); ?><span class="balance-chip" title="<?= e($type['name']) ?>"><b style="--chip-color:<?= e($type['color']) ?>"><?= e($type['code']) ?></b><span><?= e($formatBalance($amount, $type['unit'])) ?></span></span><?php endforeach; ?><?php if (!$balanceTypes): ?><span class="table-subline">Не настроены</span><?php endif; ?></div></td>
                        <td><strong><?= e(number_format((float) $student['paid_total'], 0, ',', ' ')) ?> ₽</strong><small class="table-subline">из <?= e(number_format((float) $student['sold_total'], 0, ',', ' ')) ?> ₽</small></td>
                        <td><a class="table-action" href="<?= e(url('/education/students/' . $studentId)) ?>" aria-label="Открыть карточку"><?= icon('chevron-right', 17) ?></a></td>
                    </tr><?php endforeach; ?>
                    <?php if (!$class['students']): ?><tr><td colspan="5"><div class="empty-inline">В классе пока нет курсантов.</div></td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
            <?php endforeach; ?>
            <?php if (!$classes): ?><div class="panel empty-state"><span><?= icon('folder', 28) ?></span><h3>В папке пока нет классов</h3><p><?= $currentUser->canManageEducation() ? 'Создайте учебный класс и выберите эту папку.' : 'Назначенные вам классы здесь не найдены.' ?></p></div><?php endif; ?>
        </div>
    </main>
</div>
