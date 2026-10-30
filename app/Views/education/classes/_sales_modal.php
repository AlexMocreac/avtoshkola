<?php
$salesPayload = json_encode(['services' => $saleServices, 'sales' => $studentSales, 'balances' => $balanceTypes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="studentSalesData"><?= $salesPayload ?></script>
<dialog class="modal sales-dialog" id="studentSalesDialog">
    <div class="modal__surface">
        <header class="modal__header"><div><p class="page-eyebrow">Финансы курсанта</p><h2>Продажи и расчёты по договору</h2><p data-sales-student></p></div><button class="icon-button" type="button" data-sales-close aria-label="Закрыть">×</button></header>
        <div class="modal__body sales-body">
            <div class="sales-toolbar"><button class="button button--primary" type="button" data-sale-start>＋ Продажа</button><a class="button button--secondary" data-sales-profile href="#">Открыть карточку и принять оплату</a></div>
            <div class="data-table-wrap"><table class="data-table sales-table"><thead><tr><th>Дата операции</th><th>Начислено</th><th>Оплачено</th><th>Баланс</th><th>Услуга / товар</th><th>Сотрудник</th></tr></thead><tbody data-sales-rows></tbody></table></div>
            <div class="sales-totals" data-sales-totals></div>
        </div>
    </div>
</dialog>
<dialog class="modal sale-wizard" id="saleWizard">
    <form class="modal__surface" method="post" id="saleWizardForm">
        <?= csrf_field() ?><input type="hidden" name="service_id"><input type="hidden" name="amount">
        <header class="modal__header"><div><p class="page-eyebrow">Новая продажа</p><h2>Оформление продажи</h2><p data-sales-student></p></div><button class="icon-button" type="button" data-wizard-close aria-label="Закрыть">×</button></header>
        <div class="sale-progress" aria-label="Этапы продажи"><span>1. Услуга</span><span>2. Условия</span><span>3. Рассрочка</span><span>4. Начисления</span><span>5. Проверка</span></div>
        <div class="modal__body sale-wizard-body">
            <section data-sale-step="0"><h3>Выбор товара / услуги</h3><p>Выберите услугу или товар для оформления продажи и нажмите «Далее».</p><input class="sale-search" type="search" placeholder="Поиск услуги" aria-label="Поиск услуги"><div class="data-table-wrap"><table class="data-table sales-table"><thead><tr><th>Наименование</th><th>Стоимость</th><th>Основная</th></tr></thead><tbody data-service-rows></tbody></table></div></section>
            <section data-sale-step="1" hidden><h3>Количество и условия</h3><p>Укажите основные параметры продажи.</p><div class="sale-fields"><label class="field"><span class="field__label">Дата продажи *</span><span class="field__control"><input type="date" name="sold_on" value="<?= e(date('Y-m-d')) ?>" required></span></label><label class="field"><span class="field__label">Стоимость по тарифу</span><span class="field__control"><input data-tariff readonly></span></label><label class="field"><span class="field__label">Сумма продажи, ₽ *</span><span class="field__control"><input data-sale-amount type="number" min="0.01" step="0.01" required></span></label><label class="field"><span class="field__label">Примечание</span><span class="field__control"><input name="notes" maxlength="5000"></span></label></div></section>
            <section data-sale-step="2" hidden><h3>Схема рассрочки</h3><p>Укажите этапы оплаты на полную сумму продажи.</p><div class="sale-note">Этапы рассрочки показаны для ознакомления и пока не сохраняются. Продажа сохраняется без графика платежей.</div><div class="sale-installment-controls"><label>Крайняя дата <input type="date" data-installment-date></label><label>Сумма, ₽ <input type="number" min="0.01" step="0.01" data-installment-amount></label><button class="button button--secondary" type="button" data-installment-add>＋ Добавить этап</button></div><table class="data-table sales-table"><thead><tr><th>Крайняя дата оплаты</th><th>Сумма к оплате</th><th></th></tr></thead><tbody data-installment-rows></tbody></table></section>
            <section data-sale-step="3" hidden><h3>Начисляемые учебные часы</h3><p>Проверьте схему начисления на балансы, указанную в услуге.</p><div class="data-table-wrap"><table class="data-table sales-table"><thead data-allocation-head></thead><tbody data-allocation-rows></tbody></table></div></section>
            <section data-sale-step="4" hidden><h3>Проверка данных</h3><p>Проверьте данные проводимой продажи.</p><div class="sale-review" data-sale-review></div></section>
            <div class="form-alert form-alert--error is-hidden" data-sale-error role="alert"></div>
        </div>
        <footer class="modal__footer"><button class="button button--ghost" type="button" data-sale-back>Назад</button><button class="button button--primary" type="button" data-sale-next>Далее →</button><button class="button button--primary" type="submit" data-sale-finish hidden>✓ Завершить</button></footer>
    </form>
</dialog>
