<section class="section-heading reveal">
    <div><h2>Контрольные точки обучения</h2><p>Единый порядок зачётов и экзаменов. Преподаватели отмечают прохождение в классе или карточке курсанта.</p></div>
    <div class="page-actions"><a class="button button--secondary" href="<?= e(url('/education/programs')) ?>"><?= icon('book-open', 17) ?> Учебные программы</a><a class="button button--secondary" href="<?= e(url('/education/balance-types')) ?>"><?= icon('activity', 17) ?> Типы балансов</a></div>
</section>

<?php
$selectedFolder = null;
foreach ($folders as $folder) if ((int) $folder['id'] === $selectedFolderId) $selectedFolder = $folder;
$visibleCheckpoints = array_values(array_filter($checkpoints, static fn (array $type): bool => (int) ($type['folder_id'] ?? 0) === $selectedFolderId));
?>
<div class="catalog-layout class-browser balance-browser" data-class-browser data-move-label-prefix="Контрольная точка">
    <aside class="panel catalog-folders class-navigator reveal">
        <div class="panel__header"><div><p class="page-eyebrow">Навигатор</p><h3>Папки и контрольные точки</h3></div><span class="catalog-count"><?= count($folders) + count($checkpoints) ?></span></div>
        <p class="class-drag-hint">Перетащите точку или папку в нужную папку, либо используйте меню ⋮</p>
        <div class="class-tree__root-drop" data-drop-root>На верхний уровень</div>
        <nav class="class-tree" data-collapsible-tree="checkpoints" aria-label="Папки и контрольные точки">
            <?php
            $folderChildren = [];
            foreach ($folders as $treeFolder) $folderChildren[(int) ($treeFolder['parent_id'] ?? 0)][] = $treeFolder;
            $renderFolders = static function (int $parentId) use (&$renderFolders, $folderChildren, $checkpoints, $selectedFolderId): void {
                foreach ($folderChildren[$parentId] ?? [] as $folder):
            ?>
                <section class="class-tree__branch <?= (int) $folder['id'] === $selectedFolderId ? 'is-active' : '' ?>" data-drop-folder="<?= (int) $folder['id'] ?>" data-parent-folder="<?= (int) ($folder['parent_id'] ?? 0) ?>" style="--folder-depth: <?= (int) $folder['depth'] ?>">
                    <div class="class-tree__folder-row">
                        <a class="class-tree__folder <?= (int) $folder['id'] === $selectedFolderId ? 'is-active' : '' ?>" draggable="true" data-drag-folder="<?= (int) $folder['id'] ?>" href="<?= e(url('/education/checkpoints?folder_id=' . $folder['id'])) ?>"><span class="folder-mark"><?= icon('folder', 19) ?></span><span><strong><?= e($folder['name']) ?></strong><small><?= (int) $folder['item_count'] ?> точек</small></span></a>
                        <button class="class-tree__toggle" type="button" data-folder-toggle aria-expanded="true" aria-label="Свернуть папку <?= e($folder['name']) ?>" title="Свернуть папку"><?= icon('chevron-right', 15) ?></button>
                        <button type="button" class="class-move-button" data-move-folder-id="<?= (int) $folder['id'] ?>" data-folder-name="<?= e($folder['name']) ?>" aria-label="Переместить папку <?= e($folder['name']) ?>" title="Переместить папку">⋮</button>
                    </div>
                    <?php if (!empty($folderChildren[(int) $folder['id']])): ?><div class="class-tree__children"><?php $renderFolders((int) $folder['id']); ?></div><?php endif; ?>
                    <div class="class-tree__classes">
                        <?php foreach ($checkpoints as $treeCheckpoint): if ((int) ($treeCheckpoint['folder_id'] ?? 0) !== (int) $folder['id']) continue; ?>
                            <div class="class-tree__class-row"><a class="class-tree__class" draggable="true" data-drag-class="<?= (int) $treeCheckpoint['id'] ?>" data-source-folder="<?= (int) $folder['id'] ?>" href="<?= e(url('/education/checkpoints?folder_id=' . $folder['id'] . '#checkpoint-row-' . $treeCheckpoint['id'])) ?>" title="<?= e($treeCheckpoint['name'] . ' · ' . $treeCheckpoint['code']) ?>"><span class="class-tree__line" aria-hidden="true"></span><span class="checkpoint-chip"><?= e($treeCheckpoint['code']) ?></span><span><strong><?= e($treeCheckpoint['name']) ?></strong></span></a><button type="button" class="class-move-button" data-move-class="<?= (int) $treeCheckpoint['id'] ?>" data-source-folder="<?= (int) $folder['id'] ?>" data-class-name="<?= e($treeCheckpoint['name']) ?>" aria-label="Переместить контрольную точку <?= e($treeCheckpoint['name']) ?>" title="Переместить контрольную точку">⋮</button></div>
                        <?php endforeach; ?>
                        <?php if (!(int) $folder['item_count'] && empty($folderChildren[(int) $folder['id']])): ?><span class="class-tree__empty">Точек пока нет</span><?php endif; ?>
                    </div>
                </section>
            <?php endforeach; };
            $renderFolders(0);
            ?>
        </nav>
        <div class="balance-folder-create"><button class="button button--secondary button--wide" type="button" data-dialog-open="checkpointFolderCreateDialog" aria-haspopup="dialog" aria-controls="checkpointFolderCreateDialog"><?= icon('plus', 17) ?> Новая папка</button></div>
    </aside>
    <main class="catalog-content">
        <section class="folder-heading panel reveal"><span class="folder-heading__icon"><?= icon('folder', 28) ?></span><div><p class="page-eyebrow">Выбранная папка</p><h2><?= e($selectedFolder['name'] ?? 'Контрольные точки') ?></h2><p><?= count($visibleCheckpoints) ?> контрольных точек</p></div></section>
<details class="panel stage-form-panel reveal" <?= !$visibleCheckpoints ? 'open' : '' ?>>
    <summary><span><?= icon('plus', 18) ?></span><strong>Добавить контрольную точку</strong><small>Название, короткий код и порядок отображения</small></summary>
    <form method="post" action="<?= e(url('/education/checkpoints')) ?>" class="stage-form"><div class="form-grid form-grid--three"><?= csrf_field() ?><label class="field"><span class="field__label">Папка</span><span class="field__control"><select name="folder_id" required><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>" <?= (int) $folder['id'] === $selectedFolderId ? 'selected' : '' ?>><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label><label class="field"><span class="field__label">Полное название <b>*</b></span><span class="field__control"><input name="name" required maxlength="190" placeholder="Теоретический зачёт №1"></span></label><label class="field"><span class="field__label">Код <b>*</b></span><span class="field__control"><input name="code" required maxlength="24" placeholder="Зт1"></span></label><label class="field"><span class="field__label">Порядок</span><span class="field__control"><input type="number" name="sort_order" value="0"></span></label></div><input type="hidden" name="status" value="active"><div class="stage-form__actions"><button class="button button--primary" type="submit">Добавить точку</button></div></form>
</details>

<section class="panel reveal"><div class="data-table-wrap"><table class="data-table stage-table catalog-table"><thead><tr><th>Название</th><th>Код</th><th>Порядок</th><th>Статус</th><th>Папка</th><th><span class="sr-only">Действия</span></th></tr></thead><tbody>
<?php foreach ($visibleCheckpoints as $checkpoint): ?>
<tr id="checkpoint-row-<?= (int) $checkpoint['id'] ?>"><td><strong><?= e($checkpoint['name']) ?></strong></td><td><?= e($checkpoint['code']) ?></td><td><?= (int) $checkpoint['sort_order'] ?></td><td><span class="status-pill status-pill--<?= $checkpoint['status'] === 'active' ? 'active' : 'blocked' ?>"><i></i><?= $checkpoint['status'] === 'active' ? 'Используется' : 'Скрыта' ?></span></td><td><?= e($selectedFolder['name'] ?? '—') ?></td><td><?php if ($currentUser->canManageEducation()): ?><button class="table-action" type="button" data-dialog-open="checkpointEdit<?= (int) $checkpoint['id'] ?>" aria-haspopup="dialog" aria-controls="checkpointEdit<?= (int) $checkpoint['id'] ?>" aria-label="Редактировать <?= e($checkpoint['name']) ?>" title="Редактировать"><?= icon('edit', 17) ?></button><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$visibleCheckpoints): ?><tr><td colspan="6"><div class="empty-inline">Контрольных точек пока нет.</div></td></tr><?php endif; ?>
</tbody></table></div></section>
<?php if ($currentUser->canManageEducation()): foreach ($visibleCheckpoints as $checkpoint): ?>
<dialog class="modal" id="checkpointEdit<?= (int) $checkpoint['id'] ?>" aria-labelledby="checkpointEditTitle<?= (int) $checkpoint['id'] ?>"><form method="post" action="<?= e(url('/education/checkpoints/' . $checkpoint['id'] . '/update')) ?>" class="modal__surface" data-submit-loading><?= csrf_field() ?>
<div class="modal__header"><div><p class="page-eyebrow">Контрольные точки</p><h2 id="checkpointEditTitle<?= (int) $checkpoint['id'] ?>">Редактировать контрольную точку</h2><p><?= e($checkpoint['name']) ?></p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
<div class="modal__body"><div class="form-grid">
<label class="field"><span class="field__label">Название <b>*</b></span><span class="field__control"><input name="name" value="<?= e($checkpoint['name']) ?>" required minlength="2" maxlength="190"></span></label>
<label class="field"><span class="field__label">Код <b>*</b></span><span class="field__control"><input name="code" value="<?= e($checkpoint['code']) ?>" required maxlength="24"></span></label>
<label class="field"><span class="field__label">Порядок</span><span class="field__control"><input type="number" name="sort_order" value="<?= (int) $checkpoint['sort_order'] ?>"></span></label>
<label class="field"><span class="field__label">Статус</span><span class="field__control"><select name="status"><option value="active" <?= $checkpoint['status'] === 'active' ? 'selected' : '' ?>>Используется</option><option value="archived" <?= $checkpoint['status'] === 'archived' ? 'selected' : '' ?>>Скрыта</option></select></span></label>
<label class="field form-grid__wide"><span class="field__label">Папка</span><span class="field__control"><select name="folder_id" required><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>" <?= (int) $checkpoint['folder_id'] === (int) $folder['id'] ? 'selected' : '' ?>><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label>
</div></div><div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Сохранить изменения</button></div></form></dialog>
<?php endforeach; endif; ?>
</main></div>
<dialog class="modal" id="checkpointFolderCreateDialog" aria-labelledby="checkpointFolderCreateTitle">
    <form class="modal__surface" method="post" action="<?= e(url('/education/checkpoint-folders')) ?>" data-submit-loading>
        <?= csrf_field() ?>
        <div class="modal__header"><div><p class="page-eyebrow">Контрольные точки</p><h2 id="checkpointFolderCreateTitle">Новая папка</h2><p>Создайте папку для организации контрольных точек.</p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><div class="form-grid">
            <label class="field form-grid__wide"><span class="field__label">Название папки <b>*</b></span><span class="field__control"><input name="name" required minlength="2" maxlength="120" placeholder="Например, Вождение"></span></label>
            <label class="field form-grid__wide"><span class="field__label">Родительская папка</span><span class="field__control"><select name="parent_id"><option value="">Верхний уровень</option><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>" <?= (int) $folder['id'] === (int) $selectedFolderId ? 'selected' : '' ?>><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label>
        </div></div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Создать папку</button></div>
    </form>
</dialog>

<dialog class="modal" id="classMoveDialog">
    <form class="modal__surface" method="post" data-class-move-form data-action-template="<?= e(url('/education/checkpoints/__ID__/move')) ?>">
        <?= csrf_field() ?>
        <div class="modal__header"><div><p class="page-eyebrow">Контрольные точки</p><h2>Переместить контрольную точку</h2><p data-move-label></p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><label class="field"><span class="field__label">Папка назначения</span><span class="field__control"><select name="folder_id" required data-move-folder><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>"><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label></div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Переместить</button></div>
    </form>
</dialog>
<dialog class="modal" id="folderMoveDialog">
    <form class="modal__surface" method="post" data-folder-move-form data-action-template="<?= e(url('/education/checkpoint-folders/__ID__/move')) ?>">
        <?= csrf_field() ?>
        <div class="modal__header"><div><p class="page-eyebrow">Контрольные точки</p><h2>Переместить папку</h2><p data-folder-move-label></p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><label class="field"><span class="field__label">Родительская папка</span><span class="field__control"><select name="parent_id" data-folder-destination><option value="">Верхний уровень</option><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>"><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label></div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Переместить</button></div>
    </form>
</dialog>
