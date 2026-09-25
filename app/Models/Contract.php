<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Contract
{
    public const STATUSES = [
        'active' => 'Действует',
        'completed' => 'Завершён',
        'terminated' => 'Расторгнут',
    ];

    public static function all(array $filters = []): array
    {
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(INSTR(LOWER(c.contract_number), LOWER(:search_number)) > 0
                OR INSTR(LOWER(COALESCE(c.registration_number, "")), LOWER(:search_registration)) > 0
                OR INSTR(LOWER(c.counterparty), LOWER(:search_counterparty)) > 0
                OR INSTR(COALESCE(c.client_phone, ""), :search_phone) > 0
                OR INSTR(LOWER(CONCAT_WS(" ", l.last_name, l.first_name, l.middle_name)), LOWER(:search_lead_name)) > 0
                OR INSTR(COALESCE(l.phone, ""), :search_lead_phone) > 0)';
            foreach (['number', 'registration', 'counterparty', 'phone', 'lead_name', 'lead_phone'] as $key) {
                $params['search_' . $key] = $search;
            }
        }
        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $where[] = 'c.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'c.signed_on >= :date_from';
            $params['date_from'] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'c.signed_on <= :date_to';
            $params['date_to'] = (string) $filters['date_to'];
        }
        $stmt = Database::connection()->prepare(
            'SELECT c.*, l.first_name AS lead_first_name, l.last_name AS lead_last_name, l.middle_name AS lead_middle_name,
                    l.company_name AS lead_company_name, l.phone AS lead_phone,
                    CONCAT_WS(" ", l.last_name, l.first_name, l.middle_name) AS lead_name,
                    CONCAT_WS(" ", manager.last_name, manager.first_name) AS manager_name
             FROM crm_contracts c
             LEFT JOIN crm_leads l ON l.id = c.lead_id
             LEFT JOIN users manager ON manager.id = l.assigned_to
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.signed_on DESC, c.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM crm_contracts WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $payload = self::payload($data);
        $automatic = $payload['contract_number'] === '';
        if ($automatic) {
            $payload['contract_number'] = 'AUTO-' . bin2hex(random_bytes(12));
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO crm_contracts
             (contract_number, registration_number, lead_id, counterparty, client_phone, subject, direction, signed_on, starts_on, ends_on, amount, status, notes, created_by, updated_by)
             VALUES (:contract_number, :registration_number, :lead_id, :counterparty, :client_phone, :subject, :direction, :signed_on, :starts_on, :ends_on, :amount, :status, :notes, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId, 'updated_by' => $userId]);
        $id = (int) Database::connection()->lastInsertId();
        if ($automatic) {
            $number = self::automaticNumber($id, (string) $payload['signed_on']);
            $update = Database::connection()->prepare('UPDATE crm_contracts SET contract_number = :number WHERE id = :id');
            $update->execute(['number' => $number, 'id' => $id]);
        }
        return $id;
    }

    public static function update(int $id, array $data, int $userId): void
    {
        $payload = self::payload($data);
        if ($payload['contract_number'] === '') {
            $payload['contract_number'] = self::automaticNumber($id, (string) $payload['signed_on']);
        }
        $stmt = Database::connection()->prepare(
            'UPDATE crm_contracts SET contract_number = :contract_number, registration_number = :registration_number,
             lead_id = :lead_id, counterparty = :counterparty, client_phone = :client_phone,
             subject = :subject, direction = :direction, signed_on = :signed_on, starts_on = :starts_on, ends_on = :ends_on,
             amount = :amount, status = :status, notes = :notes, updated_by = :updated_by
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($payload + ['updated_by' => $userId, 'id' => $id]);
    }

    public static function archive(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE crm_contracts SET deleted_at = NOW(), updated_by = :updated_by WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['updated_by' => $userId, 'id' => $id]);
    }

    public static function activeCount(): int
    {
        return (int) Database::connection()->query(
            'SELECT COUNT(*) FROM crm_contracts WHERE deleted_at IS NULL AND status = "active"'
        )->fetchColumn();
    }

    public static function automaticNumber(int $id, string $signedOn): string
    {
        $year = (int) substr($signedOn, 0, 4);
        if ($year < 2000 || $year > 9999) {
            $year = (int) date('Y');
        }
        return sprintf('ДГ-%d-%06d', $year, $id);
    }

    private static function payload(array $data): array
    {
        $status = (string) ($data['status'] ?? 'active');
        $amount = str_replace(',', '.', trim((string) ($data['amount'] ?? '')));
        return [
            'contract_number' => trim((string) ($data['contract_number'] ?? '')),
            'registration_number' => self::nullable($data['registration_number'] ?? null),
            'lead_id' => (int) ($data['lead_id'] ?? 0) ?: null,
            'counterparty' => trim((string) ($data['counterparty'] ?? '')),
            'client_phone' => self::nullable($data['client_phone'] ?? null),
            'subject' => trim((string) ($data['subject'] ?? '')),
            'direction' => isset(Lead::DIRECTIONS[(string) ($data['direction'] ?? '')]) ? (string) $data['direction'] : 'driving_school',
            'signed_on' => trim((string) ($data['signed_on'] ?? '')),
            'starts_on' => self::nullable($data['starts_on'] ?? null),
            'ends_on' => self::nullable($data['ends_on'] ?? null),
            'amount' => $amount === '' ? null : $amount,
            'status' => isset(self::STATUSES[$status]) ? $status : 'active',
            'notes' => self::nullable($data['notes'] ?? null),
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
