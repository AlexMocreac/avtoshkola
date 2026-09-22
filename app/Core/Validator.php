<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function user(array $input, bool $creating = true): array
    {
        $errors = [];
        $login = trim((string) ($input['login'] ?? ''));
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $role = (string) ($input['role'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $login)) {
            $errors['login'] = 'Логин: 3–80 символов, латиница, цифры, точка, дефис или подчёркивание.';
        }
        if (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 80) {
            $errors['first_name'] = 'Укажите имя.';
        }
        if (mb_strlen($lastName) < 2 || mb_strlen($lastName) > 80) {
            $errors['last_name'] = 'Укажите фамилию.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Укажите корректный email.';
        }
        if ($phone !== '' && !preg_match('/^[+0-9()\s-]{7,32}$/u', $phone)) {
            $errors['phone'] = 'Укажите корректный телефон.';
        }
        if (!array_key_exists($role, \App\Models\User::ROLES)) {
            $errors['role'] = 'Выберите роль.';
        }
        $months = (string) ($input['access_months'] ?? '');
        if ($role === \App\Models\User::ROLE_STUDENT && $months !== '' &&
            (!ctype_digit($months) || (int) $months < 1 || (int) $months > 120)) {
            $errors['access_months'] = 'Укажите срок от 1 до 120 месяцев или оставьте поле пустым.';
        }

        if ($creating) {
            $password = (string) ($input['password'] ?? '');
            if (mb_strlen($password) < 10) {
                $errors['password'] = 'Пароль должен содержать не менее 10 символов.';
            }
        }

        return $errors;
    }
}
