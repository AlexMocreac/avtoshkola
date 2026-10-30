<dialog class="modal" id="userModal">
    <form class="modal__surface" id="userForm" method="post" data-user-group="<?= e($group) ?>" data-submit-loading novalidate>
        <div class="modal__header">
            <div><p class="page-eyebrow" id="userModalEyebrow">Новая учётная запись</p><h2 id="userModalTitle">Добавить <?= $group === 'students' ? 'курсанта' : 'сотрудника' ?></h2><p id="userModalSubtitle">Доступ можно заблокировать в любой момент.</p></div>
            <?php if (!empty($showSalesAction)): ?><button class="icon-button student-sales-trigger" type="button" id="studentSalesOpen" title="??????? ? ???????" aria-label="??????? ? ???????">₽</button><?php endif; ?>
            <button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button>
        </div>
        <div class="modal__body">
            <div class="form-grid">
                <label class="field"><span class="field__label">Фамилия <b>*</b></span><span class="field__control"><input name="last_name" required maxlength="80"></span><small class="field__error" data-error-for="last_name"></small></label>
                <label class="field"><span class="field__label">Имя <b>*</b></span><span class="field__control"><input name="first_name" required maxlength="80"></span><small class="field__error" data-error-for="first_name"></small></label>
                <label class="field"><span class="field__label">Отчество</span><span class="field__control"><input name="middle_name" maxlength="80"></span></label>
                <label class="field"><span class="field__label">Роль <b>*</b></span><span class="field__control"><select name="role" required><?php foreach ($assignableRoles as $key => $title): ?><option value="<?= e($key) ?>"><?= e($title) ?></option><?php endforeach; ?></select></span><small class="field__error" data-error-for="role"></small></label>
                <?php if ($group === 'students'): ?><label class="field"><span class="field__label">Срок обучения, месяцев</span><span class="field__control"><input type="number" name="access_months" min="1" max="120" inputmode="numeric" placeholder="Например, 6"></span><small class="field__hint">Пустое поле — без срока. При изменении срока отсчёт начнётся заново.</small><small class="field__error" data-error-for="access_months"></small></label><?php endif; ?>
                <?php if ($group === 'students'): ?><label class="field" id="restartAccessField"><span class="field__label">Продление доступа</span><span class="field__control field__control--checkbox"><input type="checkbox" name="restart_access" value="1"><span>Начать указанный срок заново с текущего момента</span></span></label><?php endif; ?>
                <label class="field"><span class="field__label">Логин <b>*</b></span><span class="field__control"><input name="login" required maxlength="80" autocomplete="off" placeholder="ivan.ivanov"></span><small class="field__error" data-error-for="login"></small></label>
                <label class="field"><span class="field__label">Телефон</span><span class="field__control"><input name="phone" type="tel" maxlength="18" autocomplete="tel" placeholder="+7 (900) 000-00-00"></span><small class="field__error" data-error-for="phone"></small></label>
                <label class="field form-grid__wide"><span class="field__label">Email</span><span class="field__control"><input type="email" name="email" maxlength="190" placeholder="name@example.ru"></span><small class="field__error" data-error-for="email"></small></label>
                <label class="field form-grid__wide" id="passwordField"><span class="field__label">Временный пароль <b>*</b></span><span class="field__control field__control--with-button"><input id="createPassword" name="password" minlength="10" autocomplete="new-password"><button class="field__text-action" type="button" id="generatePassword">Сгенерировать</button></span><small class="field__hint">При первом входе пользователь создаст постоянный пароль.</small><small class="field__error" data-error-for="password"></small></label>
            </div>
            <div class="form-alert form-alert--error is-hidden" id="userFormError" role="alert"></div>
        </div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit" id="userFormSubmit">Создать <?= $group === 'students' ? 'курсанта' : 'сотрудника' ?></button></div>
    </form>
</dialog>

