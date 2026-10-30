(() => {
    'use strict';

    const body = document.body;
    const basePath = body.dataset.baseUrl || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const path = (value) => `${basePath}/${String(value).replace(/^\//, '')}`;
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const padNumber = (value) => String(value).padStart(2, '0');

    function setFormLoading(form, loading, requestedButton = null) {
        const submit = requestedButton || qs('button[type="submit"]', form);
        if (!submit) return;
        if (loading) {
            if (submit.classList.contains('is-loading')) return;
            submit.dataset.loadingOriginalHtml = submit.innerHTML;
            const spinner = document.createElement('span');
            spinner.className = 'button__spinner';
            spinner.setAttribute('aria-hidden', 'true');
            submit.replaceChildren(spinner);
            if (!submit.classList.contains('icon-button')) {
                const label = document.createElement('span');
                label.textContent = form.dataset.loadingText || 'Сохраняем…';
                submit.append(label);
            }
            submit.disabled = true;
            submit.classList.add('is-loading');
            submit.setAttribute('aria-busy', 'true');
            return;
        }
        if (submit.dataset.loadingOriginalHtml !== undefined) {
            submit.innerHTML = submit.dataset.loadingOriginalHtml;
            delete submit.dataset.loadingOriginalHtml;
        }
        submit.disabled = false;
        submit.classList.remove('is-loading');
        submit.removeAttribute('aria-busy');
    }

    function russianPhoneDigits(value) {
        let digits = String(value || '').replace(/\D/g, '');
        if (digits.startsWith('8')) digits = `7${digits.slice(1)}`;
        else if (digits !== '' && !digits.startsWith('7')) digits = `7${digits}`;
        return digits.slice(0, 11);
    }

    function formatRussianPhone(value) {
        const digits = russianPhoneDigits(value);
        if (digits === '') return '';
        const local = digits.slice(1);
        let formatted = '+7';
        if (local.length > 0) formatted += ` (${local.slice(0, 3)}`;
        if (local.length >= 3) formatted += ')';
        if (local.length > 3) formatted += ` ${local.slice(3, 6)}`;
        if (local.length > 6) formatted += `-${local.slice(6, 8)}`;
        if (local.length > 8) formatted += `-${local.slice(8, 10)}`;
        return formatted;
    }

    function syncPhoneMasks(root = document) {
        qsa('input[type="tel"], input[inputmode="tel"][name*="phone"]', root).forEach((input) => {
            input.type = 'tel';
            input.inputMode = 'tel';
            input.autocomplete = 'tel';
            input.maxLength = 18;
            input.placeholder = '+7 (900) 000-00-00';
            input.pattern = '\\+7 \\(\\d{3}\\) \\d{3}-\\d{2}-\\d{2}';
            input.title = 'Введите номер в формате +7 (900) 000-00-00';
            input.value = formatRussianPhone(input.value);
            if (input.dataset.phoneMaskReady === '1') return;
            input.dataset.phoneMaskReady = '1';
            const applyMask = () => {
                input.value = formatRussianPhone(input.value);
                input.setSelectionRange(input.value.length, input.value.length);
            };
            input.addEventListener('focus', () => {
                if (input.value === '') input.value = '+7';
            });
            input.addEventListener('input', applyMask);
            input.addEventListener('change', applyMask);
            input.addEventListener('paste', (event) => {
                const pasted = event.clipboardData?.getData('text');
                if (!pasted) return;
                event.preventDefault();
                input.value = formatRussianPhone(pasted);
                input.setSelectionRange(input.value.length, input.value.length);
            });
            input.addEventListener('blur', () => {
                if (russianPhoneDigits(input.value).length <= 1) input.value = '';
            });
        });
    }

    syncPhoneMasks();

    function formatMoneyInputValue(value) {
        const compact = String(value || '').replace(/[\s\u00a0]/g, '').replace(/[^\d.,]/g, '');
        if (compact === '') return '';
        const separatorIndex = compact.search(/[.,]/);
        const integerSource = separatorIndex === -1 ? compact : compact.slice(0, separatorIndex);
        const fractionSource = separatorIndex === -1 ? '' : compact.slice(separatorIndex + 1);
        const integerDigits = integerSource.replace(/\D/g, '').slice(0, 10);
        const fractionDigits = fractionSource.replace(/\D/g, '').slice(0, 2);
        const integer = (integerDigits || '0').replace(/^0+(?=\d)/, '');
        const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return `${grouped}${separatorIndex === -1 ? '' : `,${fractionDigits}`}`;
    }

    function syncMoneyInputs(root = document) {
        qsa('[data-money-input]', root).forEach((input) => {
            input.value = formatMoneyInputValue(input.value);
            if (input.dataset.moneyInputReady === '1') return;
            input.dataset.moneyInputReady = '1';
            input.addEventListener('input', () => {
                const significantBeforeCursor = input.value
                    .slice(0, input.selectionStart ?? input.value.length)
                    .replace(/[^\d.,]/g, '').length;
                input.value = formatMoneyInputValue(input.value);
                let cursor = 0;
                let significant = 0;
                while (cursor < input.value.length && significant < significantBeforeCursor) {
                    if (/[\d,]/.test(input.value[cursor])) significant++;
                    cursor++;
                }
                input.setSelectionRange(cursor, cursor);
            });
            input.addEventListener('blur', () => {
                input.value = formatMoneyInputValue(input.value).replace(/,$/, '');
            });
        });
    }

    syncMoneyInputs();

    function toast(message, type = 'success') {
        const stack = qs('#toastStack');
        if (!stack || !message) return;
        const element = document.createElement('div');
        element.className = `toast toast--${type}`;
        element.textContent = message;
        stack.append(element);
        window.setTimeout(() => {
            element.classList.add('is-leaving');
            window.setTimeout(() => element.remove(), 240);
        }, 4200);
    }

    qsa('[data-toast]').forEach((element) => {
        window.setTimeout(() => {
            element.classList.add('is-leaving');
            window.setTimeout(() => element.remove(), 240);
        }, 4500);
    });

    async function request(url, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set('X-CSRF-TOKEN', csrfToken);
        headers.set('X-Requested-With', 'XMLHttpRequest');
        const response = await fetch(url, { ...options, headers });
        let data;
        try {
            data = await response.json();
        } catch (_) {
            throw new Error('Сервер вернул некорректный ответ.');
        }
        if (!response.ok || data.ok === false) {
            const error = new Error(data.message || 'Не удалось выполнить действие.');
            error.data = data;
            throw error;
        }
        return data;
    }

    function openDialog(dialog) {
        if (!dialog) return;
        dialog.classList.remove('is-closing');
        if (!dialog.open) dialog.showModal();
    }

    function closeDialog(dialog) {
        if (!dialog?.open) return;
        dialog.classList.add('is-closing');
        window.setTimeout(() => {
            dialog.close();
            dialog.classList.remove('is-closing');
        }, 170);
    }

    // Branded Russian date and date-time picker.
    const monthNames = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    let activeDatePicker = null;
    let calendarMonth = new Date();
    let selectedDate = new Date();
    let selectedHour = 9;
    let selectedMinute = 0;
    const datePopover = document.createElement('div');
    datePopover.className = 'date-popover is-hidden';
    datePopover.setAttribute('role', 'dialog');
    datePopover.setAttribute('aria-label', 'Выбор даты');
    datePopover.innerHTML = `
        <div class="date-popover__head">
            <button type="button" data-calendar-prev aria-label="Предыдущий месяц">‹</button>
            <div class="date-popover__selectors"><select data-calendar-month aria-label="Месяц"></select><input data-calendar-year type="number" min="100" max="9999" aria-label="Год"></div>
            <button type="button" data-calendar-next aria-label="Следующий месяц">›</button>
        </div>
        <div class="date-popover__week"><span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span></div>
        <div class="date-popover__days" data-calendar-days></div>
        <div class="date-popover__time is-hidden" data-calendar-time>
            <span>Время</span>
            <label><span class="sr-only">Часы</span><select data-calendar-hour aria-label="Часы"></select></label>
            <b>:</b>
            <label><span class="sr-only">Минуты</span><select data-calendar-minute aria-label="Минуты"></select></label>
        </div>
        <div class="date-popover__footer">
            <button type="button" data-calendar-clear>Очистить</button>
            <button type="button" data-calendar-today>Сегодня</button>
            <button class="date-popover__apply" type="button" data-calendar-apply>Выбрать</button>
        </div>`;

    const monthSelect = qs('[data-calendar-month]', datePopover);
    const yearInput = qs('[data-calendar-year]', datePopover);
    monthNames.forEach((name, index) => monthSelect.add(new Option(name.toLowerCase(), String(index))));
    const hourSelect = qs('[data-calendar-hour]', datePopover);
    const minuteSelect = qs('[data-calendar-minute]', datePopover);
    for (let value = 0; value < 24; value++) hourSelect.add(new Option(padNumber(value), String(value)));
    for (let value = 0; value < 60; value++) minuteSelect.add(new Option(padNumber(value), String(value)));

    function normalizedPickerValue(value, withTime) {
        const normalized = String(value || '').trim().replace(' ', 'T');
        const pattern = withTime ? /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/ : /^\d{4}-\d{2}-\d{2}/;
        if (!pattern.test(normalized)) return '';
        return normalized.slice(0, withTime ? 16 : 10);
    }

    function pickerDate(value) {
        const normalized = String(value || '').replace(' ', 'T');
        const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/);
        if (!match) return null;
        const parsed = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4] || 0), Number(match[5] || 0));
        return Number.isNaN(parsed.valueOf()) ? null : parsed;
    }

    function syncDatePicker(wrapper) {
        const hidden = qs('[data-date-value]', wrapper);
        const display = qs('[data-date-display]', wrapper);
        if (!hidden || !display) return;
        const withTime = wrapper.dataset.withTime === '1';
        const normalized = normalizedPickerValue(hidden.value, withTime);
        hidden.value = normalized;
        const parsed = pickerDate(normalized);
        display.value = parsed
            ? `${padNumber(parsed.getDate())}.${padNumber(parsed.getMonth() + 1)}.${parsed.getFullYear()}${withTime ? `, ${padNumber(parsed.getHours())}:${padNumber(parsed.getMinutes())}` : ''}`
            : '';
    }

    function syncAllDatePickers(root = document) {
        qsa('[data-date-picker]', root).forEach(syncDatePicker);
    }

    function commitDatePicker(close = true) {
        if (!activeDatePicker) return;
        const withTime = activeDatePicker.dataset.withTime === '1';
        const hidden = qs('[data-date-value]', activeDatePicker);
        hidden.value = `${selectedDate.getFullYear()}-${padNumber(selectedDate.getMonth() + 1)}-${padNumber(selectedDate.getDate())}${withTime ? `T${padNumber(selectedHour)}:${padNumber(selectedMinute)}` : ''}`;
        syncDatePicker(activeDatePicker);
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        if (close) closeDatePopover();
    }

    function positionDatePopover() {
        if (!activeDatePicker || datePopover.classList.contains('is-hidden')) return;
        const rect = activeDatePicker.getBoundingClientRect();
        const dialog = activeDatePicker.closest('dialog');
        const hostRect = dialog?.getBoundingClientRect();
        const width = Math.min(320, window.innerWidth - 20, hostRect ? hostRect.width - 20 : 320);
        const viewportLeft = Math.max(10, Math.min(rect.left, window.innerWidth - width - 10));
        const estimatedHeight = activeDatePicker.dataset.withTime === '1' ? 420 : 365;
        const viewportTop = rect.bottom + estimatedHeight > window.innerHeight && rect.top > estimatedHeight
            ? Math.max(10, rect.top - estimatedHeight - 7)
            : Math.min(window.innerHeight - 10, rect.bottom + 7);
        datePopover.style.position = dialog ? 'absolute' : 'fixed';
        datePopover.style.width = `${width}px`;
        datePopover.style.left = `${dialog ? viewportLeft - hostRect.left : viewportLeft}px`;
        datePopover.style.top = `${dialog ? viewportTop - hostRect.top : viewportTop}px`;
    }

    function renderCalendar() {
        monthSelect.value = String(calendarMonth.getMonth());
        yearInput.value = String(calendarMonth.getFullYear());
        const container = qs('[data-calendar-days]', datePopover);
        container.replaceChildren();
        const first = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1);
        const offset = (first.getDay() + 6) % 7;
        const start = new Date(first.getFullYear(), first.getMonth(), 1 - offset);
        const today = new Date();

        for (let index = 0; index < 42; index++) {
            const day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(day.getDate());
            button.dataset.calendarDay = `${day.getFullYear()}-${day.getMonth()}-${day.getDate()}`;
            button.classList.toggle('is-outside', day.getMonth() !== calendarMonth.getMonth());
            button.classList.toggle('is-today', day.toDateString() === today.toDateString());
            button.classList.toggle('is-selected', day.toDateString() === selectedDate.toDateString());
            button.setAttribute('aria-label', day.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }));
            container.append(button);
        }
        const withTime = activeDatePicker?.dataset.withTime === '1';
        qs('[data-calendar-time]', datePopover).classList.toggle('is-hidden', !withTime);
        qs('[data-calendar-apply]', datePopover).classList.toggle('is-hidden', !withTime);
        hourSelect.value = String(selectedHour);
        minuteSelect.value = String(selectedMinute);
    }

    function openDatePopover(wrapper) {
        if (activeDatePicker && activeDatePicker !== wrapper) closeDatePopover();
        closeTimePopover();
        activeDatePicker = wrapper;
        const withTime = wrapper.dataset.withTime === '1';
        const parsed = pickerDate(qs('[data-date-value]', wrapper).value) || new Date();
        selectedDate = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
        calendarMonth = new Date(parsed.getFullYear(), parsed.getMonth(), 1);
        selectedHour = withTime ? parsed.getHours() : 0;
        selectedMinute = withTime ? parsed.getMinutes() : 0;
        const container = wrapper.closest('dialog') || document.body;
        container.append(datePopover);
        datePopover.classList.remove('is-hidden');
        qs('[data-date-trigger]', wrapper)?.setAttribute('aria-expanded', 'true');
        renderCalendar();
        positionDatePopover();
    }

    function closeDatePopover() {
        if (activeDatePicker) qs('[data-date-trigger]', activeDatePicker)?.setAttribute('aria-expanded', 'false');
        datePopover.classList.add('is-hidden');
        activeDatePicker = null;
    }

    qsa('[data-date-picker]').forEach((wrapper) => {
        syncDatePicker(wrapper);
        qsa('[data-date-display], [data-date-trigger]', wrapper).forEach((control) => control.addEventListener('click', () => openDatePopover(wrapper)));
        qs('[data-date-display]', wrapper)?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDatePopover(wrapper);
            }
        });
    });

    datePopover.addEventListener('click', (event) => {
        const dayButton = event.target.closest('[data-calendar-day]');
        if (dayButton) {
            const [year, month, day] = dayButton.dataset.calendarDay.split('-').map(Number);
            selectedDate = new Date(year, month, day);
            calendarMonth = new Date(year, month, 1);
            if (activeDatePicker?.dataset.withTime === '1') renderCalendar();
            else commitDatePicker();
            return;
        }
        if (event.target.closest('[data-calendar-prev]')) {
            calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() - 1, 1);
            renderCalendar();
        } else if (event.target.closest('[data-calendar-next]')) {
            calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 1);
            renderCalendar();
        } else if (event.target.closest('[data-calendar-today]')) {
            const today = new Date();
            selectedDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            calendarMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            selectedHour = today.getHours();
            selectedMinute = today.getMinutes();
            if (activeDatePicker?.dataset.withTime === '1') renderCalendar();
            else commitDatePicker();
        } else if (event.target.closest('[data-calendar-clear]')) {
            const hidden = qs('[data-date-value]', activeDatePicker);
            hidden.value = '';
            syncDatePicker(activeDatePicker);
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            closeDatePopover();
        } else if (event.target.closest('[data-calendar-apply]')) {
            selectedHour = Number(hourSelect.value);
            selectedMinute = Number(minuteSelect.value);
            commitDatePicker();
        }
    });
    monthSelect.addEventListener('change', () => {
        calendarMonth = new Date(calendarMonth.getFullYear(), Number(monthSelect.value), 1);
        renderCalendar();
    });
    function changeCalendarYear() {
        const year = Number(yearInput.value);
        if (Number.isInteger(year) && year >= 100 && year <= 9999) {
            calendarMonth = new Date(year, calendarMonth.getMonth(), 1);
        }
        renderCalendar();
    }
    yearInput.addEventListener('change', changeCalendarYear);
    yearInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            changeCalendarYear();
            yearInput.blur();
        }
    });
    hourSelect.addEventListener('change', () => { selectedHour = Number(hourSelect.value); });
    minuteSelect.addEventListener('change', () => { selectedMinute = Number(minuteSelect.value); });
    document.addEventListener('click', (event) => {
        if (activeDatePicker && !activeDatePicker.contains(event.target) && !datePopover.contains(event.target)) closeDatePopover();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeDatePopover(); });
    window.addEventListener('resize', positionDatePopover);
    document.addEventListener('scroll', positionDatePopover, true);
    qsa('form').forEach((form) => form.addEventListener('reset', () => window.setTimeout(() => syncAllDatePickers(form))));

    // Compact time picker used by weekly class schedules.
    let activeTimePicker = null;
    let selectedTimeHour = 9;
    let selectedTimeMinute = 0;
    const timePopover = document.createElement('div');
    timePopover.className = 'time-popover is-hidden';
    timePopover.setAttribute('role', 'dialog');
    timePopover.setAttribute('aria-label', 'Выбор времени');
    timePopover.innerHTML = `
        <div class="time-popover__head">
            <span class="time-popover__icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span>
            <span><strong>Время занятия</strong><small>Укажите часы и минуты</small></span>
        </div>
        <div class="time-popover__controls">
            <label>Часы<select data-time-hour aria-label="Часы"></select></label>
            <b>:</b>
            <label>Минуты<select data-time-minute aria-label="Минуты"></select></label>
        </div>
        <div class="time-popover__quick" aria-label="Быстрый выбор времени">
            ${['08:00', '09:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'].map((time) => `<button type="button" data-quick-time="${time}">${time}</button>`).join('')}
        </div>
        <div class="time-popover__footer">
            <button type="button" data-time-cancel>Отмена</button>
            <button class="time-popover__apply" type="button" data-time-apply>Выбрать</button>
        </div>`;

    const timeHourSelect = qs('[data-time-hour]', timePopover);
    const timeMinuteSelect = qs('[data-time-minute]', timePopover);
    for (let value = 0; value < 24; value++) timeHourSelect.add(new Option(padNumber(value), String(value)));
    for (let value = 0; value < 60; value++) timeMinuteSelect.add(new Option(padNumber(value), String(value)));

    function normalizedTimeValue(value) {
        const match = String(value || '').trim().match(/^([01]\d|2[0-3]):([0-5]\d)/);
        return match ? `${match[1]}:${match[2]}` : '09:00';
    }

    function syncTimePicker(wrapper) {
        const hidden = qs('[data-time-value]', wrapper);
        const display = qs('[data-time-display]', wrapper);
        if (!hidden || !display) return;
        hidden.value = normalizedTimeValue(hidden.value);
        display.textContent = hidden.value;
    }

    function syncAllTimePickers(root = document) {
        qsa('[data-time-picker]', root).forEach(syncTimePicker);
    }

    function renderTimePicker() {
        timeHourSelect.value = String(selectedTimeHour);
        timeMinuteSelect.value = String(selectedTimeMinute);
        const selectedValue = `${padNumber(selectedTimeHour)}:${padNumber(selectedTimeMinute)}`;
        qsa('[data-quick-time]', timePopover).forEach((button) => button.classList.toggle('is-selected', button.dataset.quickTime === selectedValue));
    }

    function positionTimePopover() {
        if (!activeTimePicker || timePopover.classList.contains('is-hidden')) return;
        const rect = activeTimePicker.getBoundingClientRect();
        const dialog = activeTimePicker.closest('dialog');
        const hostRect = dialog?.getBoundingClientRect();
        const width = Math.min(290, window.innerWidth - 20, hostRect ? hostRect.width - 20 : 290);
        const estimatedHeight = 260;
        const viewportLeft = Math.max(10, Math.min(rect.left, window.innerWidth - width - 10));
        const viewportTop = rect.bottom + estimatedHeight > window.innerHeight && rect.top > estimatedHeight
            ? Math.max(10, rect.top - estimatedHeight - 7)
            : Math.min(window.innerHeight - 10, rect.bottom + 7);
        timePopover.style.position = dialog ? 'absolute' : 'fixed';
        timePopover.style.width = `${width}px`;
        timePopover.style.left = `${dialog ? viewportLeft - hostRect.left : viewportLeft}px`;
        timePopover.style.top = `${dialog ? viewportTop - hostRect.top : viewportTop}px`;
    }

    function openTimePopover(wrapper) {
        const trigger = qs('[data-time-trigger]', wrapper);
        if (!trigger || trigger.disabled) return;
        closeDatePopover();
        if (activeTimePicker && activeTimePicker !== wrapper) closeTimePopover();
        activeTimePicker = wrapper;
        const [hour, minute] = normalizedTimeValue(qs('[data-time-value]', wrapper)?.value).split(':').map(Number);
        selectedTimeHour = hour;
        selectedTimeMinute = minute;
        const container = wrapper.closest('dialog') || document.body;
        container.append(timePopover);
        timePopover.classList.remove('is-hidden');
        trigger.setAttribute('aria-expanded', 'true');
        renderTimePicker();
        positionTimePopover();
        timeHourSelect.focus();
    }

    function closeTimePopover() {
        if (activeTimePicker) qs('[data-time-trigger]', activeTimePicker)?.setAttribute('aria-expanded', 'false');
        timePopover.classList.add('is-hidden');
        activeTimePicker = null;
    }

    function commitTimePicker() {
        if (!activeTimePicker) return;
        const hidden = qs('[data-time-value]', activeTimePicker);
        hidden.value = `${padNumber(selectedTimeHour)}:${padNumber(selectedTimeMinute)}`;
        syncTimePicker(activeTimePicker);
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        closeTimePopover();
    }

    qsa('[data-time-picker]').forEach((wrapper) => {
        syncTimePicker(wrapper);
        qs('[data-time-trigger]', wrapper)?.addEventListener('click', () => openTimePopover(wrapper));
    });
    timeHourSelect.addEventListener('change', () => { selectedTimeHour = Number(timeHourSelect.value); renderTimePicker(); });
    timeMinuteSelect.addEventListener('change', () => { selectedTimeMinute = Number(timeMinuteSelect.value); renderTimePicker(); });
    timePopover.addEventListener('click', (event) => {
        const quick = event.target.closest('[data-quick-time]');
        if (quick) {
            [selectedTimeHour, selectedTimeMinute] = quick.dataset.quickTime.split(':').map(Number);
            renderTimePicker();
        } else if (event.target.closest('[data-time-apply]')) {
            selectedTimeHour = Number(timeHourSelect.value);
            selectedTimeMinute = Number(timeMinuteSelect.value);
            commitTimePicker();
        } else if (event.target.closest('[data-time-cancel]')) {
            closeTimePopover();
        }
    });
    document.addEventListener('click', (event) => {
        if (activeTimePicker && !activeTimePicker.contains(event.target) && !timePopover.contains(event.target)) closeTimePopover();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeTimePopover(); });
    window.addEventListener('resize', positionTimePopover);
    document.addEventListener('scroll', positionTimePopover, true);
    qsa('form').forEach((form) => form.addEventListener('reset', () => window.setTimeout(() => syncAllTimePickers(form))));

    qsa('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) closeDialog(dialog);
        });
        qsa('[data-dialog-close]', dialog).forEach((button) => button.addEventListener('click', () => closeDialog(dialog)));
    });

    // Sidebar state and mobile drawer.
    const shell = qs('#appShell');
    const sidebarCollapse = qs('#sidebarCollapse');
    const mobileMenu = qs('#mobileMenuButton');
    const sidebarOverlay = qs('#sidebarOverlay');

    function syncSidebarToggle() {
        if (!shell || !sidebarCollapse) return;
        const collapsed = shell.classList.contains('is-collapsed');
        const label = collapsed ? 'Развернуть меню' : 'Свернуть меню';
        sidebarCollapse.setAttribute('aria-label', label);
        sidebarCollapse.setAttribute('title', label);
        sidebarCollapse.setAttribute('aria-expanded', String(!collapsed));
    }

    function applySidebarForViewport() {
        if (!shell) return;
        if (window.innerWidth > 960) {
            try {
                shell.classList.toggle('is-collapsed', localStorage.getItem('crm-sidebar-collapsed') === '1');
            } catch (_) {
                // Keep navigation and settings usable when browser storage is unavailable.
            }
            shell.classList.remove('is-mobile-open');
        } else {
            shell.classList.remove('is-collapsed');
        }
        syncSidebarToggle();
    }

    applySidebarForViewport();
    sidebarCollapse?.addEventListener('click', () => {
        shell.classList.toggle('is-collapsed');
        try {
            localStorage.setItem('crm-sidebar-collapsed', shell.classList.contains('is-collapsed') ? '1' : '0');
        } catch (_) {
            // The menu still works for the current page without persistence.
        }
        syncSidebarToggle();
    });
    mobileMenu?.addEventListener('click', () => shell.classList.add('is-mobile-open'));
    sidebarOverlay?.addEventListener('click', () => shell.classList.remove('is-mobile-open'));
    window.addEventListener('resize', applySidebarForViewport);

    qsa('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            button.classList.toggle('is-active', input.type === 'text');
        });
    });

    // Create a student directly from the selected class, matching the class browser workflow.
    const classStudentModal = qs('#classStudentModal');
    const classStudentForm = qs('#classStudentForm');
    const classStudentError = qs('#classStudentFormError');
    const classStudentPhoto = qs('[data-class-student-photo]');
    const classStudentPhotoPreview = qs('[data-class-student-photo-preview]');
    const classStudentPhotoPlaceholder = qs('[data-class-student-photo-placeholder]');
    const classStudentPhotoClear = qs('[data-class-student-photo-clear]');
    let classStudentPhotoUrl = '';

    function generatedStudentPassword() {
        const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        const values = new Uint32Array(11);
        crypto.getRandomValues(values);
        return `A9!${Array.from(values, (number) => alphabet[number % alphabet.length]).join('')}`;
    }

    function clearClassStudentPhoto() {
        if (classStudentPhotoUrl) URL.revokeObjectURL(classStudentPhotoUrl);
        classStudentPhotoUrl = '';
        if (classStudentPhoto) classStudentPhoto.value = '';
        if (classStudentPhotoPreview) {
            classStudentPhotoPreview.src = '';
            classStudentPhotoPreview.hidden = true;
        }
        classStudentPhotoPlaceholder?.removeAttribute('hidden');
        classStudentPhotoClear?.classList.add('is-hidden');
    }

    function clearClassStudentErrors() {
        qsa('.field.has-error', classStudentForm).forEach((field) => field.classList.remove('has-error'));
        qsa('[data-class-student-error]', classStudentForm).forEach((error) => { error.textContent = ''; });
        classStudentError?.classList.add('is-hidden');
        if (classStudentError) classStudentError.textContent = '';
    }

    function resetClassStudentForm() {
        if (!classStudentForm) return;
        setFormLoading(classStudentForm, false, qs('#classStudentSubmit'));
        classStudentForm.reset();
        clearClassStudentErrors();
        clearClassStudentPhoto();
        syncAllDatePickers(classStudentForm);
        const login = qs('[name="login"]', classStudentForm);
        const password = qs('[name="password"]', classStudentForm);
        if (login) login.value = `student.${Date.now().toString(36)}`;
        if (password) password.value = generatedStudentPassword();
        syncPhoneMasks(classStudentForm);
    }

    qsa('[data-class-student-create]').forEach((button) => button.addEventListener('click', () => {
        resetClassStudentForm();
        openDialog(classStudentModal);
        window.setTimeout(() => qs('[name="last_name"]', classStudentForm)?.focus(), 80);
    }));

    qs('[data-class-student-password]')?.addEventListener('click', () => {
        const password = qs('[name="password"]', classStudentForm);
        if (!password) return;
        password.value = generatedStudentPassword();
        password.type = 'text';
        password.focus();
        password.select();
    });

    classStudentPhoto?.addEventListener('change', () => {
        const file = classStudentPhoto.files?.[0];
        if (!file) {
            clearClassStudentPhoto();
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            clearClassStudentPhoto();
            if (classStudentError) {
                classStudentError.textContent = 'Фото должно быть JPG, PNG или WEBP и не больше 5 МБ.';
                classStudentError.classList.remove('is-hidden');
            }
            return;
        }
        if (classStudentPhotoUrl) URL.revokeObjectURL(classStudentPhotoUrl);
        classStudentPhotoUrl = URL.createObjectURL(file);
        classStudentPhotoPreview.src = classStudentPhotoUrl;
        classStudentPhotoPreview.hidden = false;
        classStudentPhotoPlaceholder?.setAttribute('hidden', '');
        classStudentPhotoClear?.classList.remove('is-hidden');
    });

    classStudentPhotoClear?.addEventListener('click', clearClassStudentPhoto);

    classStudentForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearClassStudentErrors();
        if (!classStudentForm.reportValidity()) return;
        const submit = qs('#classStudentSubmit');
        setFormLoading(classStudentForm, true, submit);
        try {
            const data = await request(classStudentForm.action, { method: 'POST', body: new FormData(classStudentForm) });
            closeDialog(classStudentModal);
            toast(data.message);
            window.setTimeout(() => window.location.assign(data.redirect), 350);
        } catch (error) {
            const errors = error.data?.errors || {};
            Object.entries(errors).forEach(([name, message]) => {
                const fieldError = qs(`[data-class-student-error="${name}"]`, classStudentForm);
                if (fieldError) {
                    fieldError.textContent = message;
                    fieldError.closest('.field')?.classList.add('has-error');
                }
            });
            if (classStudentError) {
                classStudentError.textContent = error.message;
                classStudentError.classList.remove('is-hidden');
            }
        } finally {
            setFormLoading(classStudentForm, false, submit);
        }
    });

    // User management.
    const userModal = qs('#userModal');
    const userForm = qs('#userForm');
    const passwordField = qs('#passwordField');
    const userFormError = qs('#userFormError');

    function clearUserErrors() {
        qsa('.field.has-error', userForm).forEach((field) => field.classList.remove('has-error'));
        qsa('[data-error-for]', userForm).forEach((error) => { error.textContent = ''; });
        userFormError?.classList.add('is-hidden');
        if (userFormError) userFormError.textContent = '';
    }

    function fillUserForm(user = null) {
        if (!userForm) return;
        setFormLoading(userForm, false);
        userForm.reset();
        clearUserErrors();
        const editing = Boolean(user);
        const noun = userForm.dataset.userGroup === 'students' ? 'курсанта' : 'сотрудника';
        userForm.dataset.mode = editing ? 'edit' : 'create';
        userForm.dataset.userId = editing ? String(user.id) : '';
        qs('#userModalEyebrow').textContent = editing ? 'Карточка пользователя' : 'Новая учётная запись';
        qs('#userModalTitle').textContent = editing ? `Редактировать ${noun}` : `Добавить ${noun}`;
        qs('#userModalSubtitle').textContent = editing ? 'Изменения применятся сразу после сохранения.' : 'Доступ можно заблокировать в любой момент.';
        qs('#userFormSubmit').textContent = editing ? 'Сохранить изменения' : `Создать ${noun}`;
        passwordField?.classList.toggle('is-hidden', editing);
        qs('#restartAccessField')?.classList.toggle('is-hidden', !editing);
        const passwordInput = qs('[name="password"]', userForm);
        if (passwordInput) passwordInput.required = !editing;
        if (editing) {
            ['last_name', 'first_name', 'middle_name', 'role', 'login', 'phone', 'email', 'access_months'].forEach((name) => {
                const input = qs(`[name="${name}"]`, userForm);
                if (input) input.value = user[name] ?? '';
            });
        }
        syncPhoneMasks(userForm);
    }

    qsa('[data-user-create]').forEach((button) => button.addEventListener('click', () => {
        fillUserForm();
        openDialog(userModal);
    }));

    qsa('[data-user-edit]').forEach((button) => button.addEventListener('click', () => {
        const row = button.closest('[data-user-row]');
        fillUserForm(JSON.parse(row.dataset.user));
        openDialog(userModal);
    }));

    qsa('[data-class-student-edit]').forEach((row) => row.addEventListener('dblclick', (event) => {
        if (event.target.closest('button, a, input, select, textarea, form')) return;
        fillUserForm(JSON.parse(row.dataset.user));
        openDialog(userModal);
    }));

    qs('#generatePassword')?.addEventListener('click', () => {
        const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        const values = new Uint32Array(11);
        crypto.getRandomValues(values);
        let value = 'A9!';
        values.forEach((number) => { value += alphabet[number % alphabet.length]; });
        const input = qs('#createPassword');
        input.value = value;
        input.type = 'text';
        input.focus();
        input.select();
    });

    userForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearUserErrors();
        const submit = qs('#userFormSubmit');
        setFormLoading(userForm, true, submit);
        const editing = userForm.dataset.mode === 'edit';
        const endpoint = editing ? path(`/users/${userForm.dataset.userId}/update`) : path('/users');
        try {
            const data = await request(endpoint, { method: 'POST', body: new FormData(userForm) });
            closeDialog(userModal);
            toast(data.message);
            window.setTimeout(() => window.location.reload(), 650);
        } catch (error) {
            const errors = error.data?.errors || {};
            Object.entries(errors).forEach(([name, message]) => {
                const fieldError = qs(`[data-error-for="${name}"]`, userForm);
                if (fieldError) {
                    fieldError.textContent = message;
                    fieldError.closest('.field')?.classList.add('has-error');
                }
            });
            if (userFormError) {
                userFormError.textContent = error.message;
                userFormError.classList.remove('is-hidden');
            }
        } finally {
            setFormLoading(userForm, false, submit);
        }
    });

    const confirmModal = qs('#confirmModal');
    let confirmCallback = null;
    function confirmAction({ title, text, label, danger = false, callback }) {
        qs('#confirmTitle').textContent = title;
        qs('#confirmText').textContent = text;
        const button = qs('#confirmAction');
        button.textContent = label;
        button.className = `button ${danger ? 'button--danger' : 'button--primary'}`;
        qs('#confirmIcon')?.classList.toggle('is-danger', danger);
        confirmCallback = callback;
        openDialog(confirmModal);
    }
    qs('#confirmAction')?.addEventListener('click', async (event) => {
        if (!confirmCallback) return;
        event.currentTarget.disabled = true;
        try {
            const result = await confirmCallback();
            closeDialog(confirmModal);
            if (result.message) toast(result.message);
            if (result.reload !== false) window.setTimeout(() => window.location.reload(), 650);
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            event.currentTarget.disabled = false;
            confirmCallback = null;
        }
    });

    qsa('[data-user-status]').forEach((button) => button.addEventListener('click', () => {
        const user = JSON.parse(button.closest('[data-user-row]').dataset.user);
        const willBlock = user.status === 'active';
        confirmAction({
            title: willBlock ? 'Заблокировать доступ?' : 'Разблокировать доступ?',
            text: willBlock ? `${user.full_name} не сможет войти в систему до разблокировки.` : `${user.full_name} снова сможет войти в CRM.`,
            label: willBlock ? 'Заблокировать' : 'Разблокировать',
            danger: willBlock,
            callback: () => {
                const form = new FormData();
                form.append('status', willBlock ? 'blocked' : 'active');
                return request(path(`/users/${user.id}/status`), { method: 'POST', body: form });
            },
        });
    }));

    qsa('[data-user-delete]').forEach((button) => button.addEventListener('click', () => {
        const user = JSON.parse(button.closest('[data-user-row]').dataset.user);
        confirmAction({
            title: 'Удалить пользователя?',
            text: `${user.full_name} будет удалён из активного списка. Это действие нельзя отменить через интерфейс.`,
            label: 'Удалить',
            danger: true,
            callback: () => request(path(`/users/${user.id}/delete`), { method: 'POST', body: new FormData() }),
        });
    }));

    const passwordResultModal = qs('#passwordResultModal');
    qsa('[data-user-reset]').forEach((button) => button.addEventListener('click', () => {
        const user = JSON.parse(button.closest('[data-user-row]').dataset.user);
        confirmAction({
            title: 'Создать новый пароль?',
            text: `Текущий пароль пользователя ${user.full_name} перестанет работать.`,
            label: 'Создать пароль',
            callback: async () => {
                const data = await request(path(`/users/${user.id}/reset-password`), { method: 'POST', body: new FormData() });
                closeDialog(confirmModal);
                qs('#passwordResult').textContent = data.password;
                qs('#passwordResultUser').textContent = data.user_name;
                window.setTimeout(() => openDialog(passwordResultModal), 190);
                return { message: '', reload: false };
            },
        });
    }));

    qs('#copyPassword')?.addEventListener('click', async () => {
        const value = qs('#passwordResult')?.textContent || '';
        try {
            await navigator.clipboard.writeText(value);
            toast('Пароль скопирован.');
        } catch (_) {
            const range = document.createRange();
            range.selectNodeContents(qs('#passwordResult'));
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
    });

    // Chat.
    const chatApp = qs('#chatApp');
    const conversationList = qs('#conversationList');
    const newChatModal = qs('#newChatModal');
    const activeConversationId = () => Number(chatApp?.dataset.conversationId || 0);
    qsa('[data-new-chat]').forEach((button) => button.addEventListener('click', () => openDialog(newChatModal)));
    qs('#chatBack')?.addEventListener('click', () => chatApp?.classList.remove('has-active-chat'));

    function bindSearch(input, itemsSelector, valueAttribute) {
        input?.addEventListener('input', () => {
            const term = input.value.trim().toLocaleLowerCase('ru');
            qsa(itemsSelector).forEach((item) => {
                item.classList.toggle('is-hidden', !(item.dataset[valueAttribute] || '').includes(term));
            });
        });
    }
    bindSearch(qs('#chatSearch'), '.conversation', 'conversationName');
    bindSearch(qs('#contactSearch'), '.contact-option', 'contactName');

    conversationList?.addEventListener('click', (event) => {
        const conversation = event.target.closest('.conversation');
        if (!conversation) return;
        const conversationId = Number(conversation.dataset.conversationId || 0);
        if (conversationId !== activeConversationId()) return;

        event.preventDefault();
        clearConversationUnread(conversationId);
        messageInput?.focus();
    });

    qsa('[data-contact-id]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        const form = new FormData();
        form.append('contact_id', button.dataset.contactId);
        try {
            const data = await request(path('/chat/conversations'), { method: 'POST', body: form });
            window.location.href = data.redirect;
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    }));

    const messageContainer = qs('#chatMessages');
    const messageForm = qs('#messageForm');
    const messageInput = qs('#messageInput');
    let lastMessageId = 0;
    let polling = false;
    let messagePollTimer = 0;
    let unreadPollTimer = 0;
    let unreadSyncing = false;
    let knownUnreadCount = Number(qs('#navUnreadBadge')?.textContent || 0);

    function formatChatTime(value) {
        if (!value) return '';
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.valueOf())
            ? ''
            : parsed.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function applyGlobalUnread(count) {
        const normalized = Math.max(0, Number(count) || 0);
        const badge = qs('#navUnreadBadge');
        const dot = qs('#topUnreadDot');
        const messageLink = badge?.closest('.nav-item');
        const hasNew = normalized > knownUnreadCount;

        if (badge) {
            badge.textContent = String(normalized);
            badge.classList.toggle('is-hidden', normalized === 0);
            if (hasNew) {
                badge.classList.remove('has-new');
                void badge.offsetWidth;
                badge.classList.add('has-new');
            }
        }
        dot?.classList.toggle('is-hidden', normalized === 0);
        dot?.classList.toggle('has-new', hasNew);
        if (messageLink) {
            messageLink.setAttribute('aria-label', normalized > 0 ? `Сообщения: ${normalized} непрочитанных` : 'Сообщения');
        }
        knownUnreadCount = normalized;
    }

    function clearConversationUnread(conversationId) {
        const conversation = qs(`.conversation[data-conversation-id="${conversationId}"]`, conversationList);
        if (!conversation) return;
        const unread = Number(conversation.dataset.unreadCount || 0);
        conversation.dataset.unreadCount = '0';
        qs('b', conversation)?.remove();
        if (unread > 0) applyGlobalUnread(knownUnreadCount - unread);
    }

    function createConversationElement(conversation) {
        const link = document.createElement('a');
        link.className = 'conversation';
        link.href = path(`/chat?conversation=${conversation.id}`);
        link.dataset.conversationId = String(conversation.id);

        const avatar = document.createElement('span');
        avatar.className = 'avatar avatar--soft';
        avatar.textContent = conversation.initials || '';

        const content = document.createElement('span');
        content.className = 'conversation__content';
        const firstLine = document.createElement('span');
        firstLine.className = 'conversation__line';
        const name = document.createElement('strong');
        const time = document.createElement('time');
        firstLine.append(name, time);
        const secondLine = document.createElement('span');
        secondLine.className = 'conversation__line';
        const preview = document.createElement('small');
        secondLine.append(preview);
        content.append(firstLine, secondLine);
        link.append(avatar, content);
        return link;
    }

    function syncConversationList(conversations) {
        if (!conversationList || !Array.isArray(conversations)) return 0;
        const activeId = activeConversationId();
        const receivedIds = new Set();
        let activeUnread = 0;

        conversations.forEach((data) => {
            const id = Number(data.id);
            receivedIds.add(id);
            let conversation = qs(`.conversation[data-conversation-id="${id}"]`, conversationList);
            if (!conversation) conversation = createConversationElement(data);

            const reportedUnread = Number(data.unread_count || 0);
            if (id === activeId) activeUnread = reportedUnread;
            const unread = id === activeId ? 0 : reportedUnread;
            conversation.classList.toggle('is-active', id === activeId);
            conversation.dataset.conversationName = String(data.name || '').toLocaleLowerCase('ru');
            conversation.dataset.unreadCount = String(unread);
            qs('.avatar', conversation).textContent = data.initials || '';
            qs('strong', conversation).textContent = data.name || '';
            qs('time', conversation).textContent = formatChatTime(data.last_message_at);
            qs('small', conversation).textContent = data.preview || '';

            const line = qsa('.conversation__line', conversation)[1];
            let unreadBadge = qs('b', conversation);
            if (unread > 0) {
                if (!unreadBadge) {
                    unreadBadge = document.createElement('b');
                    line?.append(unreadBadge);
                }
                unreadBadge.textContent = String(unread);
            } else {
                unreadBadge?.remove();
            }
            conversationList.append(conversation);
        });

        qsa('.conversation', conversationList).forEach((conversation) => {
            if (!receivedIds.has(Number(conversation.dataset.conversationId))) conversation.remove();
        });
        if (conversations.length > 0) qs('.chat-list__empty', conversationList)?.remove();
        return activeUnread;
    }

    function updateConversationPreview(message) {
        const conversation = qs(`.conversation[data-conversation-id="${message.conversation_id}"]`, conversationList);
        if (!conversation) return;
        qs('small', conversation).textContent = message.body;
        qs('time', conversation).textContent = formatChatTime(message.created_at);
        conversationList.prepend(conversation);
    }

    function messageElement(message) {
        const own = Number(message.sender_id) === Number(body.dataset.userId);
        const wrapper = document.createElement('div');
        wrapper.className = `message${own ? ' is-own' : ''}`;
        wrapper.dataset.messageId = String(message.id);
        const bubble = document.createElement('div');
        bubble.className = 'message__bubble';
        bubble.textContent = message.body;
        const meta = document.createElement('span');
        meta.className = 'message__meta';
        meta.textContent = formatChatTime(message.created_at);
        wrapper.append(bubble, meta);
        return wrapper;
    }

    async function loadMessages(initial = false) {
        const conversationId = Number(chatApp?.dataset.conversationId || 0);
        if (!conversationId || polling) return;
        polling = true;
        const nearBottom = messageContainer ? messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight < 120 : false;
        try {
            const data = await request(path(`/chat/messages?conversation_id=${conversationId}&after_id=${initial ? 0 : lastMessageId}`));
            if (initial) messageContainer.innerHTML = '';
            const appended = [];
            data.messages.forEach((message) => {
                if (Number(message.id) <= lastMessageId && !initial) return;
                messageContainer.append(messageElement(message));
                lastMessageId = Math.max(lastMessageId, Number(message.id));
                appended.push(message);
            });
            if (initial && data.messages.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'chat-placeholder';
                empty.innerHTML = '<div class="chat-placeholder__icon"><svg class="icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg></div><h3>Начните диалог</h3><p>Первое сообщение появится здесь.</p>';
                empty.dataset.emptyChat = '1';
                messageContainer.append(empty);
            }
            if (initial || nearBottom) messageContainer.scrollTop = messageContainer.scrollHeight;
            clearConversationUnread(conversationId);
            if (appended.length > 0) updateConversationPreview(appended[appended.length - 1]);
            updateUnread();
        } catch (error) {
            if (initial) {
                messageContainer.innerHTML = '';
                toast(error.message, 'error');
            }
        } finally {
            polling = false;
        }
    }

    function scheduleMessagePoll(delay = document.hidden ? 8000 : 2200) {
        if (!messageContainer) return;
        window.clearTimeout(messagePollTimer);
        messagePollTimer = window.setTimeout(async () => {
            await loadMessages(false);
            scheduleMessagePoll();
        }, delay);
    }

    if (messageContainer) {
        loadMessages(true).finally(() => scheduleMessagePoll());
    }

    function resizeComposer() {
        if (!messageInput) return;
        messageInput.style.height = 'auto';
        messageInput.style.height = `${Math.min(messageInput.scrollHeight, 120)}px`;
    }
    messageInput?.addEventListener('input', resizeComposer);
    messageInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            messageForm.requestSubmit();
        }
    });
    messageForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const value = messageInput.value.trim();
        if (!value) return;
        const submit = qs('.composer-send', messageForm);
        submit.disabled = true;
        try {
            const data = await request(path('/chat/messages'), { method: 'POST', body: new FormData(messageForm) });
            qs('[data-empty-chat]', messageContainer)?.remove();
            messageContainer.append(messageElement(data.message));
            lastMessageId = Math.max(lastMessageId, Number(data.message.id));
            updateConversationPreview(data.message);
            messageInput.value = '';
            resizeComposer();
            messageContainer.scrollTop = messageContainer.scrollHeight;
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            submit.disabled = false;
            messageInput.focus();
        }
    });

    function scheduleUnreadPoll(delay = document.hidden ? 10000 : 2800) {
        if (!body.dataset.userId) return;
        window.clearTimeout(unreadPollTimer);
        unreadPollTimer = window.setTimeout(updateUnread, delay);
    }

    async function updateUnread() {
        if (!body.dataset.userId || unreadSyncing) return;
        unreadSyncing = true;
        try {
            const data = await request(path('/chat/unread'));
            const activeUnread = syncConversationList(data.conversations);
            applyGlobalUnread(Number(data.count) - activeUnread);
            if (activeUnread > 0 && messageContainer) {
                loadMessages(false);
            }
        } catch (_) {
            // The continuous sync loop retries automatically.
        } finally {
            unreadSyncing = false;
            scheduleUnreadPoll();
        }
    }
    if (body.dataset.userId) updateUnread();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            updateUnread();
            if (messageContainer) {
                window.clearTimeout(messagePollTimer);
                loadMessages(false).finally(() => scheduleMessagePoll());
            }
        }
    });
    window.addEventListener('online', updateUnread);

    // Stage two forms and dialogs.
    function setEditorMode(kind, record = null) {
        const form = qs(`#${kind}Form`);
        if (!form) return;
        form.reset();
        syncAllDatePickers(form);
        const editing = Boolean(record);
        form.action = editing
            ? form.dataset.updateTemplate.replace('__id__', String(record.id))
            : form.dataset.createAction;

        if (editing) {
            Object.entries(record).forEach(([name, value]) => {
                const input = qs(`[name="${name}"]`, form);
                if (!input || value === null) return;
                if (input.matches('[data-date-value]')) {
                    input.value = normalizedPickerValue(value, input.closest('[data-date-picker]')?.dataset.withTime === '1');
                } else if (input.type === 'datetime-local') {
                    input.value = String(value).replace(' ', 'T').slice(0, 16);
                } else {
                    input.value = String(value);
                }
            });
        }
        syncAllDatePickers(form);
        syncPhoneMasks(form);

        const title = qs(`#${kind}ModalTitle`);
        const eyebrow = qs(`#${kind}ModalEyebrow`);
        const submit = qs(`#${kind}FormSubmit`);
        const labels = kind === 'lead'
            ? { create: 'Добавить лида', edit: 'Редактировать лида', save: 'Сохранить изменения', eyebrow: 'Карточка лида' }
            : { create: 'Оформить договор', edit: 'Редактировать договор', save: 'Сохранить изменения', eyebrow: 'Карточка договора' };
        if (title) title.textContent = editing ? labels.edit : labels.create;
        if (eyebrow) eyebrow.textContent = editing ? labels.eyebrow : 'Новая запись';
        if (submit) {
            setFormLoading(form, false, submit);
            const submitLabel = qs('[data-submit-label]', submit);
            if (submitLabel) submitLabel.textContent = editing ? labels.save : labels.create;
            else submit.textContent = editing ? labels.save : labels.create;
        }
    }

    qsa('[data-dialog-open]').forEach((button) => button.addEventListener('click', () => {
        const kind = button.dataset.recordCreate || button.dataset.recordEdit;
        if (kind) {
            let record = null;
            if (button.dataset.recordEdit) {
                try { record = JSON.parse(button.dataset.record || '{}'); } catch (_) { record = null; }
            }
            setEditorMode(kind, record);
        }
        openDialog(document.getElementById(button.dataset.dialogOpen));
    }));

    const contractForm = qs('#contractForm');
    if (contractForm?.dataset.autoOpen === '1') {
        setEditorMode('contract');
        openDialog(qs('#contractModal'));
    }

    // A pointer preview keeps the card crisp and supports touch as well as a mouse.
    qsa('[data-lead-board]').forEach((board) => {
        board.classList.add('is-draggable');
        const columns = qsa('[data-lead-status]', board);
        const announcement = qs('[data-lead-drag-announcement]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let pending = null;
        let dragging = null;
        let frame = 0;
        let suppressClickUntil = 0;

        function announce(message) {
            if (announcement) announcement.textContent = message;
        }

        function refreshColumns() {
            columns.forEach((column) => {
                const content = qs('.lead-column__body', column);
                const count = qsa('.lead-kanban-card', content).length;
                qs('.lead-column__header b', column).textContent = String(count);
                let empty = qs('.lead-column__empty', content);
                if (!empty) {
                    empty = document.createElement('p');
                    empty.className = 'lead-column__empty';
                    empty.textContent = 'Пока пусто';
                    content.append(empty);
                }
                empty.classList.toggle('is-hidden', count > 0);
            });
        }

        function selectColumn(column) {
            if (!dragging || dragging.target === column) return;
            dragging.target = column;
            columns.forEach((item) => {
                const selected = item === column;
                item.classList.toggle('is-drop-target', selected);
                qs('[data-drop-label]', item).textContent = item === dragging.source
                    ? 'Оставить на этом этапе'
                    : selected ? 'Отпустите, чтобы переместить' : 'Перетащите сюда';
            });
            if (column) announce(`Этап «${column.dataset.statusTitle}». ${dragging.keyboard ? 'Enter — переместить, Escape — отменить.' : 'Отпустите карточку для перемещения.'}`);
        }

        function columnAtPointer() {
            const element = document.elementFromPoint(dragging.x, dragging.y);
            const column = element?.closest('[data-lead-status]');
            return column && board.contains(column) ? column : null;
        }

        function renderDrag() {
            if (!dragging || dragging.keyboard) return;
            const drag = dragging;
            drag.preview.style.transform = `translate3d(${drag.x - drag.offsetX}px, ${drag.y - drag.offsetY}px, 0) rotate(2deg) scale(1.025)`;
            const rect = board.getBoundingClientRect();
            const left = Math.max(rect.left, 0);
            const right = Math.min(rect.right, window.innerWidth);
            // Keep off-screen stages reachable while holding a card at the board edge.
            if (drag.y >= rect.top && drag.y <= rect.bottom && drag.x >= left - 20 && drag.x <= right + 20) {
                const edge = 56;
                const velocity = drag.x < left + edge ? -Math.min(1, (left + edge - drag.x) / edge)
                    : drag.x > right - edge ? Math.min(1, (drag.x - right + edge) / edge) : 0;
                board.scrollLeft += velocity * 12;
            }
            if (drag.x >= left && drag.x <= right) {
                const edge = 64;
                const velocity = drag.y < edge ? -Math.max(0, (edge - drag.y) / edge)
                    : drag.y > window.innerHeight - edge ? Math.max(0, (drag.y - window.innerHeight + edge) / edge) : 0;
                if (velocity) window.scrollBy({ top: Math.max(-12, Math.min(12, velocity * 12)), behavior: 'instant' });
            }
            selectColumn(columnAtPointer());
            frame = window.requestAnimationFrame(renderDrag);
        }

        function beginDrag(card, pointer = null) {
            const rect = card.getBoundingClientRect();
            const source = card.closest('[data-lead-status]');
            const handle = qs('[data-lead-drag-handle]', card);
            dragging = {
                card, source, handle, target: null, keyboard: !pointer,
                sourceBody: card.parentElement, next: card.nextSibling,
                scrollTops: columns.map((column) => qs('.lead-column__body', column).scrollTop),
                pointerId: pointer?.pointerId, x: pointer?.x, y: pointer?.y,
                offsetX: pointer ? pointer.startX - rect.left : 0,
                offsetY: pointer ? pointer.startY - rect.top : 0,
            };
            if (pointer) {
                const preview = card.cloneNode(true);
                preview.removeAttribute('id');
                qsa('[id]', preview).forEach((element) => element.removeAttribute('id'));
                preview.classList.add('lead-kanban-card--preview');
                preview.style.width = `${rect.width}px`;
                preview.setAttribute('aria-hidden', 'true');
                preview.inert = true;
                body.append(preview);
                dragging.preview = preview;
                handle.setPointerCapture(pointer.pointerId);
            }
            board.style.setProperty('--lead-slot-height', `${Math.max(130, Math.min(rect.height, 210))}px`);
            board.classList.add('is-dragging');
            body.classList.add('is-dragging-lead');
            card.classList.add('is-dragging');
            handle.setAttribute('aria-pressed', 'true');
            columns.forEach((column) => { qs('.lead-column__body', column).scrollTop = 0; });
            selectColumn(source);
            if (pointer) renderDrag();
        }

        function settlePreview(preview, card) {
            if (!preview) return;
            if (reducedMotion.matches || !preview.animate) {
                preview.remove();
                return;
            }
            const rect = card.getBoundingClientRect();
            preview.animate([
                { transform: preview.style.transform, opacity: 1 },
                { transform: `translate3d(${rect.left}px, ${rect.top}px, 0) rotate(0) scale(1)`, opacity: 0 },
            ], { duration: 190, easing: 'cubic-bezier(.2,.8,.2,1)' }).finished.finally(() => preview.remove());
        }

        async function saveMove(drag, destination) {
            const { card, sourceBody, next, source, handle } = drag;
            const content = qs('.lead-column__body', destination);
            qs('.lead-drop-slot', content).after(card);
            content.scrollTop = 0;
            card.classList.add('is-saving');
            card.setAttribute('aria-busy', 'true');
            card.inert = true;
            refreshColumns();
            settlePreview(drag.preview, card);
            announce(`Сохраняем этап «${destination.dataset.statusTitle}»…`);
            try {
                const data = await request(path(`/crm/leads/${card.dataset.leadId}/status`), {
                    method: 'POST',
                    body: new URLSearchParams({ status: destination.dataset.leadStatus, from_status: source.dataset.leadStatus }),
                });
                qs('[data-record-edit="lead"]', card).dataset.record = JSON.stringify(data.lead);
                qs('[data-lead-contract]', card).classList.toggle('is-hidden', ['contract', 'refused'].includes(data.lead.status));
                const message = `Лид перемещён на этап «${destination.dataset.statusTitle}».`;
                announce(message);
                toast(message);
            } catch (error) {
                sourceBody.insertBefore(card, next?.parentElement === sourceBody ? next : null);
                sourceBody.scrollTop = drag.scrollTops[columns.indexOf(source)];
                refreshColumns();
                announce(error.message);
                toast(error.message, 'error');
            } finally {
                card.classList.remove('is-saving');
                card.removeAttribute('aria-busy');
                card.inert = false;
                if (drag.keyboard) handle.focus({ preventScroll: true });
            }
        }

        function finishDrag(commit = false) {
            pending = null;
            if (!dragging) return;
            const drag = dragging;
            dragging = null;
            window.cancelAnimationFrame(frame);
            suppressClickUntil = performance.now() + 350;
            board.classList.remove('is-dragging');
            board.style.removeProperty('--lead-slot-height');
            body.classList.remove('is-dragging-lead');
            drag.card.classList.remove('is-dragging');
            drag.handle.setAttribute('aria-pressed', 'false');
            columns.forEach((column, index) => {
                column.classList.remove('is-drop-target');
                qs('.lead-column__body', column).scrollTop = drag.scrollTops[index];
            });
            if (drag.pointerId !== undefined && drag.handle.hasPointerCapture(drag.pointerId)) {
                drag.handle.releasePointerCapture(drag.pointerId);
            }
            if (commit && drag.target && drag.target !== drag.source) {
                void saveMove(drag, drag.target);
            } else {
                settlePreview(drag.preview, drag.card);
                announce('Перемещение отменено.');
                if (drag.keyboard) drag.handle.focus({ preventScroll: true });
            }
        }

        board.addEventListener('pointerdown', (event) => {
            if (event.button !== 0 || !event.isPrimary || dragging) return;
            const card = event.target.closest('.lead-kanban-card');
            const handle = event.target.closest('[data-lead-drag-handle]');
            if (!card || card.classList.contains('is-saving')) return;
            if (!handle && (event.pointerType !== 'mouse' || event.target.closest('button, a, input, select, textarea, form'))) return;
            event.preventDefault();
            pending = { card, pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, x: event.clientX, y: event.clientY };
        });
        document.addEventListener('pointermove', (event) => {
            if (pending && pending.pointerId === event.pointerId) {
                pending.x = event.clientX;
                pending.y = event.clientY;
                if (Math.hypot(pending.x - pending.startX, pending.y - pending.startY) < 6) return;
                beginDrag(pending.card, pending);
                pending = null;
            }
            if (!dragging || dragging.pointerId !== event.pointerId) return;
            event.preventDefault();
            dragging.x = event.clientX;
            dragging.y = event.clientY;
        }, { passive: false });
        document.addEventListener('pointerup', (event) => {
            if (pending?.pointerId === event.pointerId) pending = null;
            if (!dragging || dragging.pointerId !== event.pointerId) return;
            dragging.x = event.clientX;
            dragging.y = event.clientY;
            selectColumn(columnAtPointer());
            finishDrag(true);
        });
        document.addEventListener('pointercancel', (event) => {
            if (pending?.pointerId === event.pointerId || dragging?.pointerId === event.pointerId) finishDrag();
        });
        board.addEventListener('lostpointercapture', (event) => {
            // Touch may first capture an SVG dot, then transfer capture to its button.
            if (dragging?.pointerId === event.pointerId && event.target === dragging.handle) finishDrag();
        });
        board.addEventListener('dragstart', (event) => {
            if (event.target.closest('.lead-kanban-card')) event.preventDefault();
        });
        board.addEventListener('click', (event) => {
            if (performance.now() < suppressClickUntil) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            const handle = event.target.closest('[data-lead-drag-handle]');
            if (handle && !dragging) beginDrag(handle.closest('.lead-kanban-card'));
            else if (dragging?.keyboard && event.target.closest('.lead-drop-slot')) {
                selectColumn(event.target.closest('[data-lead-status]'));
                finishDrag(true);
            }
        }, true);
        document.addEventListener('keydown', (event) => {
            const handle = event.target.closest('[data-lead-drag-handle]');
            if (!dragging && handle && board.contains(handle) && ['Enter', ' '].includes(event.key)) {
                event.preventDefault();
                if (!event.repeat) beginDrag(handle.closest('.lead-kanban-card'));
                return;
            }
            if (event.key === 'Escape' && (dragging || pending)) {
                event.preventDefault();
                finishDrag();
                return;
            }
            if (!dragging?.keyboard) return;
            if (['ArrowLeft', 'ArrowRight', 'Enter', ' ', 'Tab'].includes(event.key)) event.preventDefault();
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                const index = columns.indexOf(dragging.target);
                const column = columns[Math.max(0, Math.min(columns.length - 1, index + (event.key === 'ArrowRight' ? 1 : -1)))];
                selectColumn(column);
                column.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reducedMotion.matches ? 'instant' : 'smooth' });
            } else if (event.key === 'Enter' || event.key === ' ') finishDrag(true);
            else if (event.key === 'Tab') finishDrag();
        });
        window.addEventListener('blur', () => finishDrag());
        document.addEventListener('visibilitychange', () => { if (document.hidden) finishDrag(); });
    });

    // Client card and CRM history are loaded only when requested.
    const leadDetailsModal = qs('#leadDetailsModal');
    async function openLeadDetails(leadId) {
        if (!leadDetailsModal) return;
        const history = qs('[data-lead-history]', leadDetailsModal);
        const loader = qs('[data-lead-history-loader]', leadDetailsModal);
        history.replaceChildren();
        loader.textContent = 'Загрузка…';
        loader.classList.remove('is-hidden');
        openDialog(leadDetailsModal);
        try {
            const data = await request(path(`/crm/leads/${leadId}/history`));
            const lead = data.lead || {};
            qs('[data-lead-details-name]', leadDetailsModal).textContent = lead.name || 'Клиент';
            qs('[data-lead-details-direction]', leadDetailsModal).textContent = lead.direction || 'Карточка клиента';
            qs('[data-lead-details-status]', leadDetailsModal).textContent = `Этап: ${lead.status || '—'}`;
            const phone = qs('[data-lead-details-phone]', leadDetailsModal);
            phone.textContent = lead.phone || '—';
            phone.href = lead.phone ? `tel:${String(lead.phone).replace(/[^+0-9]/g, '')}` : '#';
            const email = qs('[data-lead-details-email]', leadDetailsModal);
            email.textContent = lead.email || '—';
            email.href = lead.email ? `mailto:${lead.email}` : '#';
            qs('[data-lead-details-manager]', leadDetailsModal).textContent = lead.manager || 'Не назначен';
            qs('[data-lead-details-source]', leadDetailsModal).textContent = lead.source || 'Не указан';
            const notes = qs('[data-lead-details-notes]', leadDetailsModal);
            notes.textContent = lead.notes || '';
            notes.classList.toggle('is-hidden', !lead.notes);

            if (!Array.isArray(data.events) || data.events.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'lead-history__empty';
                empty.textContent = 'История действий пока пуста.';
                history.append(empty);
            } else {
                data.events.forEach((event) => {
                    const item = document.createElement('article');
                    item.className = 'lead-history-item';
                    const top = document.createElement('div');
                    top.className = 'lead-history-item__top';
                    const title = document.createElement('strong');
                    title.textContent = event.title;
                    const time = document.createElement('time');
                    const parsed = new Date(String(event.created_at).replace(' ', 'T'));
                    time.textContent = Number.isNaN(parsed.valueOf()) ? '' : parsed.toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' });
                    top.append(title, time);
                    item.append(top);
                    if (event.description) {
                        const description = document.createElement('p');
                        description.textContent = event.description;
                        item.append(description);
                    }
                    const actor = document.createElement('small');
                    actor.textContent = `Сотрудник: ${event.actor}`;
                    item.append(actor);
                    history.append(item);
                });
            }
        } catch (error) {
            history.replaceChildren();
            const failed = document.createElement('p');
            failed.className = 'lead-history__empty';
            failed.textContent = error.message;
            history.append(failed);
        } finally {
            loader.classList.add('is-hidden');
        }
    }
    qsa('[data-lead-details]').forEach((button) => button.addEventListener('click', () => openLeadDetails(button.dataset.leadDetails)));
    const autoOpenLead = qs('[data-auto-open-lead]');
    if (autoOpenLead?.dataset.autoOpenLead) openLeadDetails(autoOpenLead.dataset.autoOpenLead);

    // Column visibility is saved separately for each signed-in employee.
    qsa('[data-column-settings]').forEach((settings) => {
        const table = qs('[data-contract-table]');
        const menu = qs('[data-column-settings-menu]', settings);
        const toggle = qs('[data-column-settings-toggle]', settings);
        const controls = qsa('[data-contract-column-toggle]', settings);
        const storageKey = `contract-journal-columns:${body.dataset.userId || 'anonymous'}`;
        let visible = controls.map((control) => control.value);
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (Array.isArray(saved)) visible = saved.filter((column) => controls.some((control) => control.value === column));
        } catch (_) {
            // Ignore malformed browser preferences and use the defaults.
        }
        function applyColumns() {
            controls.forEach((control) => { control.checked = visible.includes(control.value); });
            qsa('[data-contract-column]', table).forEach((cell) => cell.classList.toggle('is-hidden', !visible.includes(cell.dataset.contractColumn)));
        }
        controls.forEach((control) => control.addEventListener('change', () => {
            visible = controls.filter((item) => item.checked).map((item) => item.value);
            localStorage.setItem(storageKey, JSON.stringify(visible));
            applyColumns();
        }));
        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            menu.classList.toggle('is-hidden', expanded);
        });
        document.addEventListener('click', (event) => {
            if (!settings.contains(event.target)) {
                menu.classList.add('is-hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        applyColumns();
    });

    qsa('form[data-confirm-message]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirmMessage)) event.preventDefault();
    }));

    const taskRecurrence = qs('#taskRecurrence');
    const recurrenceFields = qs('#recurrenceFields');
    function syncRecurrence() {
        if (!taskRecurrence || !recurrenceFields) return;
        const enabled = taskRecurrence.checked;
        recurrenceFields.classList.toggle('is-hidden', !enabled);
        qsa('input', recurrenceFields).forEach((input) => { input.required = enabled; });
    }
    taskRecurrence?.addEventListener('change', syncRecurrence);
    syncRecurrence();

    // Task assignees are fetched on demand; no employee directory is embedded in the page.
    qsa('[data-assignee-picker]').forEach((picker) => {
        const input = qs('[data-assignee-search]', picker);
        const suggestions = qs('[data-assignee-suggestions]', picker);
        const selected = qs('[data-assignee-selected]', picker);
        const loader = qs('[data-assignee-loader]', picker);
        let timer = 0;
        let controller = null;

        function selectedIds() {
            return new Set(qsa('input[name="assignee_ids[]"]', selected).map((item) => Number(item.value)));
        }

        function closeSuggestions() {
            suggestions.classList.add('is-hidden');
            suggestions.replaceChildren();
            input.setAttribute('aria-expanded', 'false');
        }

        function addAssignee(person) {
            if (selectedIds().has(Number(person.id))) return;
            const chip = document.createElement('span');
            chip.className = 'assignee-chip';
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'assignee_ids[]';
            hidden.value = String(person.id);
            const avatar = document.createElement('span');
            avatar.className = 'avatar avatar--tiny';
            avatar.textContent = person.initials;
            const copy = document.createElement('span');
            const name = document.createElement('strong');
            const role = document.createElement('small');
            name.textContent = person.name;
            role.textContent = person.role;
            copy.append(name, role);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Убрать исполнителя ${person.name}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => chip.remove());
            chip.append(hidden, avatar, copy, remove);
            selected.append(chip);
            input.setCustomValidity('');
        }

        function renderSuggestions(items) {
            suggestions.replaceChildren();
            const excluded = selectedIds();
            const available = items.filter((person) => !excluded.has(Number(person.id)));
            if (available.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'assignee-suggestions__empty';
                empty.textContent = 'Подходящих сотрудников не найдено';
                suggestions.append(empty);
            } else {
                available.forEach((person) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'assignee-option';
                    option.setAttribute('role', 'option');
                    const avatar = document.createElement('span');
                    avatar.className = 'avatar avatar--tiny';
                    avatar.textContent = person.initials;
                    const copy = document.createElement('span');
                    const name = document.createElement('strong');
                    const role = document.createElement('small');
                    name.textContent = person.name;
                    role.textContent = person.role;
                    copy.append(name, role);
                    option.append(avatar, copy);
                    option.addEventListener('click', () => {
                        addAssignee(person);
                        input.value = '';
                        closeSuggestions();
                        input.focus();
                    });
                    suggestions.append(option);
                });
            }
            suggestions.classList.remove('is-hidden');
            input.setAttribute('aria-expanded', 'true');
        }

        async function searchAssignees() {
            const term = input.value.trim();
            if (term.length < 2) {
                controller?.abort();
                loader.classList.remove('is-loading');
                closeSuggestions();
                return;
            }
            controller?.abort();
            controller = new AbortController();
            loader.classList.add('is-loading');
            try {
                const data = await request(`${picker.dataset.endpoint}?q=${encodeURIComponent(term)}`, { signal: controller.signal });
                renderSuggestions(Array.isArray(data.items) ? data.items : []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    renderSuggestions([]);
                    toast(error.message, 'error');
                }
            } finally {
                loader.classList.remove('is-loading');
            }
        }

        input.setAttribute('aria-expanded', 'false');
        input.addEventListener('input', () => {
            input.setCustomValidity('');
            window.clearTimeout(timer);
            timer = window.setTimeout(searchAssignees, 260);
        });
        input.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSuggestions(); });
        document.addEventListener('click', (event) => { if (!picker.contains(event.target)) closeSuggestions(); });
        picker.closest('form')?.addEventListener('reset', () => window.setTimeout(() => {
            selected.replaceChildren();
            closeSuggestions();
        }));
    });

    qs('#taskForm')?.addEventListener('submit', (event) => {
        const picker = qs('[data-assignee-picker]', event.currentTarget);
        const input = qs('[data-assignee-search]', picker);
        if (qsa('input[name="assignee_ids[]"]', picker).length === 0) {
            event.preventDefault();
            input.setCustomValidity('Выберите хотя бы одного исполнителя.');
            input.reportValidity();
            input.focus();
        }
    });

    const studentTabs = qs('[data-student-tabs]');
    if (studentTabs) {
        const tabs = qsa('[data-student-tab]', studentTabs);
        const panels = qsa('[data-student-tab-panel]');
        const profileViews = qsa('[data-student-profile-view]');
        const allowedTabs = tabs.map((tab) => tab.dataset.studentTab);

        const tabFromHash = () => {
            const hash = decodeURIComponent(window.location.hash.slice(1));
            return allowedTabs.includes(hash) ? hash : 'main';
        };

        const activateStudentTab = (name, updateHistory = false) => {
            const active = allowedTabs.includes(name) ? name : 'main';
            tabs.forEach((tab) => {
                const selected = tab.dataset.studentTab === active;
                tab.classList.toggle('is-active', selected);
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
            });
            panels.forEach((panel) => {
                panel.hidden = !String(panel.dataset.studentTabPanel || '').split(/\s+/).includes(active);
            });
            profileViews.forEach((view) => {
                view.hidden = view.dataset.studentProfileView !== active;
            });
            qsa('[data-student-active-tab]').forEach((input) => {
                input.value = active === 'personal' ? 'personal' : 'main';
            });
            if (updateHistory && window.location.hash !== `#${active}`) {
                window.history.pushState(null, '', `#${active}`);
            }
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', (event) => {
                event.preventDefault();
                activateStudentTab(tab.dataset.studentTab, true);
            });
            tab.addEventListener('keydown', (event) => {
                let nextIndex = null;
                if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
                else if (event.key === 'Home') nextIndex = 0;
                else if (event.key === 'End') nextIndex = tabs.length - 1;
                if (nextIndex === null) return;
                event.preventDefault();
                tabs[nextIndex].focus();
                activateStudentTab(tabs[nextIndex].dataset.studentTab, true);
            });
        });
        window.addEventListener('hashchange', () => activateStudentTab(tabFromHash()));
        activateStudentTab(tabFromHash());
    }

    qsa('[data-file-input]').forEach((input) => {
        const summary = qs('[data-file-summary]', input.closest('.file-upload'));
        const defaultText = summary?.textContent || '';
        input.addEventListener('change', () => {
            if (!summary) return;
            const count = input.files?.length || 0;
            summary.textContent = count === 0 ? defaultText : (count === 1 ? input.files[0].name : `Выбрано файлов: ${count}`);
        });
        input.form?.addEventListener('reset', () => window.setTimeout(() => { if (summary) summary.textContent = defaultText; }));
    });

    qsa('[data-schedule-toggle]').forEach((toggle) => {
        const row = toggle.closest('.schedule-day');
        const picker = qs('[data-time-picker]', row);
        const time = qs('[data-time-value]', picker);
        const trigger = qs('[data-time-trigger]', picker);
        const sync = () => {
            if (!picker || !time || !trigger) return;
            time.disabled = !toggle.checked;
            trigger.disabled = !toggle.checked;
            picker.classList.toggle('is-disabled', !toggle.checked);
            if (!toggle.checked && activeTimePicker === picker) closeTimePopover();
        };
        toggle.addEventListener('change', sync);
        toggle.form?.addEventListener('reset', () => window.setTimeout(sync));
        sync();
    });

    qsa('form[action$="/sales"]').forEach((form) => {
        const service = qs('select[name="service_id"]', form);
        const amount = qs('input[name="amount"]', form);
        service?.addEventListener('change', () => {
            const option = service.options[service.selectedIndex];
            if (amount && option?.dataset.price) amount.value = option.dataset.price;
        });
    });

    qsa('form[data-submit-loading]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!event.defaultPrevented) setFormLoading(form, true, event.submitter);
    }));
    // Sales workflow opened from the class student editor.
    const salesDataNode = qs('#studentSalesData');
    if (salesDataNode) {
        const data = JSON.parse(salesDataNode.textContent);
        const salesDialog = qs('#studentSalesDialog');
        const wizard = qs('#saleWizard');
        const form = qs('#saleWizardForm');
        const trigger = qs('#studentSalesOpen');
        let studentId = '', selected = null, step = 0, installments = [];
        const money = (value) => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(Number(value) || 0) + ' ₽';
        const cell = (row, value) => { const td = document.createElement('td'); td.textContent = value; row.append(td); };
        const details = (service) => { try { return JSON.parse(service.service_data || '{}') || {}; } catch { return {}; } };
        const error = (message = '') => { const el = qs('[data-sale-error]', form); el.textContent = message; el.classList.toggle('is-hidden', !message); };
        function renderSales() {
            const rows = qs('[data-sales-rows]', salesDialog); rows.replaceChildren();
            const sales = data.sales[studentId] || []; let total = 0, paid = 0;
            sales.forEach((sale) => { const row = document.createElement('tr'); total += Number(sale.amount); paid += Number(sale.paid_total); [sale.sold_on, money(sale.amount), money(sale.paid_total), money(Number(sale.amount) - Number(sale.paid_total)), sale.service_name, sale.seller_name].forEach(v => cell(row, v)); rows.append(row); });
            if (!sales.length) { const row = document.createElement('tr'); const td = document.createElement('td'); td.colSpan = 6; td.textContent = 'Продаж пока нет'; row.append(td); rows.append(row); }
            qs('[data-sales-totals]', salesDialog).textContent = `Итого начислено: ${money(total)} · Итого оплачено: ${money(paid)} · Задолженность: ${money(Math.max(0, total - paid))}`;
        }
        trigger.addEventListener('click', () => {
            studentId = userForm.dataset.userId; if (!studentId) return;
            const name = ['last_name', 'first_name', 'middle_name'].map(n => userForm.elements[n].value).filter(Boolean).join(' ');
            qsa('[data-sales-student]').forEach(el => { el.textContent = name; });
            qs('[data-sales-profile]').href = path(`education/students/${studentId}#payments`);
            form.action = path(`education/students/${studentId}/sales`); renderSales(); openDialog(salesDialog);
        });
        // The action is meaningful only for an existing student.
        const syncTrigger = () => { trigger.hidden = userForm.dataset.mode !== 'edit'; };
        new MutationObserver(syncTrigger).observe(userForm, { attributes: true, attributeFilter: ['data-mode'] }); syncTrigger();
        qs('[data-sales-close]').addEventListener('click', () => closeDialog(salesDialog));
        qs('[data-wizard-close]').addEventListener('click', () => closeDialog(wizard));
        function renderServices() {
            const rows = qs('[data-service-rows]', form); rows.replaceChildren(); const query = qs('.sale-search', form).value.toLocaleLowerCase();
            data.services.filter(s => s.name.toLocaleLowerCase().includes(query)).forEach(service => {
                const row = document.createElement('tr'); row.dataset.serviceId = service.id; row.classList.toggle('is-selected', selected?.id === service.id);
                [service.name, money(service.price), details(service).main_activity ? '✓' : '—'].forEach(v => cell(row, v));
                row.addEventListener('click', () => { selected = service; qs('[name="service_id"]', form).value = service.id; qs('[data-tariff]', form).value = money(service.price); qs('[data-sale-amount]', form).value = service.price; renderServices(); error(); }); rows.append(row);
            });
            if (!rows.children.length) { const row = document.createElement('tr'); const td = document.createElement('td'); td.colSpan = 3; td.textContent = 'Услуги не найдены'; row.append(td); rows.append(row); }
        }
        function renderInstallments() {
            const rows = qs('[data-installment-rows]', form); rows.replaceChildren(); installments.forEach((item, index) => { const row = document.createElement('tr'); cell(row, item.date); cell(row, money(item.amount)); const td = document.createElement('td'); const button = document.createElement('button'); button.type = 'button'; button.className = 'button button--ghost'; button.textContent = 'Удалить'; button.addEventListener('click', () => { installments.splice(index, 1); renderInstallments(); }); td.append(button); row.append(td); rows.append(row); });
        }
        function renderStep() {
            qsa('[data-sale-step]', form).forEach(el => { el.hidden = Number(el.dataset.saleStep) !== step; });
            qsa('.sale-progress span', wizard).forEach((el, i) => el.classList.toggle('is-active', i === step));
            qs('[data-sale-back]', form).hidden = step === 0; qs('[data-sale-next]', form).hidden = step === 4; qs('[data-sale-finish]', form).hidden = step !== 4; error();
            if (step === 3) { const head = qs('[data-allocation-head]', form), rows = qs('[data-allocation-rows]', form); head.replaceChildren(); rows.replaceChildren(); const hr = document.createElement('tr'); const amountHeading = document.createElement('th'); amountHeading.textContent = 'Сумма оплаты'; hr.append(amountHeading); data.balances.forEach(b => { const th = document.createElement('th'), label = document.createElement('span'); th.title = b.name; label.className = 'balance-code'; label.style.setProperty('--chip-color', b.color); label.textContent = b.code; th.append(label); hr.append(th); }); head.append(hr); const allocations = details(selected).allocations || []; allocations.forEach(item => { const row = document.createElement('tr'); cell(row, money(item.amount)); data.balances.forEach(b => cell(row, item.balances?.[b.id] ?? '—')); rows.append(row); }); if (!allocations.length) { const row = document.createElement('tr'), td = document.createElement('td'); td.colSpan = data.balances.length + 1; td.textContent = 'Для этой услуги схема начислений не настроена.'; row.append(td); rows.append(row); } }
            if (step === 4) { const review = qs('[data-sale-review]', form); review.replaceChildren(); [`Услуга: ${selected.name}`, `Дата продажи: ${form.elements.sold_on.value}`, `Стоимость: ${money(qs('[data-sale-amount]', form).value)}`, 'После завершения продажи можно принять оплату в карточке курсанта.'].forEach(t => { const p = document.createElement('div'); p.textContent = t; review.append(p); }); }
        }
        qs('[data-sale-start]').addEventListener('click', () => { form.reset(); selected = null; installments = []; step = 0; renderServices(); renderInstallments(); renderStep(); openDialog(wizard); });
        qs('.sale-search', form).addEventListener('input', renderServices);
        qs('[data-sale-back]', form).addEventListener('click', () => { if (step > 0) { step--; renderStep(); } });
        qs('[data-sale-next]', form).addEventListener('click', () => {
            if (step === 0 && !selected) return error('Выберите услугу или товар.');
            if (step === 1) { const amount = qs('[data-sale-amount]', form); if (!form.elements.sold_on.value || !amount.validity.valid || Number(amount.value) <= 0) return error('Укажите дату и корректную сумму продажи.'); if (details(selected).no_discounts && Number(amount.value) < Number(selected.price)) return error('Для этой услуги скидки запрещены.'); qs('[name="amount"]', form).value = amount.value; }
            if (step === 2) {
                const total = installments.reduce((sum, item) => sum + Math.round(Number(item.amount) * 100), 0);
                const saleTotal = Math.round(Number(qs('[data-sale-amount]', form).value) * 100);
                if (total !== saleTotal) return error(`Сумма этапов оплаты (${money(total / 100)}) должна равняться сумме продажи (${money(saleTotal / 100)}).`);
            }
            step++; renderStep();
        });
        qs('[data-installment-add]', form).addEventListener('click', () => { const date = qs('[data-installment-date]', form), amount = qs('[data-installment-amount]', form); if (!date.value || !amount.validity.valid || Number(amount.value) <= 0) return error('Укажите дату и сумму этапа.'); installments.push({ date: date.value, amount: amount.value }); date.value = ''; amount.value = ''; error(); renderInstallments(); });
    }

})();
