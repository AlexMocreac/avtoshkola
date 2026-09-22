<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\User;

$options = getopt('', ['login:']);
$login = trim((string) ($options['login'] ?? ''));
if ($login === '') {
    fwrite(STDERR, "Укажите --login существующего администратора.\n");
    exit(1);
}

$user = User::findForLogin($login);
if (!$user || $user->login !== $login || $user->role !== User::ROLE_ADMIN || $user->status !== User::STATUS_ACTIVE) {
    fwrite(STDERR, "Активный администратор с таким логином не найден.\n");
    exit(1);
}

$stmt = Database::connection()->prepare('UPDATE users SET role = :role WHERE id = :id AND role = :current_role AND status = :status');
$stmt->execute([
    'role' => User::ROLE_DIRECTOR,
    'id' => $user->id,
    'current_role' => User::ROLE_ADMIN,
    'status' => User::STATUS_ACTIVE,
]);
if ($stmt->rowCount() !== 1) {
    fwrite(STDERR, "Роль не изменена: учётная запись была обновлена другим процессом.\n");
    exit(1);
}

echo "Роль директора назначена: {$user->login}\n";
