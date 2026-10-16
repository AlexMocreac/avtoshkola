<section class="section-heading reveal">
    <div><h2>Контрольные точки обучения</h2><p>Единый порядок зачётов и экзаменов. Преподаватели отмечают прохождение в классе или карточке курсанта.</p></div>
    <div class="page-actions"><a class="button button--secondary" href="<?= e(url('/education/programs')) ?>"><?= icon('book-open', 17) ?> Учебные программы</a><a class="button button--secondary" href="<?= e(url('/education/balance-types')) ?>"><?= icon('activity', 17) ?> Типы балансов</a></div>
</section>

<details class="panel stage-form-panel reveal" <?= !$checkpoints ? 'open' : '' ?>>
    <summary><span><?= icon('plus', 18) ?></span><strong>Добавить контрольную точку</strong><small>Название, короткий код и порядок отображения</small></summary>
    <form method="post" action="<?= e(url('/education/checkpoints')) ?>" class="stage-form"><div class="form-grid form-grid--three"><?= csrf_field() ?><label class="field"><span class="field__label">Полное название <b>*</b></span><span class="field__control"><input name="name" required maxlength="190" placeholder="Теоретический зачёт №1"></span></label><label class="field"><span class="field__label">Код <b>*</b></span><span class="field__control"><input name="code" required maxlength="24" placeholder="Зт1"></span></label><label class="field"><span class="field__label">Порядок</span><span class="field__control"><input type="number" name="sort_order" value="0"></span></label></div><input type="hidden" name="status" value="active"><div class="stage-form__actions"><button class="button button--primary" type="submit">Добавить точку</button></div></form>
</details>

<section class="panel reveal">
    <div class="data-table-wrap"><table class="data-table stage-table catalog-table"><thead><tr><th>Название</th><th>Код</th><th>Порядок</th><th>Статус</th><th></th></tr></thead><tbody>
    <?php foreach ($checkpoints as $checkpoint): $formId = 'checkpoint-' . (int) $checkpoint['id']; ?><tr><td><input form="<?= e($formId) ?>" name="name" value="<?= e($checkpoint['name']) ?>" required></td><td><input form="<?= e($formId) ?>" name="code" value="<?= e($checkpoint['code']) ?>" required maxlength="24"></td><td><input form="<?= e($formId) ?>" type="number" name="sort_order" value="<?= (int) $checkpoint['sort_order'] ?>"></td><td><select form="<?= e($formId) ?>" name="status"><option value="active" <?= $checkpoint['status'] === 'active' ? 'selected' : '' ?>>Используется</option><option value="archived" <?= $checkpoint['status'] === 'archived' ? 'selected' : '' ?>>Скрыта</option></select></td><td><form id="<?= e($formId) ?>" method="post" action="<?= e(url('/education/checkpoints/' . $checkpoint['id'] . '/update')) ?>"><?= csrf_field() ?><button class="button button--secondary" type="submit">Сохранить</button></form></td></tr><?php endforeach; ?>
    <?php if (!$checkpoints): ?><tr><td colspan="5"><div class="empty-inline">Контрольных точек пока нет.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
