<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

final class StudentProfile
{
    public const TRAINING_STATUSES = [
        'active' => 'Обучается',
        'dismissed' => 'Отчислен',
        'completed' => 'Обучение завершено',
    ];

    public const GEARBOXES = [
        'manual' => 'МКПП',
        'automatic' => 'АКПП',
    ];

    public const GENDERS = [
        'male' => 'Мужской',
        'female' => 'Женский',
    ];

    public const CUSTOMER_TYPES = [
        'student' => 'Сам курсант',
        'person' => 'Другое физическое лицо',
        'company' => 'Юридическое лицо',
    ];

    public static function ensure(int $studentId): void
    {
        $stmt = Database::connection()->prepare('INSERT IGNORE INTO student_profiles (student_id) VALUES (:student_id)');
        $stmt->execute(['student_id' => $studentId]);
    }

    public static function all(User $viewer, array $filters = []): array
    {
        $where = ['u.role = "student"', 'u.status <> "deleted"'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(INSTR(LOWER(CONCAT_WS(" ", u.last_name, u.first_name, u.middle_name)), LOWER(:search_name)) > 0
                OR INSTR(LOWER(u.login), LOWER(:search_login)) > 0 OR INSTR(COALESCE(u.phone, ""), :search_phone) > 0
                OR INSTR(LOWER(COALESCE(c.group_number, "")), LOWER(:search_group)) > 0)';
            foreach (['name', 'login', 'phone', 'group'] as $key) {
                $params['search_' . $key] = $search;
            }
        }
        if (!empty($filters['class_id'])) {
            $where[] = 'c.id = :class_id';
            $params['class_id'] = (int) $filters['class_id'];
        }
        if (!empty($filters['training_status']) && isset(self::TRAINING_STATUSES[(string) $filters['training_status']])) {
            $where[] = 'COALESCE(sp.training_status, "active") = :training_status';
            $params['training_status'] = (string) $filters['training_status'];
        }
        if ($viewer->role === User::ROLE_TEACHER) {
            $where[] = 'c.teacher_id = :teacher_id';
            $params['teacher_id'] = $viewer->id;
        }
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.login, u.email, u.phone, u.first_name, u.last_name, u.middle_name, u.status,
                    sp.training_status, sp.driving_allowed, sp.registration_number, sp.birth_date,
                    c.id AS class_id, c.name AS class_name, c.group_number, c.teacher_id,
                    COALESCE((SELECT SUM(s.amount) FROM student_sales s WHERE s.student_id = u.id AND s.status = "active"), 0) AS sold_total,
                    COALESCE((SELECT SUM(p.amount) FROM student_payments p WHERE p.student_id = u.id), 0) AS paid_total
             FROM users u
             LEFT JOIN student_profiles sp ON sp.student_id = u.id
             LEFT JOIN class_enrollments ce ON ce.student_id = u.id AND ce.status = "active"
             LEFT JOIN student_classes c ON c.id = ce.class_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $studentId): ?array
    {
        self::ensure($studentId);
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.login, u.email, u.phone, u.first_name, u.last_name, u.middle_name,
                    u.status AS account_status, u.access_expires_at, u.created_at AS account_created_at, sp.*
             FROM users u INNER JOIN student_profiles sp ON sp.student_id = u.id
             WHERE u.id = :student_id AND u.role = "student" AND u.status <> "deleted" LIMIT 1'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $studentId, array $data): void
    {
        self::ensure($studentId);
        $gearbox = (string) ($data['gearbox'] ?? '');
        $gender = (string) ($data['gender'] ?? '');
        $customerType = (string) ($data['customer_type'] ?? 'student');
        $stmt = Database::connection()->prepare(
            'UPDATE student_profiles SET training_end_on = :training_end_on, gearbox = :gearbox, gender = :gender,
             birth_date = :birth_date, citizenship = :citizenship, registration_number = :registration_number,
             birth_place = :birth_place, permanent_address = :permanent_address, temporary_address = :temporary_address,
             snils = :snils, customer_type = :customer_type, customer_name = :customer_name,
             customer_details = :customer_details, notes = :notes WHERE student_id = :student_id'
        );
        $stmt->execute([
            'training_end_on' => self::dateOrNull($data['training_end_on'] ?? null),
            'gearbox' => isset(self::GEARBOXES[$gearbox]) ? $gearbox : null,
            'gender' => isset(self::GENDERS[$gender]) ? $gender : null,
            'birth_date' => self::dateOrNull($data['birth_date'] ?? null),
            'citizenship' => self::nullable($data['citizenship'] ?? null),
            'registration_number' => self::nullable($data['registration_number'] ?? null),
            'birth_place' => self::nullable($data['birth_place'] ?? null),
            'permanent_address' => self::nullable($data['permanent_address'] ?? null),
            'temporary_address' => self::nullable($data['temporary_address'] ?? null),
            'snils' => self::nullable($data['snils'] ?? null),
            'customer_type' => isset(self::CUSTOMER_TYPES[$customerType]) ? $customerType : 'student',
            'customer_name' => self::nullable($data['customer_name'] ?? null),
            'customer_details' => self::nullable($data['customer_details'] ?? null),
            'notes' => self::nullable($data['notes'] ?? null),
            'student_id' => $studentId,
        ]);
    }

    public static function setPhoto(int $studentId, string $path): void
    {
        self::ensure($studentId);
        $stmt = Database::connection()->prepare('UPDATE student_profiles SET photo_path = :photo_path WHERE student_id = :student_id');
        $stmt->execute(['photo_path' => $path, 'student_id' => $studentId]);
    }

    public static function setAction(int $studentId, string $action): void
    {
        self::ensure($studentId);
        $pdo = Database::connection();
        if ($action === 'allow_driving' || $action === 'deny_driving') {
            $stmt = $pdo->prepare('UPDATE student_profiles SET driving_allowed = :allowed WHERE student_id = :student_id');
            $stmt->execute(['allowed' => $action === 'allow_driving' ? 1 : 0, 'student_id' => $studentId]);
            return;
        }
        if (!in_array($action, ['dismiss', 'restore', 'complete'], true)) {
            throw new RuntimeException('Неизвестное действие с курсантом.');
        }
        $status = match ($action) {
            'dismiss' => 'dismissed',
            'complete' => 'completed',
            default => 'active',
        };
        $stmt = $pdo->prepare(
            'UPDATE student_profiles SET training_status = :status,
             dismissed_at = IF(:status_check = "dismissed", NOW(), NULL),
             driving_allowed = IF(:status_check_2 = "dismissed", 0, driving_allowed)
             WHERE student_id = :student_id'
        );
        $stmt->execute(['status' => $status, 'status_check' => $status, 'status_check_2' => $status, 'student_id' => $studentId]);
        if ($action === 'dismiss') {
            $pdo->prepare(
                'UPDATE class_enrollments SET status = "withdrawn", left_on = CURDATE()
                 WHERE student_id = :student_id AND status = "active"'
            )->execute(['student_id' => $studentId]);
        }
    }

    public static function documents(int $studentId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM student_documents WHERE student_id = :student_id ORDER BY id DESC');
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public static function addDocument(int $studentId, array $data, int $userId): int
    {
        $type = trim((string) ($data['document_type'] ?? ''));
        if ($type === '') {
            throw new RuntimeException('Укажите вид документа.');
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_documents
             (student_id, document_type, series, number, issued_on, expires_on, issued_by, created_by)
             VALUES (:student_id, :document_type, :series, :number, :issued_on, :expires_on, :issued_by, :created_by)'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'document_type' => $type,
            'series' => self::nullable($data['series'] ?? null),
            'number' => self::nullable($data['number'] ?? null),
            'issued_on' => self::dateOrNull($data['issued_on'] ?? null),
            'expires_on' => self::dateOrNull($data['expires_on'] ?? null),
            'issued_by' => self::nullable($data['issued_by'] ?? null),
            'created_by' => $userId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function contracts(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM crm_contracts WHERE student_id = :student_id AND deleted_at IS NULL ORDER BY signed_on DESC, id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public static function enrollmentHistory(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ce.*, c.name AS class_name, c.group_number
             FROM class_enrollments ce INNER JOIN student_classes c ON c.id = ce.class_id
             WHERE ce.student_id = :student_id ORDER BY ce.id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
