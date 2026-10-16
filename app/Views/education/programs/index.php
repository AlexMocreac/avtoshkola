<section class="section-heading reveal">
    <div><h2>Учебные программы</h2><p>Организуйте программы по папкам и выбирайте готовую программу при создании класса.</p></div>
    <div class="page-actions"><a class="button button--secondary" href="<?= e(url('/education/checkpoints')) ?>"><?= icon('check-square', 17) ?> Контрольные точки</a><a class="button button--secondary" href="<?= e(url('/education/balance-types')) ?>"><?= icon('activity', 17) ?> Типы балансов</a></div>
</section>

<div class="catalog-layout">
    <aside class="panel catalog-folders reveal">
        <div class="panel__header"><div><p class="page-eyebrow">Структура</p><h3>Папки</h3></div><span class="catalog-count"><?= count($folders) ?></span></div>
        <nav><?php foreach ($folders as $folder): ?><a href="#program-folder-<?= (int) $folder['id'] ?>"><span class="folder-mark"><?= icon('folder', 19) ?></span><span><strong><?= e($folder['name']) ?></strong><small><?= (int) $folder['item_count'] ?> программ</small></span><?= icon('chevron-right', 15) ?></a><?php endforeach; ?></nav>
        <form class="catalog-add-form" method="post" action="<?= e(url('/education/program-folders')) ?>"><?= csrf_field() ?><input name="name" required maxlength="120" placeholder="Новая папка"><input type="hidden" name="status" value="active"><button class="icon-button" type="submit" aria-label="Добавить папку"><?= icon('plus', 17) ?></button></form>
    </aside>

    <main class="catalog-content">
        <details class="panel stage-form-panel reveal" <?= !$folders ? 'open' : '' ?>>
            <summary><span><?= icon('plus', 18) ?></span><strong>Добавить учебную программу</strong><small>Новый элемент в выбранной папке</small></summary>
            <form method="post" action="<?= e(url('/education/programs')) ?>" class="stage-form">
                <?= csrf_field() ?>
                <div class="form-grid form-grid--three">
                    <label class="field"><span class="field__label">Папка <b>*</b></span><span class="field__control"><select name="folder_id" required><option value="">Выберите</option><?php foreach ($folders as $folder): if ($folder['status'] !== 'active') continue; ?><option value="<?= (int) $folder['id'] ?>"><?= e($folder['name']) ?></option><?php endforeach; ?></select></span></label>
                    <label class="field"><span class="field__label">Название <b>*</b></span><span class="field__control"><input name="name" required maxlength="190" placeholder="Категория B"></span></label>
                    <label class="field"><span class="field__label">Код</span><span class="field__control"><input name="code" maxlength="40" placeholder="B-BASE"></span></label>
                    <label class="field form-grid__wide"><span class="field__label">Описание</span><span class="field__control"><textarea name="description" rows="3" placeholder="Содержание и назначение программы"></textarea></span></label>
                </div>
                <input type="hidden" name="status" value="active"><div class="stage-form__actions"><button class="button button--primary" type="submit">Добавить программу</button></div>
            </form>
        </details>

        <?php foreach ($folders as $folder): ?>
        <section class="panel catalog-folder-panel reveal" id="program-folder-<?= (int) $folder['id'] ?>">
            <div class="panel__header"><div><p class="page-eyebrow">Папка</p><h3><?= e($folder['name']) ?></h3></div><span class="status-pill status-pill--<?= $folder['status'] === 'active' ? 'active' : 'blocked' ?>"><i></i><?= $folder['status'] === 'active' ? 'Активна' : 'Архив' ?></span></div>
            <div class="catalog-items">
                <?php foreach ($folder['programs'] as $program): ?>
                <details class="catalog-item"><summary><span class="catalog-item__icon"><?= icon('book-open', 19) ?></span><span><strong><?= e($program['name']) ?></strong><small><?= e($program['code'] ?: 'Без кода') ?><?= $program['description'] ? ' · ' . e($program['description']) : '' ?></small></span><span class="status-pill status-pill--<?= $program['status'] === 'active' ? 'active' : 'blocked' ?>"><i></i><?= $program['status'] === 'active' ? 'Используется' : 'Скрыта' ?></span><?= icon('chevron-down', 16) ?></summary>
                    <form method="post" action="<?= e(url('/education/programs/' . $program['id'] . '/update')) ?>" class="catalog-edit-form"><?= csrf_field() ?><label>Папка<select name="folder_id"><?php foreach ($folders as $target): ?><option value="<?= (int) $target['id'] ?>" <?= (int) $target['id'] === (int) $program['folder_id'] ? 'selected' : '' ?>><?= e($target['name']) ?></option><?php endforeach; ?></select></label><label>Название<input name="name" value="<?= e($program['name']) ?>" required></label><label>Код<input name="code" value="<?= e($program['code']) ?>"></label><label>Порядок<input type="number" name="sort_order" value="<?= (int) $program['sort_order'] ?>"></label><label>Статус<select name="status"><option value="active" <?= $program['status'] === 'active' ? 'selected' : '' ?>>Используется</option><option value="archived" <?= $program['status'] === 'archived' ? 'selected' : '' ?>>Скрыта</option></select></label><label class="catalog-edit-form__wide">Описание<textarea name="description" rows="2"><?= e($program['description']) ?></textarea></label><button class="button button--secondary" type="submit">Сохранить</button></form>
                </details>
                <?php endforeach; ?>
                <?php if (!$folder['programs']): ?><div class="empty-inline">В этой папке пока нет программ.</div><?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
        <?php if (!$folders): ?><div class="panel empty-state"><span><?= icon('folder', 28) ?></span><h3>Создайте первую папку</h3><p>После этого в неё можно будет добавить учебные программы.</p></div><?php endif; ?>
    </main>
</div>
