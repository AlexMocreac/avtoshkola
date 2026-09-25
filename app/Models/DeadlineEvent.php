<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class DeadlineEvent
{
    public static function create(array $data, int $creatorId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO deadline_events (title, category, description, due_at, impacted_user_id, created_by)
             VALUES (:title, :category, :description, :due_at, :impacted_user_id, :created_by)'
        );
        $dueAt = str_replace('T', ' ', trim((string) $data['due_at']));
        $stmt->execute([
            'title' => trim((string) $data['title']),
            'category' => self::nullable($data['category'] ?? null),
            'description' => self::nullable($data['description'] ?? null),
            'due_at' => $dueAt . (strlen($dueAt) === 16 ? ':00' : ''),
            'impacted_user_id' => (int) ($data['impacted_user_id'] ?? 0) ?: null,
            'created_by' => $creatorId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM deadline_events WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function complete(int $id, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE deadline_events SET status = "completed", completed_by = :completed_by, completed_at = NOW()
             WHERE id = :id AND status = "open"'
        );
        $stmt->execute(['completed_by' => $userId, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
