<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\User;

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(5));
    $data = [
        'login' => 'access.test.' . $suffix,
        'email' => null,
        'phone' => null,
        'first_name' => 'Тестовый',
        'last_name' => 'Курсант',
        'middle_name' => null,
        'role' => User::ROLE_STUDENT,
        'access_months' => '6',
        'password' => 'Student123!',
    ];

    $student = User::create($data, 1);
    if ($student->accessMonths !== 6 || $student->accessExpiresAt === null) {
        throw new RuntimeException('Student expiry was not created.');
    }
    $originalExpiry = $student->accessExpiresAt;

    $data['access_months_changed'] = 0;
    $student = User::updateUser($student->id, $data, 1);
    if ($student->accessExpiresAt !== $originalExpiry) {
        throw new RuntimeException('Unchanged duration shifted the expiry.');
    }

    $data['access_months'] = '7';
    $data['access_months_changed'] = 1;
    $student = User::updateUser($student->id, $data, 1);
    if ($student->accessMonths !== 7 || $student->accessExpiresAt <= $originalExpiry) {
        throw new RuntimeException('New duration did not extend the expiry.');
    }

    $stmt = $pdo->prepare('UPDATE users SET access_expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = :id');
    $stmt->execute(['id' => $student->id]);
    User::blockExpiredStudents();
    $student = User::find($student->id);
    if ($student->status !== User::STATUS_BLOCKED || !$student->accessExpired()) {
        throw new RuntimeException('Expired student was not blocked.');
    }

    echo "PASS database-access-expiry\n";
} finally {
    $pdo->rollBack();
}
