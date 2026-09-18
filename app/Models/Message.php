<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Message
{
    public static function list(int $conversationId, int $userId, int $afterId = 0): array
    {
        $conversation = Conversation::findForUser($conversationId, $userId);
        if (!$conversation) {
            throw new \DomainException('Диалог не найден.');
        }

        self::markRead($conversationId, $userId);
        $stmt = Database::connection()->prepare(
            'SELECT m.id, m.conversation_id, m.sender_id, m.body, m.read_at, m.created_at,
                    u.first_name, u.last_name
             FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = :conversation_id AND m.id > :after_id
             ORDER BY m.id ASC LIMIT 200'
        );
        $stmt->bindValue(':conversation_id', $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue(':after_id', $afterId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function create(int $conversationId, int $senderId, string $body): array
    {
        if (!Conversation::findForUser($conversationId, $senderId)) {
            throw new \DomainException('Диалог не найден.');
        }

        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new \DomainException('Сообщение должно содержать от 1 до 2000 символов.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (:conversation_id, :sender_id, :body)');
            $stmt->execute(['conversation_id' => $conversationId, 'sender_id' => $senderId, 'body' => $body]);
            $id = (int) $pdo->lastInsertId();
            $touch = $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $conversationId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $stmt = $pdo->prepare(
            'SELECT m.id, m.conversation_id, m.sender_id, m.body, m.read_at, m.created_at, u.first_name, u.last_name
             FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function markRead(int $conversationId, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE messages SET read_at = NOW() WHERE conversation_id = :conversation_id AND sender_id <> :user_id AND read_at IS NULL'
        );
        $stmt->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
    }
}

