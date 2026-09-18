<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\User;

$options = getopt('', ['login::', 'email::', 'first-name::', 'last-name::', 'password::']);
$login = (string) ($options['login'] ?? 'admin');
$email = (string) ($options['email'] ?? 'admin@avtoshkola.local');
$firstName = (string) ($options['first-name'] ?? 'Главный');
$lastName = (string) ($options['last-name'] ?? 'Администратор');
$password = (string) ($options['password'] ?? ('Avto!' . bin2hex(random_bytes(5))));

if (User::findForLogin($login) || User::findForLogin($email)) {
    fwrite(STDERR, "Администратор с таким логином или email уже существует.\n");
    exit(1);
}

$stmt = Database::connection()->prepare(
    'INSERT INTO users (login, email, first_name, last_name, role, status, password_hash, must_change_password)
     VALUES (:login, :email, :first_name, :last_name, :role, :status, :password_hash, 1)'
);
$stmt->execute([
    'login' => $login,
    'email' => $email,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'role' => User::ROLE_ADMIN,
    'status' => User::STATUS_ACTIVE,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "LOGIN={$login}\nPASSWORD={$password}\n";

