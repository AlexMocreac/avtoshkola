<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Warning
{
    public static function all(User $viewer, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $pdo = Database::connection();
        $items = [];

        $contracts = $pdo->query(
            'SELECT id, CONCAT("Договор №", contract_number, ": ", counterparty) AS title, subject AS description,
                    CONCAT(ends_on, " 23:59:59") AS due_at
             FROM crm_contracts WHERE deleted_at IS NULL AND status = "active" AND ends_on IS NOT NULL
             AND ends_on <= DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)'
        )->fetchAll();
        foreach ($contracts as $row) {
            $items[] = self::item('contract', 'Договор', '/crm/contracts', $row);
        }

        $taskStmt = $pdo->prepare(
            'SELECT t.id, LEFT(t.description, 190) AS title, CONCAT("Исполнители: ",
                    (SELECT GROUP_CONCAT(CONCAT_WS(" ", u.last_name, u.first_name) ORDER BY u.last_name SEPARATOR ", ")
                     FROM task_assignees a JOIN users u ON u.id = a.user_id WHERE a.task_id = t.id)) AS description,
                    t.due_at
             FROM tasks t WHERE t.status = "open" AND t.due_at IS NOT NULL
             AND t.due_at <= DATE_ADD(NOW(), INTERVAL ' . $days . ' DAY)
             AND (t.confidential = 0 OR EXISTS (SELECT 1 FROM task_assignees visible WHERE visible.task_id = t.id AND visible.user_id = :viewer_id))'
        );
        $taskStmt->execute(['viewer_id' => $viewer->id]);
        foreach ($taskStmt->fetchAll() as $row) {
            $items[] = self::item('task', 'Задача', '/tasks', $row);
        }

        $leads = $pdo->query(
            'SELECT id, CONCAT("Связаться: ", COALESCE(NULLIF(TRIM(CONCAT_WS(" ", last_name, first_name)), ""), company_name, CONCAT("Лид №", id))) AS title,
                    CONCAT_WS(" · ", phone, email) AS description, next_contact_at AS due_at
             FROM crm_leads WHERE deleted_at IS NULL AND status NOT IN ("contract", "refused") AND next_contact_at IS NOT NULL
             AND next_contact_at <= DATE_ADD(NOW(), INTERVAL ' . $days . ' DAY)'
        )->fetchAll();
        foreach ($leads as $row) {
            $items[] = self::item('lead', 'Контакт с лидом', '/crm/leads', $row);
        }

        $events = $pdo->query(
            'SELECT d.id, d.title, d.description, d.due_at, d.category,
                    CONCAT_WS(" ", u.last_name, u.first_name) AS impacted_name
             FROM deadline_events d LEFT JOIN users u ON u.id = d.impacted_user_id
             WHERE d.status = "open" AND d.due_at <= DATE_ADD(NOW(), INTERVAL ' . $days . ' DAY)'
        )->fetchAll();
        foreach ($events as $row) {
            if (!empty($row['impacted_name'])) {
                $row['description'] = trim((string) ($row['description'] ?? '') . ' · ' . $row['impacted_name'], ' ·');
            }
            $items[] = self::item('event', $row['category'] ?: 'Событие', '/warnings', $row);
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['due_at'], $b['due_at']));
        return $items;
    }

    public static function count(User $viewer): int
    {
        return count(self::all($viewer));
    }

    private static function item(string $source, string $sourceTitle, string $url, array $row): array
    {
        $timestamp = strtotime((string) $row['due_at']) ?: time();
        $today = strtotime(date('Y-m-d') . ' 00:00:00');
        $dueDay = strtotime(date('Y-m-d', $timestamp) . ' 00:00:00');
        $daysLeft = (int) floor(($dueDay - $today) / 86400);
        return [
            'source' => $source,
            'source_title' => $sourceTitle,
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'description' => trim((string) ($row['description'] ?? '')),
            'due_at' => (string) $row['due_at'],
            'url' => $url,
            'days_left' => $daysLeft,
            'urgency' => $daysLeft < 0 ? 'overdue' : ($daysLeft <= 3 ? 'urgent' : 'soon'),
        ];
    }
}
