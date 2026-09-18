<main class="auth-shell">
    <section class="auth-showcase">
        <div class="auth-showcase__glow auth-showcase__glow--one"></div>
        <div class="auth-showcase__glow auth-showcase__glow--two"></div>
        <a class="brand brand--light" href="<?= e(url('/login')) ?>">
            <span class="brand__mark"><span>Ц</span></span>
            <span class="brand__copy"><strong>Центр</strong><small>Обучения Вождению</small></span>
        </a>

        <div class="auth-showcase__content">
            <span class="eyebrow-pill"><span></span> Единая система автошколы</span>
            <h1>Обучение под<br><em>полным контролем.</em></h1>
            <p>Курсанты, сотрудники и коммуникация — в одном защищённом рабочем пространстве.</p>
        </div>

        <div class="auth-preview" aria-hidden="true">
            <div class="auth-preview__header">
                <div><span></span><span></span><span></span></div>
                <small>Сегодня, 18 сентября</small>
            </div>
            <div class="auth-preview__body">
                <div class="auth-preview__metric"><span>Активные курсанты</span><strong>148</strong><small>+12 в этом месяце</small></div>
                <div class="auth-preview__chart">
                    <i style="height: 34%"></i><i style="height: 50%"></i><i style="height: 42%"></i><i style="height: 72%"></i><i style="height: 60%"></i><i style="height: 86%"></i><i style="height: 76%"></i>
                </div>
            </div>
        </div>
        <p class="auth-showcase__foot">Безопасный доступ · Персональные роли · Адаптивный интерфейс</p>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-card__mobile-brand">
                <span class="brand__mark"><span>Ц</span></span>
                <span><strong>Центр</strong><small>Обучения Вождению</small></span>
            </div>
            <p class="page-eyebrow">Личный кабинет</p>
            <h2>Добро пожаловать</h2>
            <p class="auth-card__intro">Введите данные, которые вы получили от сотрудника автошколы.</p>

            <?php if (!empty($error)): ?>
                <div class="form-alert form-alert--error" role="alert"><?= icon('lock', 18) ?><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/login')) ?>" class="auth-form">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="field__label">Логин или email</span>
                    <span class="field__control field__control--icon">
                        <?= icon('user', 19) ?>
                        <input type="text" name="login" value="<?= e($login ?? '') ?>" placeholder="Введите логин" autocomplete="username" required autofocus>
                    </span>
                </label>
                <label class="field">
                    <span class="field__label">Пароль</span>
                    <span class="field__control field__control--icon">
                        <?= icon('lock', 18) ?>
                        <input id="loginPassword" type="password" name="password" placeholder="Введите пароль" autocomplete="current-password" required>
                        <button class="field__action" type="button" data-password-toggle="loginPassword" aria-label="Показать пароль"><?= icon('eye', 18) ?></button>
                    </span>
                </label>
                <button class="button button--primary button--wide button--large" type="submit">
                    <span>Войти в систему</span><?= icon('chevron-right', 19) ?>
                </button>
            </form>

            <div class="auth-help">
                <span class="auth-help__icon"><?= icon('shield', 20) ?></span>
                <div><strong>Не получается войти?</strong><p>Восстановление доступа выполняет администратор автошколы.</p></div>
            </div>
        </div>
        <p class="auth-panel__footer">© <?= date('Y') ?> Центр Обучения Вождению</p>
    </section>
</main>

