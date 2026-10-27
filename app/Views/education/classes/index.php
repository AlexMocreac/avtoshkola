<?php
$selectedFolder = null;
foreach ($folders as $folder) {
    if ((int) $folder['id'] === (int) $selectedFolderId) {
        $selectedFolder = $folder;
        break;
    }
}
$formatBalance = static function (float $amount, string $unit): string {
    $value = rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',');
    return $unit === 'money' ? $value . ' ₽' : ($unit === 'hours' ? $value . ' ч' : $value);
};
$studentName = static fn (array $student): string => trim($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''));
?>
<section class="section-heading reveal">
    <div><h2>Учебные классы</h2><p>Выберите папку, чтобы увидеть её классы, или класс — чтобы открыть список курсантов.</p></div>
    <?php if ($currentUser->canManageEducation()): ?><div class="page-actions"><a class="button button--secondary" href="<?= e(url('/education/programs')) ?>"><?= icon('book-open', 17) ?> Учебные программы</a><a class="button button--secondary" href="<?= e(url('/education/checkpoints')) ?>"><?= icon('check-square', 17) ?> Контрольные точки</a></div><?php endif; ?>
</section>

<div class="catalog-layout class-browser">
    <aside class="panel catalog-folders class-navigator reveal">
        <div class="panel__header"><div><p class="page-eyebrow">Навигатор</p><h3>Папки и классы</h3></div><span class="catalog-count"><?= count($folders) + count($allClasses) ?></span></div>
        <nav class="class-tree" aria-label="Папки и учебные классы">
            <?php foreach ($folders as $folder): $folderClasses = $classesByFolder[(int) $folder['id']] ?? []; $folderActive = (int) $folder['id'] === (int) $selectedFolderId; ?>
                <section class="class-tree__branch <?= $folderActive ? 'is-active' : '' ?>">
                    <a class="class-tree__folder <?= $folderActive && !$selectedClass ? 'is-active' : '' ?>" href="<?= e(url('/education/classes?folder_id=' . $folder['id'])) ?>">
                        <span class="folder-mark"><?= icon('folder', 19) ?></span>
                        <span><strong><?= e($folder['name']) ?></strong><small><?= (int) $folder['class_count'] ?> классов · <?= (int) $folder['student_count'] ?> курсантов</small></span>
                        <?= icon('chevron-right', 15) ?>
                    </a>
                    <div class="class-tree__classes">
                        <?php foreach ($folderClasses as $treeClass): ?>
                            <a class="class-tree__class <?= (int) $treeClass['id'] === (int) $selectedClassId ? 'is-active' : '' ?>" href="<?= e(url('/education/classes?folder_id=' . $folder['id'] . '&class_id=' . $treeClass['id'])) ?>">
                                <span class="class-tree__line" aria-hidden="true"></span>
                                <span class="class-tree__class-icon"><?= icon('users', 15) ?></span>
                                <span><strong><?= e($treeClass['group_number']) ?></strong><small><?= e($treeClass['name']) ?> · <?= (int) $treeClass['student_count'] ?> курс.</small></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$folderClasses): ?><span class="class-tree__empty">Классов пока нет</span><?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>
        <?php if ($currentUser->canManageEducation()): ?><form class="catalog-add-form" method="post" action="<?= e(url('/education/class-folders')) ?>"><?= csrf_field() ?><input name="name" required maxlength="120" placeholder="Новая папка"><input type="hidden" name="status" value="active"><button class="icon-button" type="submit" aria-label="Добавить папку"><?= icon('plus', 17) ?></button></form><?php endif; ?>
    </aside>

    <main class="catalog-content">
        <?php if ($selectedClass): ?>
            <section class="folder-heading class-heading panel reveal">
                <span class="folder-heading__icon class-heading__icon"><?= icon('users', 27) ?></span>
                <div><p class="page-eyebrow"><?= e($selectedClass['folder_name']) ?> · группа <?= e($selectedClass['group_number']) ?></p><h2><?= e($selectedClass['name']) ?></h2><p><?= e($selectedClass['study_program_name'] ?: $selectedClass['study_program']) ?> · <?= e($selectedClass['teacher_name']) ?> · <?= count($selectedClass['students']) ?> курсантов</p></div>
                <div class="class-heading__actions">
                    <a class="button button--secondary" href="<?= e(url('/education/classes/' . $selectedClass['id'])) ?>"><?= icon('settings', 17) ?> Карточка класса</a>
                    <?php if ($currentUser->canManageEducation()): ?><button class="button button--primary" type="button" data-class-student-create><?= icon('plus', 18) ?> Добавить курсанта</button><?php endif; ?>
                </div>
            </section>

            <?php if ($currentUser->canManageEducation() && $availableStudents): ?>
                <details class="panel stage-form-panel class-existing-student reveal">
                    <summary><span><?= icon('users', 18) ?></span><strong>Зачислить существующего курсанта</strong><small>Для курсантов, у которых уже есть учётная запись</small></summary>
                    <form method="post" action="<?= e(url('/education/classes/' . $selectedClass['id'] . '/students')) ?>" class="stage-form inline-enroll">
                        <?= csrf_field() ?>
                        <select name="student_id" required><option value="">Выберите курсанта</option><?php foreach ($availableStudents as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($studentName($student)) ?></option><?php endforeach; ?></select>
                        <?= date_picker('joined_on', date('Y-m-d'), false, true, 'Дата зачисления') ?>
                        <button class="button button--secondary" type="submit">Зачислить</button>
                    </form>
                </details>
            <?php endif; ?>

            <section class="panel folder-class class-students-panel reveal">
                <div class="panel__header"><div><p class="page-eyebrow">Состав класса</p><h3>Курсанты</h3></div><span class="catalog-count"><?= count($selectedClass['students']) ?></span></div>
                <div class="data-table-wrap"><table class="data-table stage-table class-roster-table"><thead><tr><th>Курсант</th><th>Контрольные точки</th><th>Балансы</th><th>Расчёты</th><th></th></tr></thead><tbody>
                    <?php foreach ($selectedClass['students'] as $student): $studentId = (int) $student['id']; ?><tr>
                        <td><div class="table-person"><span class="avatar avatar--soft avatar--sm"><?= e(initials($student['first_name'], $student['last_name'])) ?></span><span><strong><?= e($studentName($student)) ?></strong><small>@<?= e($student['login']) ?> · <?= e($student['phone'] ?: 'телефон не указан') ?></small></span></div></td>
                        <td><div class="checkpoint-strip"><?php foreach ($checkpoints as $checkpoint): $done = !empty($checkpointStates[$studentId][(int) $checkpoint['id']]['is_completed']); ?><form method="post" action="<?= e(url('/education/students/' . $studentId . '/checkpoints/' . $checkpoint['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="completed" value="<?= $done ? '0' : '1' ?>"><input type="hidden" name="class_id" value="<?= (int) $selectedClass['id'] ?>"><button class="checkpoint-chip <?= $done ? 'is-complete' : '' ?>" type="submit" title="<?= e($checkpoint['name']) ?> · <?= $done ? 'снять отметку' : 'отметить пройденной' ?>"><?= e($checkpoint['code']) ?></button></form><?php endforeach; ?><?php if (!$checkpoints): ?><span class="table-subline">Не настроены</span><?php endif; ?></div></td>
                        <td><div class="balance-strip"><?php foreach ($balanceTypes as $type): $amount = (float) ($balanceValues[$studentId][(int) $type['id']] ?? 0); ?><span class="balance-chip" title="<?= e($type['name']) ?>"><b style="--chip-color:<?= e($type['color']) ?>"><?= e($type['code']) ?></b><span><?= e($formatBalance($amount, $type['unit'])) ?></span></span><?php endforeach; ?><?php if (!$balanceTypes): ?><span class="table-subline">Не настроены</span><?php endif; ?></div></td>
                        <td><strong><?= e(number_format((float) $student['paid_total'], 0, ',', ' ')) ?> ₽</strong><small class="table-subline">из <?= e(number_format((float) $student['sold_total'], 0, ',', ' ')) ?> ₽</small></td>
                        <td><a class="table-action" href="<?= e(url('/education/students/' . $studentId)) ?>" aria-label="Открыть карточку"><?= icon('chevron-right', 17) ?></a></td>
                    </tr><?php endforeach; ?>
                    <?php if (!$selectedClass['students']): ?><tr><td colspan="5"><div class="empty-state empty-state--table"><span><?= icon('users', 27) ?></span><h3>В классе пока нет курсантов</h3><p><?= $currentUser->canManageEducation() ? 'Добавьте нового курсанта или зачислите существующего.' : 'Состав класса ещё не сформирован.' ?></p></div></td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        <?php else: ?>
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
                    <article class="panel folder-class class-list-item reveal">
                        <header class="folder-class__header"><div><p class="page-eyebrow">Группа <?= e($class['group_number']) ?></p><h3><?= e($class['name']) ?></h3><p><?= e($class['study_program_name'] ?: $class['study_program']) ?> · <?= e($class['teacher_name']) ?></p></div><div><span class="status-pill status-pill--<?= $class['status'] === 'active' ? 'active' : 'blocked' ?>"><i></i><?= e($statuses[$class['status']] ?? $class['status']) ?></span><a class="button button--primary" href="<?= e(url('/education/classes?folder_id=' . $class['folder_id'] . '&class_id=' . $class['id'])) ?>">Показать курсантов</a></div></header>
                        <div class="class-list-item__meta"><span><?= icon('users', 16) ?> <?= (int) $class['student_count'] ?> курсантов</span><span><?= icon('calendar', 16) ?> <?= (int) $class['lesson_count'] ?> занятий</span><span><?= icon('clock', 16) ?> <?= $class['next_lesson_at'] ? e(format_date($class['next_lesson_at'], 'd.m.Y H:i')) : 'Ближайшее занятие не назначено' ?></span></div>
                        <?php if ($class['students']): ?><details class="class-list-preview"><summary>Быстрый просмотр состава <span><?= count($class['students']) ?></span></summary><div class="data-table-wrap"><table class="data-table stage-table class-roster-table"><thead><tr><th>Курсант</th><th>Контрольные точки</th><th>Балансы</th><th></th></tr></thead><tbody><?php foreach ($class['students'] as $student): $studentId = (int) $student['id']; ?><tr><td><strong><?= e($studentName($student)) ?></strong><small class="table-subline">@<?= e($student['login']) ?></small></td><td><div class="checkpoint-strip"><?php foreach ($checkpoints as $checkpoint): $done = !empty($checkpointStates[$studentId][(int) $checkpoint['id']]['is_completed']); ?><span class="checkpoint-chip <?= $done ? 'is-complete' : '' ?>" title="<?= e($checkpoint['name']) ?>"><?= e($checkpoint['code']) ?></span><?php endforeach; ?></div></td><td><div class="balance-strip"><?php foreach ($balanceTypes as $type): $amount = (float) ($balanceValues[$studentId][(int) $type['id']] ?? 0); ?><span class="balance-chip" title="<?= e($type['name']) ?>"><b style="--chip-color:<?= e($type['color']) ?>"><?= e($type['code']) ?></b><span><?= e($formatBalance($amount, $type['unit'])) ?></span></span><?php endforeach; ?></div></td><td><a class="table-action" href="<?= e(url('/education/students/' . $studentId)) ?>" aria-label="Открыть карточку"><?= icon('chevron-right', 17) ?></a></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$classes): ?><div class="panel empty-state"><span><?= icon('folder', 28) ?></span><h3>В папке пока нет классов</h3><p><?= $currentUser->canManageEducation() ? 'Создайте учебный класс и выберите эту папку.' : 'Назначенные вам классы здесь не найдены.' ?></p></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php if ($selectedClass && $currentUser->canManageEducation()): ?>
<dialog class="modal student-create-modal" id="classStudentModal">
    <form class="modal__surface" id="classStudentForm" method="post" action="<?= e(url('/education/classes/' . $selectedClass['id'] . '/students/create')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="modal__header">
            <div><p class="page-eyebrow">Новый курсант · группа <?= e($selectedClass['group_number']) ?></p><h2>Добавить курсанта в класс</h2><p>Заполните основные данные, оформите договор и при необходимости добавьте фотографию.</p></div>
            <button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button>
        </div>
        <div class="modal__body student-create-body">
            <div class="student-create-layout">
                <div class="student-create-fields">
                    <section class="student-create-section"><div class="student-create-section__heading"><span>1</span><div><h3>Основные данные</h3><p>Личные и контактные данные курсанта</p></div></div><div class="form-grid">
                        <label class="field"><span class="field__label">Фамилия <b>*</b></span><span class="field__control"><input name="last_name" required maxlength="80" autocomplete="family-name"></span><small class="field__error" data-class-student-error="last_name"></small></label>
                        <label class="field"><span class="field__label">Имя <b>*</b></span><span class="field__control"><input name="first_name" required maxlength="80" autocomplete="given-name"></span><small class="field__error" data-class-student-error="first_name"></small></label>
                        <label class="field"><span class="field__label">Отчество</span><span class="field__control"><input name="middle_name" maxlength="80" autocomplete="additional-name"></span></label>
                        <label class="field"><span class="field__label">Пол</span><span class="field__control"><select name="gender"><option value="">Не указан</option><option value="male">Мужской</option><option value="female">Женский</option></select></span></label>
                        <label class="field"><span class="field__label">Дата рождения</span><?= date_picker('birth_date', '', false, false, 'Дата рождения') ?></label>
                        <label class="field"><span class="field__label">Гражданство</span><span class="field__control"><input name="citizenship" maxlength="120" value="Россия"></span></label>
                        <label class="field"><span class="field__label">Регистрационный номер</span><span class="field__control"><input name="registration_number" maxlength="80"></span></label>
                        <label class="field"><span class="field__label">Коробка передач</span><span class="field__control"><select name="gearbox"><option value="">Не выбрана</option><option value="manual">МКПП</option><option value="automatic">АКПП</option></select></span></label>
                        <label class="field form-grid__wide"><span class="field__label">Место рождения</span><span class="field__control"><input name="birth_place" maxlength="190"></span></label>
                        <label class="field"><span class="field__label">Телефон</span><span class="field__control"><input name="phone" type="tel" maxlength="18" autocomplete="tel"></span><small class="field__error" data-class-student-error="phone"></small></label>
                        <label class="field"><span class="field__label">Email</span><span class="field__control"><input name="email" type="email" maxlength="190" autocomplete="email"></span><small class="field__error" data-class-student-error="email"></small></label>
                        <label class="field form-grid__wide"><span class="field__label">Примечания</span><span class="field__control"><textarea name="notes" rows="3" maxlength="5000" placeholder="Дополнительная информация о курсанте"></textarea></span></label>
                    </div></section>
                    <section class="student-create-section"><div class="student-create-section__heading"><span>2</span><div><h3>Обучение и договор</h3><p>Даты, договор и данные для входа в систему</p></div></div><div class="form-grid">
                        <label class="field"><span class="field__label">Дата зачисления <b>*</b></span><?= date_picker('joined_on', date('Y-m-d'), false, true, 'Дата зачисления') ?></label>
                        <label class="field"><span class="field__label">Окончание обучения</span><?= date_picker('training_end_on', '', false, false, 'Окончание обучения') ?></label>
                        <label class="field"><span class="field__label">Номер договора</span><span class="field__control"><input name="contract_number" maxlength="80" placeholder="Будет создан автоматически"></span><small class="field__error" data-class-student-error="contract_number"></small></label>
                        <label class="field"><span class="field__label">Дата договора <b>*</b></span><?= date_picker('signed_on', date('Y-m-d'), false, true, 'Дата договора') ?></label>
                        <label class="field form-grid__wide"><span class="field__label">Стоимость обучения</span><span class="field__control"><input type="number" name="contract_amount" min="0" step="0.01" placeholder="0,00"></span></label>
                        <label class="field"><span class="field__label">Логин <b>*</b></span><span class="field__control"><input name="login" required maxlength="80" autocomplete="off"></span><small class="field__error" data-class-student-error="login"></small></label>
                        <label class="field"><span class="field__label">Срок доступа, месяцев</span><span class="field__control"><input type="number" name="access_months" min="1" max="120" value="6" inputmode="numeric"></span><small class="field__error" data-class-student-error="access_months"></small></label>
                        <label class="field form-grid__wide"><span class="field__label">Временный пароль <b>*</b></span><span class="field__control field__control--with-button"><input name="password" id="classStudentPassword" required minlength="10" autocomplete="new-password"><button class="field__text-action" type="button" data-class-student-password>Сгенерировать</button></span><small class="field__hint">Передайте пароль курсанту безопасным способом. При первом входе его потребуется заменить.</small><small class="field__error" data-class-student-error="password"></small></label>
                    </div></section>
                </div>
                <aside class="student-create-photo-panel">
                    <p class="page-eyebrow">Фотография</p>
                    <div class="student-create-photo-preview"><img src="" alt="Предпросмотр фотографии" data-class-student-photo-preview hidden><span data-class-student-photo-placeholder><?= icon('user', 40) ?></span></div>
                    <label class="button button--secondary button--wide student-create-photo-button"><?= icon('upload', 17) ?> Выбрать фото<input class="sr-only" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-class-student-photo></label>
                    <button class="button button--ghost button--wide is-hidden" type="button" data-class-student-photo-clear>Убрать фото</button>
                    <small>JPG, PNG или WEBP · до 5 МБ</small>
                </aside>
            </div>
            <div class="form-alert form-alert--error is-hidden" id="classStudentFormError" role="alert"></div>
        </div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit" id="classStudentSubmit">Создать и добавить в класс</button></div>
    </form>
</dialog>
<?php endif; ?>
