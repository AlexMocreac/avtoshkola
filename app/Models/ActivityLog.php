<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ActivityLog
{
    public static function record(
        string $actionKey,
        string $actionName,
        ?User $actor = null,
        array $impactedUsers = [],
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = []
    ): int {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO activity_logs
                 (action_key, action_name, actor_user_id, actor_name, actor_role, entity_type, entity_id, details, ip_address, user_agent)
                 VALUES (:action_key, :action_name, :actor_user_id, :actor_name, :actor_role, :entity_type, :entity_id, :details, :ip_address, :user_agent)'
            );
            $stmt->execute([
                'action_key' => mb_substr($actionKey, 0, 80),
                'action_name' => mb_substr($actionName, 0, 190),
                'actor_user_id' => $actor?->id,
                'actor_name' => $actor?->fullName() ?? 'Система',
                'actor_role' => $actor?->roleTitle(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip_address' => self::clientIp(),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();

            $target = $pdo->prepare(
                'INSERT INTO activity_log_targets (activity_log_id, impacted_user_id, impacted_name)
                 VALUES (:activity_log_id, :impacted_user_id, :impacted_name)'
            );
            foreach (self::targets($impactedUsers) as $user) {
                $target->execute([
                    'activity_log_id' => $id,
                    'impacted_user_id' => $user->id,
                    'impacted_name' => $user->fullName(),
                ]);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $id;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function all(array $filters = [], int $limit = 200, ?int $viewerId = null): array
    {
        $where = ['1 = 1'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(l.action_name LIKE :search_action OR l.actor_name LIKE :search_actor OR l.details LIKE :search_details
                OR EXISTS (SELECT 1 FROM activity_log_targets st WHERE st.activity_log_id = l.id AND st.impacted_name LIKE :search_target))';
            foreach (['action', 'actor', 'details', 'target'] as $key) {
                $params['search_' . $key] = '%' . $search . '%';
            }
        }
        if (!empty($filters['action'])) {
            $where[] = 'l.action_key = :action_key';
            $params['action_key'] = (string) $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'l.created_at >= :date_from';
            $params['date_from'] = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'l.created_at <= :date_to';
            $params['date_to'] = (string) $filters['date_to'] . ' 23:59:59';
        }
        if ($viewerId !== null) {
            $where[] = '(l.entity_type <> "task"
                OR NOT EXISTS (SELECT 1 FROM tasks hidden_task WHERE hidden_task.id = l.entity_id AND hidden_task.confidential = 1)
                OR EXISTS (SELECT 1 FROM activity_log_targets visible_target WHERE visible_target.activity_log_id = l.id AND visible_target.impacted_user_id = :viewer_id))';
            $params['viewer_id'] = $viewerId;
        }

        $limit = max(1, min(500, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT l.*,
                (SELECT GROUP_CONCAT(t.impacted_name ORDER BY t.id SEPARATOR ", ")
                 FROM activity_log_targets t WHERE t.activity_log_id = l.id) AS impacted_names,
                entity_user.role AS entity_user_role,
                CONCAT_WS(" ", entity_user.last_name, entity_user.first_name, entity_user.middle_name) AS entity_user_name,
                entity_lead.direction AS entity_lead_direction,
                COALESCE(NULLIF(TRIM(CONCAT_WS(" ", entity_lead.last_name, entity_lead.first_name, entity_lead.middle_name)), ""), entity_lead.company_name) AS entity_lead_name,
                entity_contract.contract_number AS entity_contract_number,
                entity_contract.counterparty AS entity_contract_counterparty,
                entity_task.description AS entity_task_description,
                entity_deadline.title AS entity_deadline_title
             FROM activity_logs l
             LEFT JOIN users entity_user ON l.entity_type = "user" AND entity_user.id = l.entity_id
             LEFT JOIN crm_leads entity_lead ON l.entity_type = "lead" AND entity_lead.id = l.entity_id
             LEFT JOIN crm_contracts entity_contract ON l.entity_type = "contract" AND entity_contract.id = l.entity_id
             LEFT JOIN tasks entity_task ON l.entity_type = "task" AND entity_task.id = l.entity_id
             LEFT JOIN deadline_events entity_deadline ON l.entity_type = "deadline" AND entity_deadline.id = l.entity_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY l.created_at DESC, l.id DESC LIMIT ' . $limit
        );
        $stmt->execute($params);
        return array_map([self::class, 'present'], $stmt->fetchAll());
    }

    public static function actions(?int $viewerId = null): array
    {
        $where = '';
        $params = [];
        if ($viewerId !== null) {
            $where = 'WHERE (l.entity_type <> "task"
                OR NOT EXISTS (SELECT 1 FROM tasks hidden_task WHERE hidden_task.id = l.entity_id AND hidden_task.confidential = 1)
                OR EXISTS (SELECT 1 FROM activity_log_targets visible_target WHERE visible_target.activity_log_id = l.id AND visible_target.impacted_user_id = :viewer_id))';
            $params['viewer_id'] = $viewerId;
        }
        $stmt = Database::connection()->prepare(
            'SELECT l.action_key, MAX(l.action_name) AS action_name FROM activity_logs l ' . $where . ' GROUP BY l.action_key ORDER BY action_name'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function forEntity(string $entityType, int $entityId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT id, action_key, action_name, actor_name, actor_role, details, created_at
             FROM activity_logs
             WHERE entity_type = :entity_type AND entity_id = :entity_id
             ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );
        $stmt->execute(['entity_type' => $entityType, 'entity_id' => $entityId]);
        return $stmt->fetchAll();
    }

    /**
     * Build an audit-safe list containing only values that actually changed.
     * Controllers pass display-ready values so the event feed can stay generic.
     */
    public static function changes(array $before, array $after, array $fields): array
    {
        $changes = [];
        foreach ($fields as $key => $label) {
            $from = self::displayValue($before[$key] ?? null);
            $to = self::displayValue($after[$key] ?? null);
            if ($from === $to) {
                continue;
            }
            $changes[] = ['field' => (string) $label, 'from' => $from, 'to' => $to];
        }
        return $changes;
    }

    private static function targets(array $targets): array
    {
        $resolved = [];
        foreach ($targets as $target) {
            $user = $target instanceof User ? $target : User::find((int) $target);
            if ($user && !isset($resolved[$user->id])) {
                $resolved[$user->id] = $user;
            }
        }
        return array_values($resolved);
    }

    private static function present(array $event): array
    {
        $details = [];
        if (!empty($event['details'])) {
            $decoded = json_decode((string) $event['details'], true);
            $details = is_array($decoded) ? $decoded : [];
        }

        $event['details_data'] = $details;
        $event['changes'] = self::eventChanges($event, $details);
        $event['legacy_change_details_missing'] = in_array((string) ($event['action_key'] ?? ''), [
            'lead.updated', 'lead.stage_changed', 'contract.updated', 'user.updated',
        ], true) && !$event['changes'] && !array_key_exists('changes', $details);
        $event['entity_name'] = self::entityName($event, $details);
        $event['entity_url'] = self::entityUrl($event, $details);
        $event['actor_url'] = self::userUrl(
            isset($event['actor_user_id']) ? (int) $event['actor_user_id'] : null,
            (string) ($event['actor_role'] ?? '')
        );
        return $event;
    }

    private static function eventChanges(array $event, array $details): array
    {
        if (isset($details['changes']) && is_array($details['changes'])) {
            $changes = [];
            foreach ($details['changes'] as $change) {
                if (!is_array($change) || !isset($change['field'])) {
                    continue;
                }
                $changes[] = [
                    'field' => (string) $change['field'],
                    'from' => self::displayValue($change['from'] ?? null),
                    'to' => self::displayValue($change['to'] ?? null),
                ];
            }
            return $changes;
        }

        // Compatibility with audit entries created before field-level changes
        // were introduced.
        $changes = [];
        if (array_key_exists('from_status', $details) && array_key_exists('to_status', $details)) {
            $from = (string) $details['from_status'];
            $to = (string) $details['to_status'];
            $type = (string) ($event['entity_type'] ?? '');
            $labels = $type === 'lead' ? Lead::STATUSES : ($type === 'contract' ? Contract::STATUSES : []);
            $field = $type === 'lead' ? 'Этап' : 'Статус';
            if ($from !== $to) {
                $changes[] = ['field' => $field, 'from' => $labels[$from] ?? self::displayValue($from), 'to' => $labels[$to] ?? self::displayValue($to)];
            }
        }
        if (array_key_exists('from_direction', $details) && array_key_exists('to_direction', $details)) {
            $from = (string) $details['from_direction'];
            $to = (string) $details['to_direction'];
            if ($from !== $to) {
                $changes[] = [
                    'field' => 'Направление',
                    'from' => Lead::DIRECTIONS[$from] ?? self::displayValue($from),
                    'to' => Lead::DIRECTIONS[$to] ?? self::displayValue($to),
                ];
            }
        }
        return $changes;
    }

    private static function entityName(array $event, array $details): string
    {
        $type = (string) ($event['entity_type'] ?? '');
        return match ($type) {
            'user' => trim((string) ($details['name'] ?? $event['entity_user_name'] ?? $event['impacted_names'] ?? $event['actor_name'] ?? '')),
            'lead' => trim((string) ($details['name'] ?? $event['entity_lead_name'] ?? '')) ?: 'Лид №' . (int) $event['entity_id'],
            'contract' => self::contractName($event, $details),
            'task' => self::prefixedName('Задача', (string) ($details['description'] ?? $event['entity_task_description'] ?? '')),
            'deadline' => self::prefixedName('Событие', (string) ($details['title'] ?? $event['entity_deadline_title'] ?? '')),
            'conversation' => self::prefixedName('Диалог с', (string) ($event['impacted_names'] ?? '')),
            default => trim((string) ($details['name'] ?? $details['title'] ?? '')),
        };
    }

    private static function contractName(array $event, array $details): string
    {
        $number = trim((string) ($details['number'] ?? $event['entity_contract_number'] ?? ''));
        $counterparty = trim((string) ($details['counterparty'] ?? $event['entity_contract_counterparty'] ?? ''));
        $name = $number !== '' ? 'Договор №' . $number : 'Договор №' . (int) $event['entity_id'];
        return $counterparty !== '' ? $name . ' — ' . $counterparty : $name;
    }

    private static function prefixedName(string $prefix, string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }
        return $prefix . ' «' . mb_substr($value, 0, 120) . (mb_strlen($value) > 120 ? '…' : '') . '»';
    }

    private static function entityUrl(array $event, array $details): ?string
    {
        $id = isset($event['entity_id']) ? (int) $event['entity_id'] : 0;
        if ($id < 1) {
            return null;
        }
        $type = (string) ($event['entity_type'] ?? '');
        if ($type === 'user') {
            $role = (string) ($details['role_key'] ?? $event['entity_user_role'] ?? '');
            return self::userUrl($id, $role === User::ROLE_STUDENT ? User::ROLES[User::ROLE_STUDENT] : '');
        }
        if ($type === 'lead') {
            $direction = (string) ($details['direction'] ?? $details['to_direction'] ?? $event['entity_lead_direction'] ?? 'driving_school');
            $direction = isset(Lead::DIRECTIONS[$direction]) ? $direction : 'driving_school';
            return '/crm/leads?direction=' . rawurlencode($direction) . '&lead_id=' . $id;
        }
        if ($type === 'contract') {
            return '/crm/contracts#contract-' . $id;
        }
        if ($type === 'task') {
            $completed = (string) ($event['action_key'] ?? '') === 'task.completed';
            return '/tasks?status=' . ($completed ? 'completed' : 'open') . '#task-' . $id;
        }
        if ($type === 'deadline') {
            return '/warnings#deadline-' . $id;
        }
        return $type === 'conversation' ? '/chat' : null;
    }

    private static function userUrl(?int $id, string $roleTitle): ?string
    {
        if (!$id) {
            return null;
        }
        $path = $roleTitle === User::ROLES[User::ROLE_STUDENT] || $roleTitle === User::ROLE_STUDENT ? '/students' : '/staff';
        return $path . '#user-' . $id;
    }

    private static function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }
        if (is_array($value)) {
            return implode(', ', array_map([self::class, 'displayValue'], $value));
        }
        return trim((string) $value) !== '' ? trim((string) $value) : '—';
    }

    private static function clientIp(): ?string
    {
        $value = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $value !== '' ? mb_substr($value, 0, 45) : null;
    }
}
