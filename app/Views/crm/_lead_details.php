<dialog class="modal lead-details-modal" id="leadDetailsModal">
    <div class="modal__surface modal__surface--wide">
        <div class="modal__header"><div><p class="page-eyebrow" data-lead-details-direction>Карточка клиента</p><h2 data-lead-details-name>Клиент</h2><p data-lead-details-status></p></div><button class="icon-button modal__close" type="button" data-dialog-close><?= icon('x', 20) ?></button></div>
        <div class="modal__body lead-details">
            <div class="lead-details__summary">
                <div><small>Телефон</small><a href="#" data-lead-details-phone>—</a></div>
                <div><small>Email</small><a href="#" data-lead-details-email>—</a></div>
                <div><small>Ответственный</small><strong data-lead-details-manager>—</strong></div>
                <div><small>Источник</small><strong data-lead-details-source>—</strong></div>
            </div>
            <p class="lead-details__notes is-hidden" data-lead-details-notes></p>
            <div class="lead-history__head"><div><p class="page-eyebrow">Хронология</p><h3>История работы с лидом</h3></div><span class="lead-history__loader" data-lead-history-loader>Загрузка…</span></div>
            <div class="lead-history" data-lead-history></div>
        </div>
        <div class="modal__footer"><button class="button button--secondary" type="button" data-dialog-close>Закрыть</button></div>
    </div>
</dialog>
