(() => {
    'use strict';

    const storageKey = 'crm-font-scale';
    const defaultSize = 100;
    const minSize = 100;
    const maxSize = 150;
    const step = 5;

    function normalizeSize(value) {
        const size = Number(value);
        if (!Number.isFinite(size)) return defaultSize;
        return Math.min(maxSize, Math.max(minSize, Math.round(size / step) * step));
    }

    function readSize() {
        try {
            return normalizeSize(localStorage.getItem(storageKey) ?? defaultSize);
        } catch (_) {
            return defaultSize;
        }
    }

    let currentSize = readSize();

    // Run in the head so saved typography is applied before the first paint.
    function applySize(size) {
        currentSize = normalizeSize(size);
        document.documentElement.style.setProperty('--font-scale', String(currentSize / 100));
    }

    applySize(currentSize);

    document.addEventListener('DOMContentLoaded', () => {
        const slider = document.getElementById('fontSizeSlider');
        const output = document.getElementById('fontSizeValue');
        const reset = document.getElementById('fontSizeReset');
        const status = document.getElementById('appearanceSaveStatus');

        function syncControls() {
            if (!slider) return;
            slider.value = String(currentSize);
            slider.setAttribute('aria-valuetext', `${currentSize} процентов`);
            output.value = `${currentSize}%`;
            reset.disabled = currentSize === defaultSize;
        }

        function saveSize(size, clear = false) {
            applySize(size);
            syncControls();
            try {
                if (clear) localStorage.removeItem(storageKey);
                else localStorage.setItem(storageKey, String(currentSize));
                status.textContent = 'Размер текста сохраняется автоматически в этом браузере.';
            } catch (_) {
                status.textContent = 'Браузер не разрешает сохранить настройку. Размер текста изменён только для этой страницы.';
            }
        }

        if (slider) {
            slider.min = String(minSize);
            slider.max = String(maxSize);
            slider.step = String(step);
            slider.addEventListener('input', () => saveSize(slider.value));
            reset.addEventListener('click', () => saveSize(defaultSize, true));
            syncControls();
        }

        window.addEventListener('storage', (event) => {
            if (event.key !== storageKey && event.key !== null) return;
            applySize(readSize());
            syncControls();
        });
    });
})();
