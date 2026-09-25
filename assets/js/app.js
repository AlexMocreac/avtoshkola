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
            <strong data-calendar-title></strong>
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
        qs('[data-calendar-title]', datePopover).textContent = `${monthNames[calendarMonth.getMonth()]} ${calendarMonth.getFullYear()}`;
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
    hourSelect.addEventListener('change', () => { selectedHour = Number(hourSelect.value); });
    minuteSelect.addEventListener('change', () => { selectedMinute = Number(minuteSelect.value); });
    document.addEventListener('click', (event) => {
        if (activeDatePicker && !activeDatePicker.contains(event.target) && !datePopover.contains(event.target)) closeDatePopover();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeDatePopover(); });
    window.addEventListener('resize', positionDatePopover);
    document.addEventListener('scroll', positionDatePopover, true);
    qsa('form').forEach((form) => form.addEventListener('reset', () => window.setTimeout(() => syncAllDatePickers(form))));

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
            shell.classList.toggle('is-collapsed', localStorage.getItem('crm-sidebar-collapsed') === '1');
            shell.classList.remove('is-mobile-open');
        } else {
            shell.classList.remove('is-collapsed');
        }
        syncSidebarToggle();
    }

    applySidebarForViewport();
    sidebarCollapse?.addEventListener('click', () => {
        shell.classList.toggle('is-collapsed');
        localStorage.setItem('crm-sidebar-collapsed', shell.classList.contains('is-collapsed') ? '1' : '0');
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

    qsa('[data-file-input]').forEach((input) => {
        const summary = qs('[data-file-summary]', input.closest('.file-upload'));
        const defaultText = summary?.textContent || '';
        input.addEventListener('change', () => {
            if (!summary) return;
            const count = input.files?.length || 0;
            summary.textContent = count === 0 ? defaultText : `Выбрано файлов: ${count}`;
        });
        input.form?.addEventListener('reset', () => window.setTimeout(() => { if (summary) summary.textContent = defaultText; }));
    });

    qsa('form[data-submit-loading]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!event.defaultPrevented) setFormLoading(form, true, event.submitter);
    }));
})();
