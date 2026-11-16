<section class="section-heading reveal">
    <div><h2>Типы балансов курсанта</h2><p>Настройте часы, денежные или количественные балансы, которые отображаются у каждого курсанта.</p></div>
    <div class="page-actions"><a class="button button--secondary" href="<?= e(url('/education/programs')) ?>"><?= icon('book-open', 17) ?> Учебные программы</a><a class="button button--secondary" href="<?= e(url('/education/checkpoints')) ?>"><?= icon('check-square', 17) ?> Контрольные точки</a></div>
</section>

<?php
$selectedFolder = null;
foreach ($folders as $folder) if ((int) $folder['id'] === $selectedFolderId) $selectedFolder = $folder;
$visibleTypes = array_values(array_filter($types, static fn (array $type): bool => (int) ($type['folder_id'] ?? 0) === $selectedFolderId));
?>
<div class="catalog-layout class-browser balance-browser" data-class-browser data-move-label-prefix="Тип баланса">
    <aside class="panel catalog-folders class-navigator reveal">
        <div class="panel__header"><div><p class="page-eyebrow">Навигатор</p><h3>Папки и типы балансов</h3></div><span class="catalog-count"><?= count($folders) + count($types) ?></span></div>
        <p class="class-drag-hint">Перетащите тип или папку в нужную папку, либо используйте меню ⋮</p>
        <div class="class-tree__root-drop" data-drop-root>На верхний уровень</div>
        <nav class="class-tree" data-collapsible-tree="balance-types" aria-label="Папки и типы балансов">
            <?php
            $folderChildren = [];
            foreach ($folders as $treeFolder) $folderChildren[(int) ($treeFolder['parent_id'] ?? 0)][] = $treeFolder;
            $renderFolders = static function (int $parentId) use (&$renderFolders, $folderChildren, $types, $selectedFolderId): void {
                foreach ($folderChildren[$parentId] ?? [] as $folder):
            ?>
                <section class="class-tree__branch <?= (int) $folder['id'] === $selectedFolderId ? 'is-active' : '' ?>" data-drop-folder="<?= (int) $folder['id'] ?>" data-parent-folder="<?= (int) ($folder['parent_id'] ?? 0) ?>" style="--folder-depth: <?= (int) $folder['depth'] ?>">
                    <div class="class-tree__folder-row">
                        <a class="class-tree__folder <?= (int) $folder['id'] === $selectedFolderId ? 'is-active' : '' ?>" draggable="true" data-drag-folder="<?= (int) $folder['id'] ?>" href="<?= e(url('/education/balance-types?folder_id=' . $folder['id'])) ?>"><span class="folder-mark"><?= icon('folder', 19) ?></span><span><strong><?= e($folder['name']) ?></strong><small><?= (int) $folder['item_count'] ?> типов</small></span></a>
                        <button class="class-tree__toggle" type="button" data-folder-toggle aria-expanded="true" aria-label="Свернуть папку <?= e($folder['name']) ?>" title="Свернуть папку"><?= icon('chevron-right', 15) ?></button>
                        <button type="button" class="class-move-button" data-move-folder-id="<?= (int) $folder['id'] ?>" data-folder-name="<?= e($folder['name']) ?>" aria-label="Переместить папку <?= e($folder['name']) ?>" title="Переместить папку">⋮</button>
                    </div>
                    <?php if (!empty($folderChildren[(int) $folder['id']])): ?><div class="class-tree__children"><?php $renderFolders((int) $folder['id']); ?></div><?php endif; ?>
                    <div class="class-tree__classes">
                        <?php foreach ($types as $treeType): if ((int) ($treeType['folder_id'] ?? 0) !== (int) $folder['id']) continue; ?>
                            <div class="class-tree__class-row"><a class="class-tree__class" draggable="true" data-drag-class="<?= (int) $treeType['id'] ?>" data-source-folder="<?= (int) $folder['id'] ?>" href="<?= e(url('/education/balance-types?folder_id=' . $folder['id'] . '#balance-type-row-' . $treeType['id'])) ?>" title="<?= e($treeType['name'] . ' · ' . $treeType['code']) ?>"><span class="class-tree__line" aria-hidden="true"></span><span class="balance-tree__code" style="--chip-color:<?= e($treeType['color']) ?>"><?= e($treeType['code']) ?></span><span><strong><?= e($treeType['name']) ?></strong></span></a><button type="button" class="class-move-button" data-move-class="<?= (int) $treeType['id'] ?>" data-source-folder="<?= (int) $folder['id'] ?>" data-class-name="<?= e($treeType['name']) ?>" aria-label="Переместить тип баланса <?= e($treeType['name']) ?>" title="Переместить тип баланса">⋮</button></div>
                        <?php endforeach; ?>
                        <?php if (!(int) $folder['item_count'] && empty($folderChildren[(int) $folder['id']])): ?><span class="class-tree__empty">Типов пока нет</span><?php endif; ?>
                    </div>
                </section>
            <?php endforeach; };
            $renderFolders(0);
            ?>
        </nav>
        <div class="balance-folder-create"><button class="button button--secondary button--wide" type="button" data-dialog-open="balanceFolderCreateDialog" aria-haspopup="dialog" aria-controls="balanceFolderCreateDialog"><?= icon('plus', 17) ?> Новая папка</button></div>
    </aside>
    <main class="catalog-content">
        <section class="folder-heading panel reveal"><span class="folder-heading__icon"><?= icon('folder', 28) ?></span><div><p class="page-eyebrow">Выбранная папка</p><h2><?= e($selectedFolder['name'] ?? 'Типы балансов') ?></h2><p><?= count($visibleTypes) ?> типов балансов</p></div></section>
<details class="panel stage-form-panel reveal" <?= !$visibleTypes ? 'open' : '' ?>>
    <summary><span><?= icon('plus', 18) ?></span><strong>Добавить тип баланса</strong><small>Код, название, единица измерения и цвет</small></summary>
    <form method="post" action="<?= e(url('/education/balance-types')) ?>" class="stage-form"><div class="form-grid form-grid--three"><?= csrf_field() ?><label class="field"><span class="field__label">Папка</span><span class="field__control"><select name="folder_id" required><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>" <?= (int) $folder['id'] === $selectedFolderId ? 'selected' : '' ?>><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label><label class="field"><span class="field__label">Название <b>*</b></span><span class="field__control"><input name="name" required maxlength="190" placeholder="Категория B"></span></label><label class="field"><span class="field__label">Код <b>*</b></span><span class="field__control"><input name="code" required maxlength="24" placeholder="B"></span></label><label class="field"><span class="field__label">Единица</span><span class="field__control"><select name="unit"><?php foreach ($units as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></span></label><label class="field"><span class="field__label">Цвет</span><span class="field__control color-field"><input type="color" name="color" value="#4768db"><span>#</span></span></label><label class="field"><span class="field__label">Порядок</span><span class="field__control"><input type="number" name="sort_order" value="0"></span></label></div><input type="hidden" name="status" value="active"><div class="stage-form__actions"><button class="button button--primary" type="submit">Добавить тип</button></div></form>
</details>

<section class="panel reveal">
    <div class="data-table-wrap"><table class="data-table stage-table catalog-table balance-type-table"><thead><tr><th>Код</th><th>Название</th><th>Единица</th><th>Цвет</th><th>Порядок</th><th>Статус</th><th>Папка</th><th><span class="sr-only">Действия</span></th></tr></thead><tbody>
    <?php foreach ($visibleTypes as $type): ?>
        <tr id="balance-type-row-<?= (int) $type['id'] ?>">
            <td><span class="balance-code balance-type-table__code" style="--chip-color:<?= e($type['color']) ?>"><?= e($type['code']) ?></span></td>
            <td><strong><?= e($type['name']) ?></strong></td>
            <td><?= e($units[$type['unit']] ?? $type['unit']) ?></td>
            <td><span class="balance-type-table__color"><i style="--chip-color:<?= e($type['color']) ?>" aria-hidden="true"></i><?= e(strtoupper($type['color'])) ?></span></td>
            <td><?= (int) $type['sort_order'] ?></td>
            <td><span class="status-pill status-pill--<?= $type['status'] === 'active' ? 'active' : 'blocked' ?>"><i></i><?= $type['status'] === 'active' ? 'Используется' : 'Скрыт' ?></span></td>
            <td><?= e($selectedFolder['name'] ?? '—') ?></td>
            <td><?php if ($currentUser->canManageEducation()): ?><button class="table-action" type="button" data-dialog-open="balanceTypeEdit<?= (int) $type['id'] ?>" aria-haspopup="dialog" aria-controls="balanceTypeEdit<?= (int) $type['id'] ?>" aria-label="Редактировать <?= e($type['name']) ?>" title="Редактировать"><?= icon('edit', 17) ?></button><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$visibleTypes): ?><tr><td colspan="8"><div class="empty-inline">Типов баланса пока нет.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<?php if ($currentUser->canManageEducation()): foreach ($visibleTypes as $type): ?>
<dialog class="modal" id="balanceTypeEdit<?= (int) $type['id'] ?>" aria-labelledby="balanceTypeEditTitle<?= (int) $type['id'] ?>">
    <form method="post" action="<?= e(url('/education/balance-types/' . $type['id'] . '/update')) ?>" class="modal__surface" data-submit-loading>
        <?= csrf_field() ?>
        <div class="modal__header"><div><p class="page-eyebrow">Типы балансов</p><h2 id="balanceTypeEditTitle<?= (int) $type['id'] ?>">Редактировать тип баланса</h2><p><?= e($type['name']) ?></p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><div class="form-grid">
            <label class="field"><span class="field__label">Название <b>*</b></span><span class="field__control"><input name="name" value="<?= e($type['name']) ?>" required minlength="2" maxlength="190"></span></label>
            <label class="field"><span class="field__label">Код <b>*</b></span><span class="field__control"><input name="code" value="<?= e($type['code']) ?>" required maxlength="24"></span></label>
            <label class="field"><span class="field__label">Единица</span><span class="field__control"><select name="unit"><?php foreach ($units as $key => $label): ?><option value="<?= e($key) ?>" <?= $type['unit'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></span></label>
            <label class="field"><span class="field__label">Цвет</span><span class="field__control color-field"><input type="color" name="color" value="<?= e($type['color']) ?>"><span>Цвет метки</span></span></label>
            <label class="field"><span class="field__label">Порядок</span><span class="field__control"><input type="number" name="sort_order" value="<?= (int) $type['sort_order'] ?>"></span></label>
            <label class="field"><span class="field__label">Статус</span><span class="field__control"><select name="status"><option value="active" <?= $type['status'] === 'active' ? 'selected' : '' ?>>Используется</option><option value="archived" <?= $type['status'] === 'archived' ? 'selected' : '' ?>>Скрыт</option></select></span></label>
            <label class="field form-grid__wide"><span class="field__label">Папка</span><span class="field__control"><select name="folder_id" required><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>" <?= (int) $type['folder_id'] === (int) $folder['id'] ? 'selected' : '' ?>><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label>
        </div></div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Сохранить изменения</button></div>
    </form>
</dialog>
<?php endforeach; endif; ?>

    </main>
</div>

<dialog class="modal" id="balanceFolderCreateDialog" aria-labelledby="balanceFolderCreateTitle">
    <form class="modal__surface" method="post" action="<?= e(url('/education/balance-folders')) ?>" data-submit-loading>
        <?= csrf_field() ?>
        <div class="modal__header"><div><p class="page-eyebrow">Типы балансов</p><h2 id="balanceFolderCreateTitle">Новая папка</h2><p>Создайте папку для организации типов балансов.</p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><div class="form-grid">
            <label class="field form-grid__wide"><span class="field__label">Название папки <b>*</b></span><span class="field__control"><input name="name" required minlength="2" maxlength="120" placeholder="Например, Вождение"></span></label>
            <label class="field form-grid__wide"><span class="field__label">Родительская папка</span><span class="field__control"><select name="parent_id"><option value="">Верхний уровень</option><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>" <?= (int) $folder['id'] === (int) $selectedFolderId ? 'selected' : '' ?>><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label>
        </div></div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Создать папку</button></div>
    </form>
</dialog>

<dialog class="modal" id="classMoveDialog">
    <form class="modal__surface" method="post" data-class-move-form data-action-template="<?= e(url('/education/balance-types/__ID__/move')) ?>">
        <?= csrf_field() ?>
        <div class="modal__header"><div><p class="page-eyebrow">Типы балансов</p><h2>Переместить тип баланса</h2><p data-move-label></p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><label class="field"><span class="field__label">Папка назначения</span><span class="field__control"><select name="folder_id" required data-move-folder><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>"><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label></div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Переместить</button></div>
    </form>
</dialog>
<dialog class="modal" id="folderMoveDialog">
    <form class="modal__surface" method="post" data-folder-move-form data-action-template="<?= e(url('/education/balance-folders/__ID__/move')) ?>">
        <?= csrf_field() ?>
        <div class="modal__header"><div><p class="page-eyebrow">Типы балансов</p><h2>Переместить папку</h2><p data-folder-move-label></p></div><button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть"><?= icon('x', 20) ?></button></div>
        <div class="modal__body"><label class="field"><span class="field__label">Родительская папка</span><span class="field__control"><select name="parent_id" data-folder-destination><option value="">Верхний уровень</option><?php foreach ($folders as $folder): ?><option value="<?= (int) $folder['id'] ?>"><?= e(str_repeat('— ', (int) $folder['depth']) . $folder['name']) ?></option><?php endforeach; ?></select></span></label></div>
        <div class="modal__footer"><button class="button button--ghost" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Переместить</button></div>
    </form>
</dialog>
