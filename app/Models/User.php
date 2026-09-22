<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_INSTRUCTOR = 'instructor';
    public const ROLE_DEPUTY_DIRECTOR = 'deputy_director';
    public const ROLE_DIRECTOR = 'director';
    public const ROLE_STUDENT = 'student';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_DELETED = 'deleted';

    public const ROLES = [
        self::ROLE_ADMIN => 'Администратор',
        self::ROLE_TEACHER => 'Преподаватель',
        self::ROLE_INSTRUCTOR => 'Мастер производственного обучения',
        self::ROLE_DEPUTY_DIRECTOR => 'Заместитель генерального директора',
        self::ROLE_DIRECTOR => 'Директор',
        self::ROLE_STUDENT => 'Курсант',
    ];

    public int $id;
    public string $login;
    public ?string $email;
    public ?string $phone;
    public string $firstName;
    public string $lastName;
    public ?string $middleName;
    public string $role;
    public ?int $accessMonths;
    public ?string $accessExpiresAt;
    public string $status;
    public string $passwordHash;
    public bool $mustChangePassword;
    public ?string $lastLoginAt;
    public string $createdAt;
    public string $updatedAt;

    public static function fromRow(array $row): self
    {
        $user = new self();
        $user->id = (int) $row['id'];
        $user->login = (string) $row['login'];
        $user->email = $row['email'] !== null ? (string) $row['email'] : null;
        $user->phone = $row['phone'] !== null ? (string) $row['phone'] : null;
        $user->firstName = (string) $row['first_name'];
        $user->lastName = (string) $row['last_name'];
        $user->middleName = $row['middle_name'] !== null ? (string) $row['middle_name'] : null;
        $user->role = (string) $row['role'];
        $user->accessMonths = $row['access_months'] !== null ? (int) $row['access_months'] : null;
        $user->accessExpiresAt = $row['access_expires_at'] !== null ? (string) $row['access_expires_at'] : null;
        $user->status = (string) $row['status'];
        $user->passwordHash = (string) ($row['password_hash'] ?? '');
        $user->mustChangePassword = (bool) $row['must_change_password'];
        $user->lastLoginAt = $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null;
        $user->createdAt = (string) $row['created_at'];
        $user->updatedAt = (string) $row['updated_at'];
        return $user;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = :id AND status <> :deleted LIMIT 1');
        $stmt->execute(['id' => $id, 'deleted' => self::STATUS_DELETED]);
        $row = $stmt->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findForLogin(string $login): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE (LOWER(login) = LOWER(:login) OR LOWER(email) = LOWER(:email)) AND status <> :deleted LIMIT 1'
        );
        $stmt->execute(['login' => trim($login), 'email' => trim($login), 'deleted' => self::STATUS_DELETED]);
        $row = $stmt->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function all(array $filters = []): array
    {
        $where = ['status <> :deleted'];
        $params = ['deleted' => self::STATUS_DELETED];
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $where[] = '(login LIKE :search_login OR first_name LIKE :search_first OR last_name LIKE :search_last OR middle_name LIKE :search_middle OR email LIKE :search_email OR phone LIKE :search_phone)';
            foreach (['login', 'first', 'last', 'middle', 'email', 'phone'] as $field) {
                $params['search_' . $field] = '%' . $search . '%';
            }
        }
        if (!empty($filters['role']) && array_key_exists((string) $filters['role'], self::ROLES)) {
            $where[] = 'role = :role';
            $params['role'] = (string) $filters['role'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], [self::STATUS_ACTIVE, self::STATUS_BLOCKED], true)) {
            $where[] = 'status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (isset($filters['group'])) {
            $where[] = $filters['group'] === 'students' ? 'role = :group_role' : 'role <> :group_role';
            $params['group_role'] = self::ROLE_STUDENT;
        }

        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute($params);
        return array_map([self::class, 'fromRow'], $stmt->fetchAll());
    }

    public static function create(array $data, int $createdBy): self
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (login, email, phone, first_name, last_name, middle_name, role, access_months, access_expires_at, status, password_hash, must_change_password, created_by, updated_by)
             VALUES (:login, :email, :phone, :first_name, :last_name, :middle_name, :role, :access_months,
             IF(:expiry_months IS NULL, NULL, DATE_ADD(NOW(), INTERVAL :expiry_interval MONTH)), :status, :password_hash, 1, :created_by, :updated_by)'
        );
        $stmt->execute([
            'login' => trim((string) $data['login']),
            'email' => self::nullable($data['email'] ?? null),
            'phone' => self::nullable($data['phone'] ?? null),
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'middle_name' => self::nullable($data['middle_name'] ?? null),
            'role' => (string) $data['role'],
            'access_months' => self::accessMonths($data),
            'expiry_months' => self::accessMonths($data),
            'expiry_interval' => self::accessMonths($data),
            'status' => self::STATUS_ACTIVE,
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
        return self::find((int) Database::connection()->lastInsertId());
    }

    public static function updateUser(int $id, array $data, int $updatedBy): ?self
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET login = :login, email = :email, phone = :phone, first_name = :first_name,
             last_name = :last_name, middle_name = :middle_name, role = :role,
             access_months = :access_months,
             access_expires_at = CASE WHEN :expiry_changed = 1 THEN
                 IF(:expiry_months IS NULL, NULL, DATE_ADD(NOW(), INTERVAL :expiry_interval MONTH))
                 ELSE access_expires_at END,
             updated_by = :updated_by
             WHERE id = :id AND status <> :deleted'
        );
        $stmt->execute([
            'login' => trim((string) $data['login']),
            'email' => self::nullable($data['email'] ?? null),
            'phone' => self::nullable($data['phone'] ?? null),
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'middle_name' => self::nullable($data['middle_name'] ?? null),
            'role' => (string) $data['role'],
            'access_months' => self::accessMonths($data),
            'expiry_changed' => (int) ($data['access_months_changed'] ?? 0),
            'expiry_months' => self::accessMonths($data),
            'expiry_interval' => self::accessMonths($data),
            'updated_by' => $updatedBy,
            'id' => $id,
            'deleted' => self::STATUS_DELETED,
        ]);
        return self::find($id);
    }

    public static function setStatus(int $id, string $status, int $updatedBy): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET status = :status, updated_by = :updated_by WHERE id = :id AND status <> :deleted'
        );
        return $stmt->execute(['status' => $status, 'updated_by' => $updatedBy, 'id' => $id, 'deleted' => self::STATUS_DELETED]);
    }

    public static function blockExpiredStudents(): void
    {
        Database::connection()->exec(
            "UPDATE users SET status = 'blocked' WHERE role = 'student' AND status = 'active'
             AND access_expires_at IS NOT NULL AND access_expires_at <= NOW()"
        );
    }

    public function accessExpired(): bool
    {
        if (!$this->isStudent() || $this->accessExpiresAt === null) {
            return false;
        }
        $stmt = Database::connection()->prepare('SELECT access_expires_at <= NOW() FROM users WHERE id = :id');
        $stmt->execute(['id' => $this->id]);
        return (bool) $stmt->fetchColumn();
    }

    public function canManageUsers(): bool
    {
        return in_array($this->role, [self::ROLE_DIRECTOR, self::ROLE_DEPUTY_DIRECTOR, self::ROLE_ADMIN], true);
    }

    public function canManage(self $target): bool
    {
        return $this->canManageUsers() && ($this->role !== self::ROLE_ADMIN || $target->isStudent());
    }

    public function canAssignRole(string $role): bool
    {
        return $this->canManageUsers() && array_key_exists($role, self::ROLES)
            && ($this->role !== self::ROLE_ADMIN || $role === self::ROLE_STUDENT);
    }

    public static function softDelete(int $id, int $updatedBy): bool
    {
        $suffix = bin2hex(random_bytes(5));
        $stmt = Database::connection()->prepare(
            'UPDATE users SET status = :deleted, login = CONCAT(login, :suffix), email = NULL, phone = NULL,
             updated_by = :updated_by, deleted_at = NOW() WHERE id = :id AND status <> :deleted_check'
        );
        return $stmt->execute([
            'deleted' => self::STATUS_DELETED,
            'suffix' => '.deleted.' . $suffix,
            'updated_by' => $updatedBy,
            'id' => $id,
            'deleted_check' => self::STATUS_DELETED,
        ]);
    }

    public static function resetPassword(int $id, string $password, int $updatedBy): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET password_hash = :password_hash, must_change_password = 1, updated_by = :updated_by WHERE id = :id AND status <> :deleted'
            );
            $stmt->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_by' => $updatedBy,
                'id' => $id,
                'deleted' => self::STATUS_DELETED,
            ]);
            $event = $pdo->prepare('INSERT INTO password_reset_events (user_id, reset_by) VALUES (:user_id, :reset_by)');
            $event->execute(['user_id' => $id, 'reset_by' => $updatedBy]);
            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function changePassword(int $id, string $password): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :password_hash, must_change_password = 0, updated_at = NOW() WHERE id = :id'
        );
        return $stmt->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function touchLastLogin(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function stats(): array
    {
        $pdo = Database::connection();
        $students = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND role = 'student'")->fetchColumn();
        $staff = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND role <> 'student'")->fetchColumn();
        return compact('students', 'staff');
    }

    public static function chatContacts(self $current): array
    {
        if ($current->isStudent()) {
            $sql = "SELECT * FROM users WHERE status = 'active' AND role <> 'student' AND id <> :id ORDER BY FIELD(role, 'instructor', 'admin', 'teacher', 'deputy_director', 'director'), last_name";
        } else {
            $sql = "SELECT * FROM users WHERE status = 'active' AND role = 'student' AND id <> :id ORDER BY last_name, first_name";
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $current->id]);
        return array_map([self::class, 'fromRow'], $stmt->fetchAll());
    }

    public function fullName(): string
    {
        return trim($this->lastName . ' ' . $this->firstName . ' ' . ($this->middleName ?? ''));
    }

    public function shortName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function roleTitle(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function accessMonths(array $data): ?int
    {
        return ($data['role'] ?? null) === self::ROLE_STUDENT && ($data['access_months'] ?? '') !== ''
            ? (int) $data['access_months'] : null;
    }
}
