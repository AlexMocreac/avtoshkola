<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Conversation
{
    public static function forUser(User $user): array
    {
        $otherId = $user->isStudent() ? 'c.employee_id' : 'c.student_id';
        $stmt = Database::connection()->prepare(
            "SELECT c.id, c.student_id, c.employee_id, c.updated_at,
                    u.first_name, u.last_name, u.middle_name, u.role, u.status,
                    (SELECT body FROM messages lm WHERE lm.conversation_id = c.id ORDER BY lm.id DESC LIMIT 1) AS last_message,
                    (SELECT created_at FROM messages lmd WHERE lmd.conversation_id = c.id ORDER BY lmd.id DESC LIMIT 1) AS last_message_at,
                    (SELECT COUNT(*) FROM messages um WHERE um.conversation_id = c.id AND um.sender_id <> :unread_user AND um.read_at IS NULL) AS unread_count
             FROM conversations c
             JOIN users u ON u.id = {$otherId}
             WHERE (c.student_id = :student_user_id OR c.employee_id = :employee_user_id) AND u.status <> 'deleted'
             ORDER BY COALESCE(last_message_at, c.updated_at) DESC"
        );
        $stmt->execute([
            'unread_user' => $user->id,
            'student_user_id' => $user->id,
            'employee_user_id' => $user->id,
        ]);
        return $stmt->fetchAll();
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conversations WHERE id = :id AND (student_id = :student_user_id OR employee_id = :employee_user_id) LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'student_user_id' => $userId, 'employee_user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function createOrFind(User $current, User $contact): int
    {
        if ($current->isStudent() === $contact->isStudent()) {
            throw new \DomainException('Диалог доступен только между курсантом и сотрудником.');
        }

        $studentId = $current->isStudent() ? $current->id : $contact->id;
        $employeeId = $current->isStudent() ? $contact->id : $current->id;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM conversations WHERE student_id = :student_id AND employee_id = :employee_id LIMIT 1');
        $stmt->execute(['student_id' => $studentId, 'employee_id' => $employeeId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = $pdo->prepare('INSERT INTO conversations (student_id, employee_id) VALUES (:student_id, :employee_id)');
        $stmt->execute(['student_id' => $studentId, 'employee_id' => $employeeId]);
        return (int) $pdo->lastInsertId();
    }

    public static function totalUnread(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
             WHERE (c.student_id = :student_user_id OR c.employee_id = :employee_user_id) AND m.sender_id <> :sender_id AND m.read_at IS NULL'
        );
        $stmt->execute(['student_user_id' => $userId, 'employee_user_id' => $userId, 'sender_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
