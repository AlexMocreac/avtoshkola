<section class="section-heading reveal">
    <div><h2>Пользователи системы</h2><p>Создавайте учётные записи, назначайте роли и управляйте доступом.</p></div>
    <button class="button button--primary" type="button" data-user-create><?= icon('plus', 18) ?> Добавить пользователя</button>
</section>

<section class="panel users-panel reveal">
    <form class="filter-bar" method="get" action="<?= e(url('/users')) ?>">
        <label class="search-field">
            <?= icon('search', 18) ?>
            <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="ФИО, логин, телефон или email">
        </label>
        <label class="compact-select">
            <span>Роль</span>
            <select name="role" onchange="this.form.submit()">
                <option value="">Все роли</option>
                <?php foreach ($roles as $key => $title): ?><option value="<?= e($key) ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>><?= e($title) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="compact-select">
            <span>Статус</span>
            <select name="status" onchange="this.form.submit()">
                <option value="">Все статусы</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Активные</option>
                <option value="blocked" <?= $filters['status'] === 'blocked' ? 'selected' : '' ?>>Заблокированные</option>
            </select>
        </label>
        <button class="button button--secondary filter-submit" type="submit">Найти</button>
        <?php if (array_filter($filters)): ?><a class="filter-reset" href="<?= e(url('/users')) ?>">Сбросить</a><?php endif; ?>
    </form>

    <div class="table-summary"><span>Найдено: <strong><?= count($users) ?></strong></span><small>Управление всеми ролями первого этапа</small></div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Пользователь</th><th>Контакты</th><th>Роль</th><th>Статус</th><th>Последний вход</th><th><span class="sr-only">Действия</span></th></tr></thead>
            <tbody>
            <?php foreach ($users as $user):
                $userJson = json_encode([
                    'id' => $user->id,
                    'login' => $user->login,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'first_name' => $user->firstName,
                    'last_name' => $user->lastName,
                    'middle_name' => $user->middleName,
                    'role' => $user->role,
                    'full_name' => $user->fullName(),
                    'status' => $user->status,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ?>
                <tr data-user-row data-user="<?= e($userJson) ?>">
                    <td data-label="Пользователь">
                        <div class="table-person"><span class="avatar avatar--soft avatar--sm"><?= e(initials($user->firstName, $user->lastName)) ?></span><span><strong><?= e($user->fullName()) ?></strong><small>@<?= e($user->login) ?></small></span></div>
                    </td>
                    <td data-label="Контакты"><div class="contact-stack"><span><?= e($user->phone ?: 'Телефон не указан') ?></span><small><?= e($user->email ?: 'Email не указан') ?></small></div></td>
                    <td data-label="Роль"><span class="role-chip role-chip--<?= e($user->role) ?>"><?= e($user->roleTitle()) ?></span></td>
                    <td data-label="Статус"><span class="status-pill status-pill--<?= e($user->status) ?>"><i></i><?= $user->status === 'active' ? 'Активен' : 'Заблокирован' ?></span></td>
                    <td data-label="Последний вход"><div class="date-stack"><span><?= e(format_date($user->lastLoginAt)) ?></span><small>Создан <?= e(format_date($user->createdAt, 'd.m.Y')) ?></small></div></td>
                    <td class="table-actions" data-label="Действия">
                        <button class="table-action" type="button" data-user-edit title="Редактировать" aria-label="Редактировать"><?= icon('edit', 17) ?></button>
                        <button class="table-action" type="button" data-user-reset title="Новый пароль" aria-label="Новый пароль"><?= icon('key', 17) ?></button>
                        <?php if ($user->id !== $currentUser->id): ?>
                            <button class="table-action <?= $user->status === 'blocked' ? 'table-action--success' : '' ?>" type="button" data-user-status title="<?= $user->status === 'blocked' ? 'Разблокировать' : 'Заблокировать' ?>" aria-label="<?= $user->status === 'blocked' ? 'Разблокировать' : 'Заблокировать' ?>"><?= icon($user->status === 'blocked' ? 'unlock' : 'lock', 17) ?></button>
                            <button class="table-action table-action--danger" type="button" data-user-delete title="Удалить" aria-label="Удалить"><?= icon('trash', 17) ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="6"><div class="empty-state empty-state--table"><span><?= icon('users', 27) ?></span><h3>Ничего не найдено</h3><p>Измените параметры поиска или добавьте нового пользователя.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="modal" id="userModal">
    <form class="modal__surface" id="userForm" method="post" novalidate>
        <div class="modal__header">
            <div><p class="page-eyebrow" id="userModalEyebrow">Новая учётная запись</p><h2 id="userModalTitle">Добавить пользователя</h2><p id="userModalSubtitle">Доступ можно заблокировать в любой момент.</p></div>
            <button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button>
        </div>
        <div class="modal__body">
            <div class="form-grid">
                <label class="field"><span class="field__label">Фамилия <b>*</b></span><span class="field__control"><input name="last_name" required maxlength="80"></span><small class="field__error" data-error-for="last_name"></small></label>
                <label class="field"><span class="field__label">Имя <b>*</b></span><span class="field__control"><input name="first_name" required maxlength="80"></span><small class="field__error" data-error-for="first_name"></small></label>
                <label class="field"><span class="field__label">Отчество</span><span class="field__control"><input name="middle_name" maxlength="80"></span></label>
                <label class="field"><span class="field__label">Роль <b>*</b></span><span class="field__control"><select name="role" required><?php foreach ($roles as $key => $title): ?><option value="<?= e($key) ?>"><?= e($title) ?></option><?php endforeach; ?></select></span><small class="field__error" data-error-for="role"></small></label>
                <label class="field"><span class="field__label">Логин <b>*</b></span><span class="field__control"><input name="login" required maxlength="80" autocomplete="off" placeholder="ivan.ivanov"></span><small class="field__error" data-error-for="login"></small></label>
                <label class="field"><span class="field__label">Телефон</span><span class="field__control"><input name="phone" maxlength="32" inputmode="tel" placeholder="+7 (900) 000-00-00"></span><small class="field__error" data-error-for="phone"></small></label>
                <label class="field form-grid__wide"><span class="field__label">Email</span><span class="field__control"><input type="email" name="email" maxlength="190" placeholder="name@example.ru"></span><small class="field__error" data-error-for="email"></small></label>
                <label class="field form-grid__wide" id="passwordField"><span class="field__label">Временный пароль <b>*</b></span><span class="field__control field__control--with-button"><input id="createPassword" name="password" minlength="10" autocomplete="new-password"><button class="field__text-action" type="button" id="generatePassword">Сгенерировать</button></span><small class="field__hint">При первом входе пользователь создаст постоянный пароль.</small><small class="field__error" data-error-for="password"></small></label>
            </div>
            <div class="form-alert form-alert--error is-hidden" id="userFormError" role="alert"></div>
        </div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit" id="userFormSubmit">Создать пользователя</button></div>
    </form>
</dialog>

<dialog class="modal modal--compact" id="confirmModal">
    <div class="modal__surface">
        <div class="confirm-icon" id="confirmIcon"><?= icon('lock', 26) ?></div>
        <div class="modal__body modal__body--center"><h2 id="confirmTitle">Подтвердите действие</h2><p id="confirmText"></p></div>
        <div class="modal__footer modal__footer--center"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--danger" type="button" id="confirmAction">Подтвердить</button></div>
    </div>
</dialog>

<dialog class="modal modal--compact" id="passwordResultModal">
    <div class="modal__surface">
        <div class="modal__header"><div><p class="page-eyebrow">Восстановление доступа</p><h2>Новый временный пароль</h2></div><button class="icon-button modal__close" type="button" data-dialog-close><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><p class="modal-copy">Передайте пароль пользователю безопасным способом. После закрытия окна он больше не будет показан.</p><div class="password-result"><code id="passwordResult"></code><button class="icon-button" type="button" id="copyPassword" title="Копировать"><?= icon('copy', 18) ?></button></div><p class="password-result__user" id="passwordResultUser"></p></div>
        <div class="modal__footer"><button class="button button--primary button--wide" type="button" data-dialog-close>Готово</button></div>
    </div>
</dialog>

