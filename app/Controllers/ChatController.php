<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Models\Conversation;
use App\Models\ActivityLog;
use App\Models\Message;
use App\Models\User;

final class ChatController
{
    public function index(): void
    {
        $user = Auth::requireLogin();
        $conversations = Conversation::forUser($user);
        $activeId = (int) ($_GET['conversation'] ?? ($conversations[0]['id'] ?? 0));
        if ($activeId && !Conversation::findForUser($activeId, $user->id)) {
            $activeId = 0;
        }
        if ($activeId) {
            Message::markRead($activeId, $user->id);
            $conversations = Conversation::forUser($user);
        }

        View::render('chat/index', [
            'pageTitle' => 'Сообщения',
            'pageEyebrow' => 'Связь с автошколой',
            'currentUser' => $user,
            'conversations' => $conversations,
            'contacts' => User::chatContacts($user),
            'activeId' => $activeId,
        ]);
    }

    public function createConversation(): void
    {
        $user = Auth::requireLogin();
        Csrf::enforce(true);
        $contact = User::find((int) ($_POST['contact_id'] ?? 0));
        if (!$contact || $contact->status !== User::STATUS_ACTIVE) {
            Response::json(['ok' => false, 'message' => 'Контакт недоступен.'], 422);
        }

        try {
            $id = Conversation::createOrFind($user, $contact);
            ActivityLog::record('chat.opened', 'Открыт диалог', $user, [$contact], 'conversation', $id);
            Response::json(['ok' => true, 'conversation_id' => $id, 'redirect' => url('/chat?conversation=' . $id)]);
        } catch (\DomainException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function messages(): void
    {
        $user = Auth::requireLogin();
        try {
            $messages = Message::list(
                (int) ($_GET['conversation_id'] ?? 0),
                $user->id,
                (int) ($_GET['after_id'] ?? 0)
            );
            Response::json(['ok' => true, 'messages' => $messages]);
        } catch (\DomainException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function send(): void
    {
        $user = Auth::requireLogin();
        Csrf::enforce(true);
        try {
            $conversationId = (int) ($_POST['conversation_id'] ?? 0);
            $conversation = Conversation::findForUser($conversationId, $user->id);
            $message = Message::create(
                $conversationId,
                $user->id,
                (string) ($_POST['body'] ?? '')
            );
            $impactedId = $conversation
                ? ((int) $conversation['student_id'] === $user->id ? (int) $conversation['employee_id'] : (int) $conversation['student_id'])
                : 0;
            $impacted = User::find($impactedId);
            ActivityLog::record('chat.message_sent', 'Отправлено сообщение', $user, $impacted ? [$impacted] : [], 'conversation', $conversationId);
            Response::json(['ok' => true, 'message' => $message], 201);
        } catch (\DomainException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function unread(): void
    {
        $user = Auth::requireLogin();
        $conversations = Conversation::forUser($user);
        $count = array_sum(array_map(
            static fn (array $conversation): int => (int) $conversation['unread_count'],
            $conversations
        ));

        Response::json([
            'ok' => true,
            'count' => $count,
            'conversations' => array_map(
                static function (array $conversation) use ($user): array {
                    return [
                        'id' => (int) $conversation['id'],
                        'name' => trim($conversation['first_name'] . ' ' . $conversation['last_name']),
                        'initials' => initials($conversation['first_name'], $conversation['last_name']),
                        'preview' => $conversation['last_message'] ?: (
                            $user->isStudent()
                                ? (User::ROLES[$conversation['role']] ?? 'Сотрудник автошколы')
                                : 'Новый диалог'
                        ),
                        'last_message_at' => $conversation['last_message_at'],
                        'unread_count' => (int) $conversation['unread_count'],
                    ];
                },
                $conversations
            ),
        ]);
    }
}
