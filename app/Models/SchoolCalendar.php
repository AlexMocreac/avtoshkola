<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

final class SchoolCalendar
{
    public static function month(User $viewer, string $month, array $filters = []): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m', $month) ?: new DateTimeImmutable('first day of this month');
        $end = $start->add(new DateInterval('P1M'));
        $where = ['l.starts_at >= :starts_at', 'l.starts_at < :ends_at', 'l.status <> "cancelled"'];
        $params = ['starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s')];

        if ($viewer->role === User::ROLE_STUDENT) {
            $where[] = 'EXISTS (SELECT 1 FROM class_enrollments ce WHERE ce.class_id = c.id AND ce.student_id = :student_id AND ce.status = "active")';
            $params['student_id'] = $viewer->id;
        } elseif ($viewer->role === User::ROLE_TEACHER) {
            $where[] = 'l.teacher_id = :teacher_id';
            $params['teacher_id'] = $viewer->id;
        } elseif ($viewer->role === User::ROLE_INSTRUCTOR) {
            // The same calendar will receive practical-driving events in Stage 5.
            $where[] = 'l.teacher_id = :instructor_id';
            $params['instructor_id'] = $viewer->id;
        } else {
            if (!empty($filters['class_id'])) {
                $where[] = 'c.id = :class_id';
                $params['class_id'] = (int) $filters['class_id'];
            }
            if (!empty($filters['teacher_id'])) {
                $where[] = 'l.teacher_id = :filter_teacher_id';
                $params['filter_teacher_id'] = (int) $filters['teacher_id'];
            }
        }

        $stmt = Database::connection()->prepare(
            'SELECT l.*, c.name AS class_name, c.group_number, c.classroom,
                    CONCAT_WS(" ", teacher.last_name, teacher.first_name, teacher.middle_name) AS teacher_name,
                    (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = c.id AND ce.status = "active") AS student_count
             FROM class_lessons l
             INNER JOIN student_classes c ON c.id = l.class_id
             INNER JOIN users teacher ON teacher.id = l.teacher_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY l.starts_at'
        );
        $stmt->execute($params);
        $events = [];
        foreach ($stmt->fetchAll() as $event) {
            $events[substr((string) $event['starts_at'], 0, 10)][] = $event;
        }

        $holidayStmt = Database::connection()->prepare(
            'SELECT * FROM school_holidays WHERE holiday_date >= :starts_on AND holiday_date < :ends_on ORDER BY holiday_date'
        );
        $holidayStmt->execute(['starts_on' => $start->format('Y-m-d'), 'ends_on' => $end->format('Y-m-d')]);
        $holidays = [];
        foreach ($holidayStmt->fetchAll() as $holiday) {
            $holidays[(string) $holiday['holiday_date']] = $holiday;
        }

        return [
            'start' => $start,
            'end' => $end,
            'events' => $events,
            'holidays' => $holidays,
            'grid_start' => $start->modify('monday this week'),
            'grid_end' => $end->modify('sunday this week'),
        ];
    }

    public static function holidays(?int $year = null): array
    {
        $year ??= (int) date('Y');
        $stmt = Database::connection()->prepare(
            'SELECT * FROM school_holidays WHERE holiday_date >= :starts_on AND holiday_date <= :ends_on ORDER BY holiday_date'
        );
        $stmt->execute(['starts_on' => $year . '-01-01', 'ends_on' => $year . '-12-31']);
        return $stmt->fetchAll();
    }

    public static function createHoliday(array $data, int $userId): int
    {
        $date = trim((string) ($data['holiday_date'] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $name = trim((string) ($data['name'] ?? ''));
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $name === '') {
            throw new RuntimeException('Укажите дату и название выходного дня.');
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO school_holidays (holiday_date, name, created_by) VALUES (:holiday_date, :name, :created_by)'
        );
        $stmt->execute(['holiday_date' => $date, 'name' => $name, 'created_by' => $userId]);
        StudyClass::regenerateAllActive();
        return (int) Database::connection()->lastInsertId();
    }

    public static function deleteHoliday(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM school_holidays WHERE id = :id');
        $stmt->execute(['id' => $id]);
        StudyClass::regenerateAllActive();
    }
}
