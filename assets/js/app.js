(() => {
    'use strict';

    const body = document.body;
    const basePath = body.dataset.baseUrl || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const path = (value) => `${basePath}/${String(value).replace(/^\//, '')}`;
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

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
        userForm.reset();
        clearUserErrors();
        const editing = Boolean(user);
        userForm.dataset.mode = editing ? 'edit' : 'create';
        userForm.dataset.userId = editing ? String(user.id) : '';
        qs('#userModalEyebrow').textContent = editing ? 'Карточка пользователя' : 'Новая учётная запись';
        qs('#userModalTitle').textContent = editing ? 'Редактировать пользователя' : 'Добавить пользователя';
        qs('#userModalSubtitle').textContent = editing ? 'Изменения применятся сразу после сохранения.' : 'Доступ можно заблокировать в любой момент.';
        qs('#userFormSubmit').textContent = editing ? 'Сохранить изменения' : 'Создать пользователя';
        passwordField?.classList.toggle('is-hidden', editing);
        const passwordInput = qs('[name="password"]', userForm);
        if (passwordInput) passwordInput.required = !editing;
        if (editing) {
            ['last_name', 'first_name', 'middle_name', 'role', 'login', 'phone', 'email'].forEach((name) => {
                const input = qs(`[name="${name}"]`, userForm);
                if (input) input.value = user[name] ?? '';
            });
        }
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
        submit.disabled = true;
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
            submit.disabled = false;
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
})();
