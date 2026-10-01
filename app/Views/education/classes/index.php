<section class="section-heading reveal">
    <div><h2>Учебные классы</h2><p>Создавайте группы, назначайте преподавателя и сразу формируйте весь график занятий.</p></div>
</section>

<?php if ($currentUser->canManageEducation()): ?>
<details class="panel stage-form-panel reveal" <?= !$classes ? 'open' : '' ?>>
    <summary><span><?= icon('plus', 18) ?></span><strong>Создать учебный класс</strong><small>Основные данные и недельный шаблон расписания</small></summary>
    <form method="post" action="<?= e(url('/education/classes')) ?>" class="stage-form" data-submit-loading>
        <?= csrf_field() ?>
        <div class="form-grid form-grid--three">
            <label class="field"><span class="field__label">Название <b>*</b></span><span class="field__control"><input name="name" required maxlength="120" placeholder="Категория B"></span></label>
            <label class="field"><span class="field__label">Номер группы <b>*</b></span><span class="field__control"><input name="group_number" required maxlength="80" placeholder="B-2026-01"></span></label>
            <label class="field"><span class="field__label">Учебный класс</span><span class="field__control"><input name="classroom" maxlength="190" placeholder="Кабинет 3"></span></label>
            <label class="field"><span class="field__label">Учебная программа <b>*</b></span><span class="field__control"><input name="study_program" required maxlength="190" placeholder="Подготовка водителей категории B"></span></label>
            <label class="field"><span class="field__label">Преподаватель <b>*</b></span><span class="field__control"><select name="teacher_id" required><option value="">Выберите</option><?php foreach ($teachers as $teacher): ?><option value="<?= $teacher->id ?>"><?= e($teacher->fullName()) ?> · <?= e($teacher->roleTitle()) ?></option><?php endforeach; ?></select></span></label>
            <label class="field"><span class="field__label">Начало обучения <b>*</b></span><?= date_picker('starts_on', date('Y-m-d'), false, true, 'Начало обучения') ?></label>
            <label class="field"><span class="field__label">Начало вождения</span><?= date_picker('driving_starts_on', '', false, false, 'Начало вождения') ?></label>
            <label class="field"><span class="field__label">Внутренний экзамен</span><?= date_picker('internal_exam_on', '', false, false, 'Внутренний экзамен') ?></label>
            <label class="field"><span class="field__label">Регистрация в инспекции</span><?= date_picker('inspection_registration_on', '', false, false, 'Регистрация в инспекции') ?></label>
            <label class="field"><span class="field__label">Всего занятий <b>*</b></span><span class="field__control"><input type="number" name="planned_lesson_count" value="20" min="1" max="500" required></span></label>
            <label class="field"><span class="field__label">Длительность, минут <b>*</b></span><span class="field__control"><input type="number" name="lesson_duration_minutes" value="90" min="15" max="480" step="5" required></span></label>
        </div>
        <fieldset class="schedule-builder">
            <legend>Еженедельный график</legend>
            <p>Отметьте дни и укажите время. Праздничные даты будут пропущены автоматически.</p>
            <div class="schedule-builder__grid">
                <?php foreach ($weekdays as $number => $title): ?>
                    <div class="schedule-day"><label class="schedule-day__toggle"><input type="checkbox" data-schedule-toggle><i aria-hidden="true"><?= icon('check', 13) ?></i><strong><?= e($title) ?></strong></label><?= time_picker('schedule[' . $number . ']', '09:00', true, 'Время занятия: ' . $title) ?></div>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <label class="field field--wide stage-form__notes"><span class="field__label">Примечания</span><span class="field__control"><textarea name="notes" rows="3" maxlength="5000" placeholder="Дополнительная информация об учебном классе"></textarea></span></label>
        <div class="stage-form__actions"><button class="button button--primary" type="submit"><?= icon('calendar', 18) ?> Создать и построить расписание</button></div>
    </form>
</details>
<?php endif; ?>

<section class="class-grid stagger-group">
    <?php foreach ($classes as $class): ?>
        <article class="panel class-card reveal">
            <div class="class-card__top"><span class="metric-icon metric-icon--blue"><?= icon('users', 22) ?></span><span class="status-pill status-pill--<?= $class['status'] === 'active' ? 'active' : 'blocked' ?>"><i></i><?= e($statuses[$class['status']] ?? $class['status']) ?></span></div>
            <p class="page-eyebrow">Группа <?= e($class['group_number']) ?></p>
            <h3><?= e($class['name']) ?></h3>
            <dl class="class-card__meta"><div><dt>Преподаватель</dt><dd><?= e($class['teacher_name']) ?></dd></div><div><dt>Программа</dt><dd><?= e($class['study_program']) ?></dd></div><div><dt>Курсанты</dt><dd><?= (int) $class['student_count'] ?></dd></div><div><dt>Занятия</dt><dd><?= (int) $class['lesson_count'] ?> / <?= (int) $class['planned_lesson_count'] ?></dd></div></dl>
            <div class="class-card__footer"><span><?= $class['next_lesson_at'] ? 'Следующее: ' . e(format_date($class['next_lesson_at'], 'd.m.Y H:i')) : 'Ближайших занятий нет' ?></span><a class="button button--secondary" href="<?= e(url('/education/classes/' . $class['id'])) ?>">Открыть</a></div>
        </article>
    <?php endforeach; ?>
    <?php if (!$classes): ?><div class="panel empty-state"><span><?= icon('calendar', 28) ?></span><h3>Учебных классов пока нет</h3><p>Создайте первый класс и задайте его недельный график.</p></div><?php endif; ?>
</section>
