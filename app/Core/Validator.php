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
        if ($phone !== '' && !self::russianPhone($phone)) {
            $errors['phone'] = 'Укажите российский телефон в формате +7 (900) 000-00-00.';
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

    public static function lead(array $input): array
    {
        $errors = [];
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $company = trim((string) ($input['company_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($firstName === '' && $company === '') {
            $errors[] = 'Укажите имя клиента или название организации.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Укажите корректный email лида.';
        }
        if ($phone !== '' && !self::russianPhone($phone)) {
            $errors[] = 'Укажите российский телефон лида в формате +7 (900) 000-00-00.';
        }
        if (!isset(\App\Models\Lead::STATUSES[(string) ($input['status'] ?? '')])) {
            $errors[] = 'Выберите корректный этап воронки.';
        }
        if (!isset(\App\Models\Lead::DIRECTIONS[(string) ($input['direction'] ?? '')])) {
            $errors[] = 'Выберите направление воронки.';
        }
        if (!self::optionalDateTime($input['next_contact_at'] ?? null)) {
            $errors[] = 'Укажите корректную дату следующего контакта.';
        }
        return $errors;
    }

    public static function contract(array $input): array
    {
        $errors = [];
        foreach (['counterparty' => 'ФИО клиента', 'subject' => 'предмет договора'] as $field => $label) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[] = 'Укажите ' . $label . '.';
            }
        }
        if (mb_strlen(trim((string) ($input['contract_number'] ?? ''))) > 80) {
            $errors[] = 'Номер договора не должен превышать 80 символов.';
        }
        if (mb_strlen(trim((string) ($input['registration_number'] ?? ''))) > 80) {
            $errors[] = 'Регистрационный номер не должен превышать 80 символов.';
        }
        $phone = trim((string) ($input['client_phone'] ?? ''));
        if ($phone !== '' && !self::russianPhone($phone)) {
            $errors[] = 'Укажите российский телефон клиента в формате +7 (900) 000-00-00.';
        }
        if (!self::date($input['signed_on'] ?? null)) {
            $errors[] = 'Укажите дату подписания.';
        }
        foreach (['starts_on', 'ends_on'] as $field) {
            if (!self::optionalDate($input[$field] ?? null)) {
                $errors[] = 'Проверьте даты действия договора.';
            }
        }
        if (!isset(\App\Models\Contract::STATUSES[(string) ($input['status'] ?? '')])) {
            $errors[] = 'Выберите корректный статус договора.';
        }
        if (!isset(\App\Models\Lead::DIRECTIONS[(string) ($input['direction'] ?? '')])) {
            $errors[] = 'Выберите корректное направление договора.';
        }
        $amount = str_replace(',', '.', trim((string) ($input['amount'] ?? '')));
        if ($amount !== '' && (!is_numeric($amount) || (float) $amount < 0)) {
            $errors[] = 'Сумма договора должна быть положительным числом.';
        }
        return $errors;
    }

    public static function task(array $input): array
    {
        $errors = [];
        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) < 3 || mb_strlen($description) > 5000) {
            $errors[] = 'Описание задачи должно содержать от 3 до 5000 символов.';
        }
        if (!self::date($input['task_date'] ?? null)) {
            $errors[] = 'Укажите дату постановки задачи.';
        }
        if (!self::optionalDateTime($input['due_at'] ?? null)) {
            $errors[] = 'Укажите корректный крайний срок.';
        }
        $assignees = array_values(array_filter(array_map('intval', (array) ($input['assignee_ids'] ?? []))));
        if (!$assignees) {
            $errors[] = 'Выберите хотя бы одного исполнителя.';
        }
        if (($input['recurrence'] ?? '') === 'monthly') {
            $day = (int) ($input['recurrence_day'] ?? 0);
            if ($day < 1 || $day > 31 || !self::date($input['recurrence_until'] ?? null)) {
                $errors[] = 'Для повторения укажите день месяца и дату окончания.';
            } elseif (self::date($input['task_date'] ?? null)) {
                $start = strtotime((string) $input['task_date']);
                $until = strtotime((string) $input['recurrence_until']);
                if ($until < $start || $until > strtotime('+1 year', $start)) {
                    $errors[] = 'Повторение можно настроить максимум на один год вперёд.';
                }
            }
        }
        return $errors;
    }

    public static function deadline(array $input): array
    {
        $errors = [];
        if (mb_strlen(trim((string) ($input['title'] ?? ''))) < 3) {
            $errors[] = 'Укажите название события.';
        }
        if (!self::dateTime($input['due_at'] ?? null)) {
            $errors[] = 'Укажите дату и время срока.';
        }
        return $errors;
    }

    private static function russianPhone(string $value): bool
    {
        if (!preg_match('/^[+0-9()\s-]+$/u', $value)) {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $value);
        return is_string($digits)
            && strlen($digits) === 11
            && ($digits[0] === '7' || $digits[0] === '8');
    }

    private static function date(mixed $value): bool
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private static function optionalDate(mixed $value): bool
    {
        return trim((string) $value) === '' || self::date($value);
    }

    private static function dateTime(mixed $value): bool
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d\\TH:i') === $value;
    }

    private static function optionalDateTime(mixed $value): bool
    {
        return trim((string) $value) === '' || self::dateTime($value);
    }
}
