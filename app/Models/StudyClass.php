<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class StudyClass
{
    public const STATUSES = [
        'active' => 'Обучается',
        'completed' => 'Обучение завершено',
        'archived' => 'Архив',
    ];

    public const WEEKDAYS = [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота',
        7 => 'Воскресенье',
    ];

    public static function all(User $viewer): array
    {
        $where = ['c.status <> "archived"'];
        $params = [];
        if ($viewer->role === User::ROLE_TEACHER) {
            $where[] = 'c.teacher_id = :teacher_id';
            $params['teacher_id'] = $viewer->id;
        }
        $stmt = Database::connection()->prepare(
            'SELECT c.*, CONCAT_WS(" ", teacher.last_name, teacher.first_name, teacher.middle_name) AS teacher_name,
                    (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = c.id AND ce.status = "active") AS student_count,
                    (SELECT COUNT(*) FROM class_lessons lesson WHERE lesson.class_id = c.id) AS lesson_count,
                    (SELECT MIN(starts_at) FROM class_lessons lesson WHERE lesson.class_id = c.id AND lesson.starts_at >= NOW() AND lesson.status = "scheduled") AS next_lesson_at
             FROM student_classes c
             INNER JOIN users teacher ON teacher.id = c.teacher_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.starts_on DESC, c.group_number'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, CONCAT_WS(" ", teacher.last_name, teacher.first_name, teacher.middle_name) AS teacher_name
             FROM student_classes c INNER JOIN users teacher ON teacher.id = c.teacher_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $patterns = self::patternsFromInput($data);
        self::validate($data, $patterns);
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO student_classes
                 (name, group_number, classroom, study_program, teacher_id, starts_on, driving_starts_on,
                  internal_exam_on, inspection_registration_on, planned_lesson_count, lesson_duration_minutes,
                  status, notes, created_by, updated_by)
                 VALUES (:name, :group_number, :classroom, :study_program, :teacher_id, :starts_on, :driving_starts_on,
                  :internal_exam_on, :inspection_registration_on, :planned_lesson_count, :lesson_duration_minutes,
                  :status, :notes, :created_by, :updated_by)'
            );
            $stmt->execute(self::payload($data) + ['created_by' => $userId, 'updated_by' => $userId]);
            $id = (int) $pdo->lastInsertId();
            self::replacePatterns($id, $patterns);
            self::generateSchedule($id);
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

    public static function update(int $id, array $data, int $userId): void
    {
        $patterns = self::patternsFromInput($data);
        self::validate($data, $patterns);
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare(
                'UPDATE student_classes SET name = :name, group_number = :group_number, classroom = :classroom,
                 study_program = :study_program, teacher_id = :teacher_id, starts_on = :starts_on,
                 driving_starts_on = :driving_starts_on, internal_exam_on = :internal_exam_on,
                 inspection_registration_on = :inspection_registration_on, planned_lesson_count = :planned_lesson_count,
                 lesson_duration_minutes = :lesson_duration_minutes, status = :status, notes = :notes, updated_by = :updated_by
                 WHERE id = :id'
            );
            $stmt->execute(self::payload($data) + ['updated_by' => $userId, 'id' => $id]);
            self::replacePatterns($id, $patterns);
            self::generateSchedule($id);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function patterns(int $classId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT weekday, TIME_FORMAT(starts_at, "%H:%i") AS starts_at
             FROM class_schedule_patterns WHERE class_id = :class_id ORDER BY weekday, starts_at'
        );
        $stmt->execute(['class_id' => $classId]);
        return $stmt->fetchAll();
    }

    public static function lessons(int $classId, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT l.*, CONCAT_WS(" ", teacher.last_name, teacher.first_name, teacher.middle_name) AS teacher_name
             FROM class_lessons l INNER JOIN users teacher ON teacher.id = l.teacher_id
             WHERE l.class_id = :class_id ORDER BY l.starts_at LIMIT ' . $limit
        );
        $stmt->execute(['class_id' => $classId]);
        return $stmt->fetchAll();
    }

    public static function students(int $classId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, ce.joined_on, sp.training_status, sp.driving_allowed,
                    COALESCE((SELECT SUM(s.amount) FROM student_sales s WHERE s.student_id = u.id AND s.status = "active"), 0) AS sold_total,
                    COALESCE((SELECT SUM(p.amount) FROM student_payments p WHERE p.student_id = u.id), 0) AS paid_total
             FROM class_enrollments ce
             INNER JOIN users u ON u.id = ce.student_id
             LEFT JOIN student_profiles sp ON sp.student_id = u.id
             WHERE ce.class_id = :class_id AND ce.status = "active" AND u.status <> "deleted"
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['class_id' => $classId]);
        return $stmt->fetchAll();
    }

    public static function availableStudents(): array
    {
        return Database::connection()->query(
            'SELECT u.id, u.first_name, u.last_name, u.middle_name
             FROM users u
             WHERE u.role = "student" AND u.status <> "deleted"
               AND NOT EXISTS (SELECT 1 FROM class_enrollments ce WHERE ce.student_id = u.id AND ce.status = "active")
             ORDER BY u.last_name, u.first_name'
        )->fetchAll();
    }

    public static function enroll(int $classId, int $studentId, int $userId, ?string $joinedOn = null): void
    {
        $student = User::find($studentId);
        if (!$student || !$student->isStudent()) {
            throw new RuntimeException('Курсант не найден.');
        }
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $close = $pdo->prepare(
                'UPDATE class_enrollments SET status = "transferred", left_on = :left_on
                 WHERE student_id = :student_id AND status = "active"'
            );
            $date = self::validDate($joinedOn) ? (string) $joinedOn : date('Y-m-d');
            $close->execute(['left_on' => $date, 'student_id' => $studentId]);
            $insert = $pdo->prepare(
                'INSERT INTO class_enrollments (class_id, student_id, status, joined_on, created_by)
                 VALUES (:class_id, :student_id, "active", :joined_on, :created_by)'
            );
            $insert->execute(['class_id' => $classId, 'student_id' => $studentId, 'joined_on' => $date, 'created_by' => $userId]);
            StudentProfile::ensure($studentId);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function currentForStudent(int $studentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, ce.joined_on, CONCAT_WS(" ", teacher.last_name, teacher.first_name, teacher.middle_name) AS teacher_name
             FROM class_enrollments ce
             INNER JOIN student_classes c ON c.id = ce.class_id
             INNER JOIN users teacher ON teacher.id = c.teacher_id
             WHERE ce.student_id = :student_id AND ce.status = "active" ORDER BY ce.id DESC LIMIT 1'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetch() ?: null;
    }

    public static function regenerateAllActive(): void
    {
        $ids = Database::connection()->query('SELECT id FROM student_classes WHERE status = "active"')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            self::generateSchedule((int) $id);
        }
    }

    public static function generateSchedule(int $classId): int
    {
        $class = self::find($classId);
        if (!$class) {
            throw new RuntimeException('Учебный класс не найден.');
        }
        $patterns = self::patterns($classId);
        if (!$patterns) {
            throw new RuntimeException('Добавьте хотя бы один день и время занятий.');
        }

        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $pdo->prepare('DELETE FROM class_lessons WHERE class_id = :class_id AND status = "scheduled"')
                ->execute(['class_id' => $classId]);
            $preserved = $pdo->prepare('SELECT starts_at FROM class_lessons WHERE class_id = :class_id');
            $preserved->execute(['class_id' => $classId]);
            $occupied = array_fill_keys(array_map('strval', $preserved->fetchAll(PDO::FETCH_COLUMN)), true);
            $alreadyCount = count($occupied);
            $needed = max(0, (int) $class['planned_lesson_count'] - $alreadyCount);
            $holidays = array_fill_keys(array_map('strval', $pdo->query('SELECT holiday_date FROM school_holidays')->fetchAll(PDO::FETCH_COLUMN)), true);
            $byWeekday = [];
            foreach ($patterns as $pattern) {
                $byWeekday[(int) $pattern['weekday']][] = (string) $pattern['starts_at'];
            }
            foreach ($byWeekday as &$times) {
                sort($times);
            }
            unset($times);

            $insert = $pdo->prepare(
                'INSERT INTO class_lessons (class_id, teacher_id, title, starts_at, ends_at)
                 VALUES (:class_id, :teacher_id, :title, :starts_at, :ends_at)'
            );
            $cursor = new DateTimeImmutable((string) $class['starts_on'] . ' 00:00:00');
            $maxDate = $cursor->add(new DateInterval('P10Y'));
            $created = 0;
            while ($created < $needed && $cursor <= $maxDate) {
                $date = $cursor->format('Y-m-d');
                $weekday = (int) $cursor->format('N');
                if (!isset($holidays[$date]) && isset($byWeekday[$weekday])) {
                    foreach ($byWeekday[$weekday] as $time) {
                        $starts = new DateTimeImmutable($date . ' ' . $time . ':00');
                        $startsSql = $starts->format('Y-m-d H:i:s');
                        if (isset($occupied[$startsSql])) {
                            continue;
                        }
                        $ends = $starts->add(new DateInterval('PT' . (int) $class['lesson_duration_minutes'] . 'M'));
                        $insert->execute([
                            'class_id' => $classId,
                            'teacher_id' => (int) $class['teacher_id'],
                            'title' => 'Теоретическое занятие',
                            'starts_at' => $startsSql,
                            'ends_at' => $ends->format('Y-m-d H:i:s'),
                        ]);
                        $created++;
                        if ($created >= $needed) {
                            break;
                        }
                    }
                }
                $cursor = $cursor->add(new DateInterval('P1D'));
            }
            if ($created < $needed) {
                throw new RuntimeException('Не удалось построить расписание на выбранный срок.');
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $created;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function replacePatterns(int $classId, array $patterns): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM class_schedule_patterns WHERE class_id = :class_id')->execute(['class_id' => $classId]);
        $stmt = $pdo->prepare(
            'INSERT INTO class_schedule_patterns (class_id, weekday, starts_at)
             VALUES (:class_id, :weekday, :starts_at)'
        );
        foreach ($patterns as $pattern) {
            $stmt->execute(['class_id' => $classId] + $pattern);
        }
    }

    private static function patternsFromInput(array $data): array
    {
        $patterns = [];
        foreach ((array) ($data['schedule'] ?? []) as $weekday => $value) {
            $weekday = (int) $weekday;
            foreach (is_array($value) ? $value : [$value] as $time) {
                $time = trim((string) $time);
                if ($weekday >= 1 && $weekday <= 7 && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                    $patterns[$weekday . '-' . $time] = ['weekday' => $weekday, 'starts_at' => $time . ':00'];
                }
            }
        }
        ksort($patterns);
        return array_values($patterns);
    }

    private static function validate(array $data, array $patterns): void
    {
        if (mb_strlen(trim((string) ($data['name'] ?? ''))) < 2 || trim((string) ($data['group_number'] ?? '')) === '') {
            throw new RuntimeException('Укажите название и номер учебного класса.');
        }
        if (trim((string) ($data['study_program'] ?? '')) === '') {
            throw new RuntimeException('Укажите учебную программу.');
        }
        $teacher = User::find((int) ($data['teacher_id'] ?? 0));
        if (!$teacher || $teacher->isStudent()) {
            throw new RuntimeException('Выберите преподавателя.');
        }
        if (!self::validDate($data['starts_on'] ?? null)) {
            throw new RuntimeException('Укажите дату начала обучения.');
        }
        $count = (int) ($data['planned_lesson_count'] ?? 0);
        $duration = (int) ($data['lesson_duration_minutes'] ?? 0);
        if ($count < 1 || $count > 500 || $duration < 15 || $duration > 480) {
            throw new RuntimeException('Количество занятий должно быть от 1 до 500, длительность — от 15 до 480 минут.');
        }
        if (!$patterns) {
            throw new RuntimeException('Выберите хотя бы один день недели и время занятия.');
        }
    }

    private static function payload(array $data): array
    {
        $status = (string) ($data['status'] ?? 'active');
        return [
            'name' => trim((string) $data['name']),
            'group_number' => trim((string) $data['group_number']),
            'classroom' => self::nullable($data['classroom'] ?? null),
            'study_program' => trim((string) $data['study_program']),
            'teacher_id' => (int) $data['teacher_id'],
            'starts_on' => (string) $data['starts_on'],
            'driving_starts_on' => self::dateOrNull($data['driving_starts_on'] ?? null),
            'internal_exam_on' => self::dateOrNull($data['internal_exam_on'] ?? null),
            'inspection_registration_on' => self::dateOrNull($data['inspection_registration_on'] ?? null),
            'planned_lesson_count' => (int) $data['planned_lesson_count'],
            'lesson_duration_minutes' => (int) $data['lesson_duration_minutes'],
            'status' => isset(self::STATUSES[$status]) ? $status : 'active',
            'notes' => self::nullable($data['notes'] ?? null),
        ];
    }

    private static function validDate(mixed $value): bool
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private static function dateOrNull(mixed $value): ?string
    {
        return self::validDate($value) ? trim((string) $value) : null;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
