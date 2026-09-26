<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Lead
{
    public const DIRECTIONS = [
        'driving_school' => 'Автошкола',
        'gostekhnadzor' => 'Гостехнадзор',
        'dpo' => 'ДПО',
    ];

    public const STATUSES = [
        'new' => 'Новый',
        'contacted' => 'Связались',
        'target' => 'Целевой',
        'contract' => 'Договор',
        'refused' => 'Отказ',
    ];

    public static function all(array $filters = []): array
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(INSTR(LOWER(CONCAT_WS(" ", l.last_name, l.first_name, l.middle_name)), LOWER(:search_name)) > 0
                OR INSTR(LOWER(COALESCE(l.company_name, "")), LOWER(:search_company)) > 0
                OR INSTR(COALESCE(l.phone, ""), :search_phone) > 0
                OR INSTR(LOWER(COALESCE(l.email, "")), LOWER(:search_email)) > 0)';
            foreach (['name', 'company', 'phone', 'email'] as $key) {
                $params['search_' . $key] = $search;
            }
        }
        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $where[] = 'l.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['direction']) && isset(self::DIRECTIONS[$filters['direction']])) {
            $where[] = 'l.direction = :direction';
            $params['direction'] = (string) $filters['direction'];
        }

        $stmt = Database::connection()->prepare(
            'SELECT l.*, CONCAT_WS(" ", u.last_name, u.first_name) AS assignee_name
             FROM crm_leads l LEFT JOIN users u ON u.id = l.assigned_to
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(l.status, "new", "contacted", "target", "contract", "refused"), l.updated_at DESC, l.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.*, CONCAT_WS(" ", u.last_name, u.first_name) AS assignee_name
             FROM crm_leads l LEFT JOIN users u ON u.id = l.assigned_to
             WHERE l.id = :id AND l.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function selectable(): array
    {
        return Database::connection()->query(
            'SELECT id, first_name, last_name, middle_name, company_name, phone, direction FROM crm_leads
             WHERE deleted_at IS NULL AND status <> "refused" ORDER BY last_name, first_name, company_name'
        )->fetchAll();
    }

    public static function create(array $data, int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO crm_leads
             (first_name, last_name, middle_name, company_name, phone, email, source, direction, status, notes, next_contact_at, assigned_to, created_by, updated_by)
             VALUES (:first_name, :last_name, :middle_name, :company_name, :phone, :email, :source, :direction, :status, :notes, :next_contact_at, :assigned_to, :created_by, :updated_by)'
        );
        $stmt->execute(self::payload($data) + ['created_by' => $userId, 'updated_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, int $userId): void
    {
        $stmt = Database::connection()->prepare(
             'UPDATE crm_leads SET first_name = :first_name, last_name = :last_name, middle_name = :middle_name,
             company_name = :company_name, phone = :phone, email = :email, source = :source, direction = :direction, status = :status,
             notes = :notes, next_contact_at = :next_contact_at, assigned_to = :assigned_to, updated_by = :updated_by
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(self::payload($data) + ['updated_by' => $userId, 'id' => $id]);
    }

    public static function moveToStatus(int $id, string $fromStatus, string $status, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE crm_leads SET status = :status, updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id AND status = :from_status AND deleted_at IS NULL'
        );
        $stmt->execute(['status' => $status, 'updated_by' => $userId, 'id' => $id, 'from_status' => $fromStatus]);
        return $stmt->rowCount() === 1;
    }

    public static function archive(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE crm_leads SET deleted_at = NOW(), updated_by = :updated_by WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['updated_by' => $userId, 'id' => $id]);
    }

    public static function stats(array $filters = []): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];
        $direction = (string) ($filters['direction'] ?? '');
        if (isset(self::DIRECTIONS[$direction])) {
            $where[] = 'direction = :direction';
            $params['direction'] = $direction;
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(INSTR(LOWER(CONCAT_WS(" ", last_name, first_name, middle_name)), LOWER(:search_name)) > 0
                OR INSTR(LOWER(COALESCE(company_name, "")), LOWER(:search_company)) > 0
                OR INSTR(COALESCE(phone, ""), :search_phone) > 0
                OR INSTR(LOWER(COALESCE(email, "")), LOWER(:search_email)) > 0)';
            foreach (['name', 'company', 'phone', 'email'] as $key) {
                $params['search_' . $key] = $search;
            }
        }
        $stmt = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS count FROM crm_leads WHERE ' . implode(' AND ', $where) . ' GROUP BY status'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $stats = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        return $stats;
    }

    public static function markConverted(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE crm_leads SET status = "contract", updated_by = :updated_by WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['updated_by' => $userId, 'id' => $id]);
    }

    public static function directionTotals(): array
    {
        $totals = array_fill_keys(array_keys(self::DIRECTIONS), 0);
        $rows = Database::connection()->query(
            'SELECT direction, COUNT(*) AS count FROM crm_leads WHERE deleted_at IS NULL GROUP BY direction'
        )->fetchAll();
        foreach ($rows as $row) {
            if (isset($totals[$row['direction']])) {
                $totals[$row['direction']] = (int) $row['count'];
            }
        }
        return $totals;
    }

    public static function displayName(array $lead): string
    {
        $person = trim(($lead['last_name'] ?? '') . ' ' . ($lead['first_name'] ?? '') . ' ' . ($lead['middle_name'] ?? ''));
        return $person !== '' ? $person : ((string) ($lead['company_name'] ?? '') ?: 'Лид №' . $lead['id']);
    }

    private static function payload(array $data): array
    {
        $status = (string) ($data['status'] ?? 'new');
        return [
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => self::nullable($data['last_name'] ?? null),
            'middle_name' => self::nullable($data['middle_name'] ?? null),
            'company_name' => self::nullable($data['company_name'] ?? null),
            'phone' => self::nullable($data['phone'] ?? null),
            'email' => self::nullable($data['email'] ?? null),
            'source' => self::nullable($data['source'] ?? null),
            'direction' => isset(self::DIRECTIONS[(string) ($data['direction'] ?? '')]) ? (string) $data['direction'] : 'driving_school',
            'status' => isset(self::STATUSES[$status]) ? $status : 'new',
            'notes' => self::nullable($data['notes'] ?? null),
            'next_contact_at' => self::dateTime($data['next_contact_at'] ?? null),
            'assigned_to' => (int) ($data['assigned_to'] ?? 0) ?: null,
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function dateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
    }
}
