<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;

final class Task
{
    public const STATUSES = [
        'open' => 'В работе',
        'completed' => 'Выполнена',
    ];

    public static function all(User $viewer, array $filters = []): array
    {
        $where = [
            '(t.confidential = 0 OR EXISTS (SELECT 1 FROM task_assignees visible WHERE visible.task_id = t.id AND visible.user_id = :viewer_id))',
        ];
        $params = ['viewer_id' => $viewer->id];
        $status = (string) ($filters['status'] ?? 'open');
        if (isset(self::STATUSES[$status])) {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        if (($filters['mine'] ?? '') === '1') {
            $where[] = 'EXISTS (SELECT 1 FROM task_assignees mine WHERE mine.task_id = t.id AND mine.user_id = :mine_id)';
            $params['mine_id'] = $viewer->id;
        }

        $stmt = Database::connection()->prepare(
            'SELECT t.*, CONCAT_WS(" ", creator.last_name, creator.first_name) AS creator_name,
                (SELECT GROUP_CONCAT(CONCAT_WS(" ", assignee.last_name, assignee.first_name) ORDER BY assignee.last_name, assignee.first_name SEPARATOR ", ")
                 FROM task_assignees names_link JOIN users assignee ON assignee.id = names_link.user_id WHERE names_link.task_id = t.id) AS assignee_names,
                EXISTS (SELECT 1 FROM task_assignees own WHERE own.task_id = t.id AND own.user_id = :assignee_viewer_id) AS is_assignee
             FROM tasks t
             JOIN users creator ON creator.id = t.created_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY (t.status = "completed") ASC, COALESCE(t.due_at, CONCAT(t.task_date, " 23:59:59")) ASC, t.id DESC'
        );
        $stmt->execute(['assignee_viewer_id' => $viewer->id] + $params);
        return $stmt->fetchAll();
    }

    public static function createSeries(array $data, int $creatorId, array $assigneeIds): array
    {
        $assigneeIds = array_values(array_unique(array_filter(array_map('intval', $assigneeIds))));
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $dates = self::occurrences($data);
            $group = count($dates) > 1 ? self::uuid() : null;
            $taskInsert = $pdo->prepare(
                'INSERT INTO tasks
                 (description, task_date, due_at, confidential, status, recurrence_group, recurrence_rule, recurrence_day, recurrence_until, created_by)
                 VALUES (:description, :task_date, :due_at, :confidential, "open", :recurrence_group, :recurrence_rule, :recurrence_day, :recurrence_until, :created_by)'
            );
            $assigneeInsert = $pdo->prepare('INSERT INTO task_assignees (task_id, user_id) VALUES (:task_id, :user_id)');
            $ids = [];
            foreach ($dates as $date) {
                $dueAt = self::occurrenceDueAt($data, $date);
                $taskInsert->execute([
                    'description' => trim((string) $data['description']),
                    'task_date' => $date,
                    'due_at' => $dueAt,
                    'confidential' => !empty($data['confidential']) ? 1 : 0,
                    'recurrence_group' => $group,
                    'recurrence_rule' => $group ? 'monthly' : null,
                    'recurrence_day' => $group ? (int) $data['recurrence_day'] : null,
                    'recurrence_until' => $group ? (string) $data['recurrence_until'] : null,
                    'created_by' => $creatorId,
                ]);
                $taskId = (int) $pdo->lastInsertId();
                $ids[] = $taskId;
                foreach ($assigneeIds as $assigneeId) {
                    $assigneeInsert->execute(['task_id' => $taskId, 'user_id' => $assigneeId]);
                }
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $ids;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function findVisible(int $id, User $viewer): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.* FROM tasks t WHERE t.id = :id
             AND (t.confidential = 0 OR EXISTS (SELECT 1 FROM task_assignees a WHERE a.task_id = t.id AND a.user_id = :viewer_id)) LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'viewer_id' => $viewer->id]);
        return $stmt->fetch() ?: null;
    }

    public static function assignees(int $taskId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.* FROM users u JOIN task_assignees a ON a.user_id = u.id WHERE a.task_id = :task_id ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['task_id' => $taskId]);
        return array_map([User::class, 'fromRow'], $stmt->fetchAll());
    }

    public static function complete(int $id, User $user): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE tasks SET status = "completed", completed_by = :completed_by, completed_at = NOW()
             WHERE id = :id AND status = "open"
             AND EXISTS (SELECT 1 FROM task_assignees a WHERE a.task_id = tasks.id AND a.user_id = :assignee_id)'
        );
        $stmt->execute(['completed_by' => $user->id, 'id' => $id, 'assignee_id' => $user->id]);
        return $stmt->rowCount() === 1;
    }

    public static function openCountFor(User $user): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM tasks t WHERE t.status = "open"
             AND (t.confidential = 0 OR EXISTS (SELECT 1 FROM task_assignees visible WHERE visible.task_id = t.id AND visible.user_id = :viewer_id))'
        );
        $stmt->execute(['viewer_id' => $user->id]);
        return (int) $stmt->fetchColumn();
    }

    private static function occurrences(array $data): array
    {
        $start = new DateTimeImmutable((string) $data['task_date']);
        if (($data['recurrence'] ?? '') !== 'monthly') {
            return [$start->format('Y-m-d')];
        }

        $day = (int) $data['recurrence_day'];
        $until = new DateTimeImmutable((string) $data['recurrence_until']);
        $cursor = $start->modify('first day of this month');
        $dates = [];
        while ($cursor <= $until) {
            $candidateDay = min($day, (int) $cursor->format('t'));
            $candidate = $cursor->setDate((int) $cursor->format('Y'), (int) $cursor->format('m'), $candidateDay);
            if ($candidate >= $start && $candidate <= $until) {
                $dates[] = $candidate->format('Y-m-d');
            }
            $cursor = $cursor->modify('first day of next month');
        }
        return $dates;
    }

    private static function occurrenceDueAt(array $data, string $date): ?string
    {
        $due = trim((string) ($data['due_at'] ?? ''));
        if (($data['recurrence'] ?? '') === 'monthly') {
            $time = $due !== '' && str_contains($due, 'T') ? substr($due, 11, 5) : '23:59';
            return $date . ' ' . $time . ':00';
        }
        return $due === '' ? null : str_replace('T', ' ', $due) . (strlen($due) === 16 ? ':00' : '');
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
