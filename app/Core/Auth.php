<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static User|false|null $currentUser = null;

    public static function user(): ?User
    {
        if (self::$currentUser instanceof User) {
            return self::$currentUser;
        }
        if (self::$currentUser === false || empty($_SESSION['user_id'])) {
            return null;
        }

        User::blockExpiredStudents();
        $user = User::find((int) $_SESSION['user_id']);
        if (!$user || $user->status !== User::STATUS_ACTIVE) {
            self::logout();
            self::$currentUser = false;
            return null;
        }

        self::$currentUser = $user;
        return $user;
    }

    public static function check(): bool
    {
        return self::user() instanceof User;
    }

    public static function attempt(string $login, string $password): bool
    {
        User::blockExpiredStudents();
        $user = User::findForLogin($login);
        if (!$user || $user->status !== User::STATUS_ACTIVE || !password_verify($password, $user->passwordHash)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        self::$currentUser = $user;
        User::touchLastLogin($user->id);
        return true;
    }

    public static function logout(): void
    {
        self::$currentUser = false;
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    public static function requireLogin(bool $allowPasswordChange = false): User
    {
        $user = self::user();
        if (!$user) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? url('/dashboard');
            Response::redirect('/login');
        }
        if (!$allowPasswordChange && $user->mustChangePassword) {
            Response::redirect('/change-password');
        }
        return $user;
    }

    public static function requireUserManager(): User
    {
        $user = self::requireLogin();
        if (!$user->canManageUsers()) {
            if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                Response::json(['ok' => false, 'message' => 'Недостаточно прав для управления пользователями.'], 403);
            }
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Нет доступа', 'currentUser' => $user]);
            exit;
        }
        return $user;
    }

    public static function requireCrmAccess(): User
    {
        $user = self::requireLogin();
        if (!$user->canUseCrm()) {
            self::deny($user, 'Недостаточно прав для доступа к CRM.');
        }
        return $user;
    }

    public static function requireStaff(): User
    {
        $user = self::requireLogin();
        if (!$user->canUseTasks()) {
            self::deny($user, 'Раздел доступен только сотрудникам автошколы.');
        }
        return $user;
    }

    private static function deny(User $user, string $message): never
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            Response::json(['ok' => false, 'message' => $message], 403);
        }
        http_response_code(403);
        View::render('errors/403', ['pageTitle' => 'Нет доступа', 'currentUser' => $user]);
        exit;
    }
}
