<main class="password-page">
    <div class="password-page__brand">
        <span class="brand__mark"><span>Ц</span></span>
        <span><strong>Центр</strong><small>Обучения Вождению</small></span>
    </div>
    <section class="password-card">
        <div class="password-card__icon"><?= icon('key', 28) ?></div>
        <p class="page-eyebrow"><?= $forced ? 'Первый вход' : 'Безопасность' ?></p>
        <h1><?= $forced ? 'Создайте постоянный пароль' : 'Изменить пароль' ?></h1>
        <p><?= $forced ? 'Временный пароль сработал. Теперь задайте пароль, который будете знать только вы.' : 'Для защиты аккаунта подтвердите текущий пароль.' ?></p>
        <form method="post" action="<?= e(url('/change-password')) ?>" class="auth-form" data-submit-loading>
            <?= csrf_field() ?>
            <label class="field">
                <span class="field__label">Текущий пароль</span>
                <span class="field__control"><input type="password" name="current_password" autocomplete="current-password" required></span>
            </label>
            <label class="field">
                <span class="field__label">Новый пароль</span>
                <span class="field__control"><input type="password" name="password" minlength="10" autocomplete="new-password" required></span>
                <small class="field__hint">Минимум 10 символов, буквы и цифры</small>
            </label>
            <label class="field">
                <span class="field__label">Повторите новый пароль</span>
                <span class="field__control"><input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></span>
            </label>
            <button class="button button--primary button--wide button--large" type="submit">Сохранить пароль <?= icon('chevron-right', 18) ?></button>
        </form>
        <?php if (!$forced): ?><a class="text-link password-card__back" href="<?= e(url('/dashboard')) ?>"><?= icon('arrow-left', 16) ?> Вернуться в CRM</a><?php endif; ?>
    </section>
</main>
