<?php
$activeConversation = null;
foreach ($conversations as $conversation) {
    if ((int) $conversation['id'] === $activeId) {
        $activeConversation = $conversation;
        break;
    }
}
?>
<section class="section-heading section-heading--chat reveal">
    <div><h2>Диалоги</h2><p><?= $currentUser->isStudent() ? 'Общайтесь с сотрудниками автошколы и инструктором.' : 'Поддерживайте связь с курсантами.' ?></p></div>
    <button class="button button--primary" type="button" data-new-chat><?= icon('plus', 18) ?> Новый диалог</button>
</section>

<section class="chat-shell reveal <?= $activeId ? 'has-active-chat' : '' ?>" id="chatApp" data-conversation-id="<?= $activeId ?>">
    <aside class="chat-list">
        <label class="chat-search"><?= icon('search', 17) ?><input type="search" id="chatSearch" placeholder="Поиск диалога"></label>
        <div class="chat-list__items" id="conversationList">
            <?php foreach ($conversations as $conversation): ?>
                <a class="conversation <?= (int) $conversation['id'] === $activeId ? 'is-active' : '' ?>" href="<?= e(url('/chat?conversation=' . $conversation['id'])) ?>" data-conversation-id="<?= (int) $conversation['id'] ?>" data-conversation-name="<?= e(mb_strtolower($conversation['first_name'] . ' ' . $conversation['last_name'])) ?>" data-unread-count="<?= (int) $conversation['unread_count'] ?>">
                    <span class="avatar avatar--soft"><?= e(initials($conversation['first_name'], $conversation['last_name'])) ?></span>
                    <span class="conversation__content"><span class="conversation__line"><strong><?= e($conversation['first_name'] . ' ' . $conversation['last_name']) ?></strong><time><?= e($conversation['last_message_at'] ? format_date($conversation['last_message_at'], 'H:i') : '') ?></time></span><span class="conversation__line"><small><?= e($conversation['last_message'] ?: ($currentUser->isStudent() ? \App\Models\User::ROLES[$conversation['role']] : 'Новый диалог')) ?></small><?php if ((int) $conversation['unread_count'] > 0): ?><b><?= (int) $conversation['unread_count'] ?></b><?php endif; ?></span></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$conversations): ?><div class="chat-list__empty"><span><?= icon('message', 24) ?></span><strong>Диалогов пока нет</strong><small>Начните общение с помощью кнопки выше.</small></div><?php endif; ?>
        </div>
    </aside>

    <div class="chat-room">
        <?php if ($activeConversation): ?>
            <header class="chat-room__header">
                <button class="icon-button chat-back" type="button" id="chatBack" aria-label="К списку диалогов"><?= icon('arrow-left', 19) ?></button>
                <span class="avatar avatar--dark"><?= e(initials($activeConversation['first_name'], $activeConversation['last_name'])) ?></span>
                <div><strong><?= e($activeConversation['first_name'] . ' ' . $activeConversation['last_name']) ?></strong><small><i></i><?= e(\App\Models\User::ROLES[$activeConversation['role']] ?? '') ?></small></div>
            </header>
            <div class="chat-messages" id="chatMessages" aria-live="polite"><div class="message-loader"><span></span><span></span><span></span></div></div>
            <form class="message-composer" id="messageForm">
                <input type="hidden" name="conversation_id" value="<?= $activeId ?>">
                <textarea name="body" id="messageInput" rows="1" maxlength="2000" placeholder="Напишите сообщение…" aria-label="Сообщение"></textarea>
                <button class="composer-send" type="submit" aria-label="Отправить"><?= icon('send', 19) ?></button>
            </form>
        <?php else: ?>
            <div class="chat-placeholder"><div class="chat-placeholder__icon"><?= icon('message', 34) ?></div><h3>Выберите диалог</h3><p>Сообщения хранятся в системе и доступны только участникам диалога.</p><button class="button button--primary" type="button" data-new-chat>Начать общение</button></div>
        <?php endif; ?>
    </div>
</section>

<dialog class="modal" id="newChatModal">
    <div class="modal__surface">
        <div class="modal__header"><div><p class="page-eyebrow">Новый диалог</p><h2><?= $currentUser->isStudent() ? 'Кому написать?' : 'Выберите курсанта' ?></h2><p><?= $currentUser->isStudent() ? 'Сотрудники автошколы ответят в рабочее время.' : 'Создайте персональный диалог с курсантом.' ?></p></div><button class="icon-button modal__close" type="button" data-dialog-close><?= icon('x', 20) ?></button></div>
        <div class="modal__body">
            <label class="search-field contact-search"><?= icon('search', 18) ?><input type="search" id="contactSearch" placeholder="Найти по имени или роли"></label>
            <div class="contact-list" id="contactList">
                <?php foreach ($contacts as $contact): ?>
                    <button class="contact-option" type="button" data-contact-id="<?= $contact->id ?>" data-contact-name="<?= e(mb_strtolower($contact->fullName() . ' ' . $contact->roleTitle())) ?>">
                        <span class="avatar avatar--soft"><?= e(initials($contact->firstName, $contact->lastName)) ?></span>
                        <span><strong><?= e($contact->fullName()) ?></strong><small><?= e($contact->roleTitle()) ?></small></span>
                        <?= icon('chevron-right', 17) ?>
                    </button>
                <?php endforeach; ?>
                <?php if (!$contacts): ?><div class="empty-state"><span><?= icon('users', 26) ?></span><h3>Контактов пока нет</h3><p>Администратор должен создать активные учётные записи.</p></div><?php endif; ?>
            </div>
        </div>
    </div>
</dialog>
