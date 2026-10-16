<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class EducationCatalog
{
    public const BALANCE_UNITS = [
        'hours' => 'В часах',
        'money' => 'В рублях',
        'count' => 'Количество',
    ];

    public static function classFolders(User $viewer): array
    {
        $teacherFilter = $viewer->role === User::ROLE_TEACHER ? ' AND c.teacher_id = :teacher_id' : '';
        $sql = 'SELECT f.*, COUNT(DISTINCT c.id) AS class_count,
                       COUNT(DISTINCT ce.student_id) AS student_count
                FROM class_folders f
                LEFT JOIN student_classes c ON c.folder_id = f.id AND c.status <> "archived"' . $teacherFilter . '
                LEFT JOIN class_enrollments ce ON ce.class_id = c.id AND ce.status = "active"
                WHERE f.status = "active"
                GROUP BY f.id
                ' . ($viewer->role === User::ROLE_TEACHER ? 'HAVING class_count > 0' : '') . '
                ORDER BY f.sort_order, f.name';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($viewer->role === User::ROLE_TEACHER ? ['teacher_id' => $viewer->id] : []);
        return $stmt->fetchAll();
    }

    public static function findClassFolder(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM class_folders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function saveClassFolder(array $data, int $userId, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new RuntimeException('Название папки должно содержать от 2 до 120 символов.');
        }
        $payload = [
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => self::status($data['status'] ?? 'active'),
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE class_folders SET name = :name, sort_order = :sort_order, status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO class_folders (name, sort_order, status, created_by, updated_by)
             VALUES (:name, :sort_order, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function defaultClassFolderId(int $userId): int
    {
        $id = (int) Database::connection()->query('SELECT id FROM class_folders WHERE status = "active" ORDER BY sort_order, id LIMIT 1')->fetchColumn();
        return $id > 0 ? $id : self::saveClassFolder(['name' => 'Общие классы'], $userId);
    }

    public static function programFolders(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE f.status = "active"' : '';
        $folders = Database::connection()->query(
            'SELECT f.*, (SELECT COUNT(*) FROM training_programs p WHERE p.folder_id = f.id AND p.status = "active") AS item_count
             FROM training_program_folders f ' . $where . ' ORDER BY f.sort_order, f.name'
        )->fetchAll();
        $stmt = Database::connection()->prepare(
            'SELECT * FROM training_programs WHERE folder_id = :folder_id ' . ($activeOnly ? 'AND status = "active" ' : '') . 'ORDER BY sort_order, name'
        );
        foreach ($folders as &$folder) {
            $stmt->execute(['folder_id' => $folder['id']]);
            $folder['programs'] = $stmt->fetchAll();
        }
        unset($folder);
        return $folders;
    }

    public static function activePrograms(): array
    {
        return Database::connection()->query(
            'SELECT p.*, f.name AS folder_name FROM training_programs p
             INNER JOIN training_program_folders f ON f.id = p.folder_id
             WHERE p.status = "active" AND f.status = "active"
             ORDER BY f.sort_order, f.name, p.sort_order, p.name'
        )->fetchAll();
    }

    public static function findProgram(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM training_programs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function saveProgramFolder(array $data, int $userId, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new RuntimeException('Название папки должно содержать от 2 до 120 символов.');
        }
        $payload = [
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => self::status($data['status'] ?? 'active'),
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE training_program_folders SET name = :name, sort_order = :sort_order, status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO training_program_folders (name, sort_order, status, created_by, updated_by)
             VALUES (:name, :sort_order, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function saveProgram(array $data, int $userId, ?int $id = null): int
    {
        $folderId = (int) ($data['folder_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        if ($folderId < 1 || !self::programFolderExists($folderId)) {
            throw new RuntimeException('Выберите папку учебной программы.');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 190) {
            throw new RuntimeException('Название программы должно содержать от 2 до 190 символов.');
        }
        $payload = [
            'folder_id' => $folderId,
            'name' => $name,
            'code' => self::nullable($data['code'] ?? null),
            'description' => self::nullable($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => self::status($data['status'] ?? 'active'),
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE training_programs SET folder_id = :folder_id, name = :name, code = :code,
                 description = :description, sort_order = :sort_order, status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO training_programs (folder_id, name, code, description, sort_order, status, created_by, updated_by)
             VALUES (:folder_id, :name, :code, :description, :sort_order, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function findOrCreateProgram(string $name, int $userId): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Выберите учебную программу.');
        }
        $stmt = Database::connection()->prepare('SELECT id FROM training_programs WHERE name = :name ORDER BY id LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = (int) $stmt->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $folderId = (int) Database::connection()->query('SELECT id FROM training_program_folders WHERE status = "active" ORDER BY sort_order, id LIMIT 1')->fetchColumn();
        if ($folderId < 1) {
            $folderId = self::saveProgramFolder(['name' => 'Основные программы'], $userId);
        }
        return self::saveProgram(['folder_id' => $folderId, 'name' => $name], $userId);
    }

    public static function checkpoints(bool $activeOnly = false): array
    {
        return Database::connection()->query(
            'SELECT * FROM learning_checkpoints ' . ($activeOnly ? 'WHERE status = "active" ' : '') . 'ORDER BY sort_order, name'
        )->fetchAll();
    }

    public static function findCheckpoint(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM learning_checkpoints WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function saveCheckpoint(array $data, int $userId, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 190 || $code === '' || mb_strlen($code) > 24) {
            throw new RuntimeException('Укажите название и короткий код контрольной точки.');
        }
        $payload = [
            'name' => $name,
            'code' => $code,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => self::status($data['status'] ?? 'active'),
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE learning_checkpoints SET name = :name, code = :code, sort_order = :sort_order,
                 status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO learning_checkpoints (name, code, sort_order, status, created_by, updated_by)
             VALUES (:name, :code, :sort_order, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function checkpointStateMap(array $studentIds): array
    {
        $studentIds = self::positiveIds($studentIds);
        if (!$studentIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT student_id, checkpoint_id, is_completed, completed_at
             FROM student_checkpoint_states WHERE student_id IN (' . $placeholders . ')'
        );
        $stmt->execute($studentIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['student_id']][(int) $row['checkpoint_id']] = $row;
        }
        return $map;
    }

    public static function setCheckpointState(int $studentId, int $checkpointId, bool $completed, int $userId): void
    {
        if (!self::findCheckpoint($checkpointId)) {
            throw new RuntimeException('Контрольная точка не найдена.');
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_checkpoint_states (student_id, checkpoint_id, is_completed, completed_at, updated_by)
             VALUES (:student_id, :checkpoint_id, :is_completed, :completed_at, :updated_by)
             ON DUPLICATE KEY UPDATE is_completed = VALUES(is_completed), completed_at = VALUES(completed_at), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'checkpoint_id' => $checkpointId,
            'is_completed' => $completed ? 1 : 0,
            'completed_at' => $completed ? date('Y-m-d H:i:s') : null,
            'updated_by' => $userId,
        ]);
    }

    public static function balanceTypes(bool $activeOnly = false): array
    {
        return Database::connection()->query(
            'SELECT * FROM student_balance_types ' . ($activeOnly ? 'WHERE status = "active" ' : '') . 'ORDER BY sort_order, name'
        )->fetchAll();
    }

    public static function findBalanceType(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM student_balance_types WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function saveBalanceType(array $data, int $userId, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $unit = (string) ($data['unit'] ?? 'hours');
        $color = strtolower(trim((string) ($data['color'] ?? '#4768db')));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 190 || $code === '' || mb_strlen($code) > 24) {
            throw new RuntimeException('Укажите название и короткий код типа баланса.');
        }
        if (!isset(self::BALANCE_UNITS[$unit])) {
            throw new RuntimeException('Выберите единицу измерения баланса.');
        }
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            throw new RuntimeException('Цвет должен быть указан в формате #4768db.');
        }
        $payload = [
            'name' => $name,
            'code' => $code,
            'unit' => $unit,
            'color' => $color,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => self::status($data['status'] ?? 'active'),
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE student_balance_types SET name = :name, code = :code, unit = :unit, color = :color,
                 sort_order = :sort_order, status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_balance_types (code, name, unit, color, sort_order, status, created_by, updated_by)
             VALUES (:code, :name, :unit, :color, :sort_order, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function balanceValueMap(array $studentIds): array
    {
        $studentIds = self::positiveIds($studentIds);
        if (!$studentIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT student_id, balance_type_id, amount FROM student_balances WHERE student_id IN (' . $placeholders . ')'
        );
        $stmt->execute($studentIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['student_id']][(int) $row['balance_type_id']] = (float) $row['amount'];
        }
        return $map;
    }

    public static function setStudentBalances(int $studentId, array $amounts, int $userId): void
    {
        $types = self::balanceTypes(true);
        $allowed = array_fill_keys(array_map(static fn (array $type): int => (int) $type['id'], $types), true);
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_balances (student_id, balance_type_id, amount, updated_by)
             VALUES (:student_id, :balance_type_id, :amount, :updated_by)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_by = VALUES(updated_by)'
        );
        foreach ($amounts as $typeId => $amount) {
            $typeId = (int) $typeId;
            $normalized = str_replace(',', '.', trim((string) $amount));
            if (!isset($allowed[$typeId]) || !is_numeric($normalized) || (float) $normalized < -999999999 || (float) $normalized > 999999999) {
                throw new RuntimeException('Проверьте значения балансов курсанта.');
            }
            $stmt->execute([
                'student_id' => $studentId,
                'balance_type_id' => $typeId,
                'amount' => number_format((float) $normalized, 2, '.', ''),
                'updated_by' => $userId,
            ]);
        }
    }

    private static function programFolderExists(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM training_program_folders WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function positiveIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    private static function status(mixed $status): string
    {
        return (string) $status === 'archived' ? 'archived' : 'active';
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
