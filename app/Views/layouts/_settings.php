<dialog class="modal settings-modal" id="settingsModal" aria-labelledby="settingsModalTitle">
    <div class="modal__surface">
        <div class="modal__header">
            <div>
                <h2 id="settingsModalTitle">Настройки</h2>
                <p>Настройте интерфейс для комфортной работы.</p>
            </div>
            <button class="icon-button modal__close" type="button" data-dialog-close aria-label="Закрыть настройки"><?= icon('x', 20) ?></button>
        </div>
        <div class="modal__body settings-sections">
            <section class="settings-section" aria-labelledby="appearanceTitle">
                <h3 id="appearanceTitle">Внешний вид</h3>
                <div class="settings-section__label">
                    <label for="fontSizeSlider">Размер текста</label>
                    <output id="fontSizeValue" for="fontSizeSlider" aria-live="off">100%</output>
                </div>
                <p id="fontSizeDescription">Увеличьте или уменьшите текст на всех страницах сайта. Изменения видны сразу.</p>
                <input class="settings-range" id="fontSizeSlider" type="range" min="100" max="150" step="5" value="100" aria-describedby="fontSizeDescription" autofocus>
                <div class="settings-range__limits" aria-hidden="true"><span>100%</span><span>150%</span></div>
                <p id="appearanceSaveStatus" role="status">Размер текста сохраняется автоматически в этом браузере.</p>
            </section>
        </div>
        <div class="modal__footer">
            <button class="button button--secondary" id="fontSizeReset" type="button">По умолчанию</button>
            <button class="button button--primary" type="button" data-dialog-close>Готово</button>
        </div>
    </div>
</dialog>
