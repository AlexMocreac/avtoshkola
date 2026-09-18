<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\User;
use PDOException;

final class UserController
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'role' => (string) ($_GET['role'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        View::render('users/index', [
            'pageTitle' => 'Пользователи',
            'pageEyebrow' => 'Управление доступом',
            'currentUser' => $admin,
            'users' => User::all($filters),
            'filters' => $filters,
            'roles' => User::ROLES,
        ]);
    }

    public function create(): void
    {
        $admin = Auth::requireAdmin();
        Csrf::enforce(true);
        $errors = Validator::user($_POST, true);
        if ($errors) {
            Response::json(['ok' => false, 'message' => 'Проверьте заполнение полей.', 'errors' => $errors], 422);
        }

        try {
            $user = User::create($_POST, $admin->id);
            Response::json([
                'ok' => true,
                'message' => 'Пользователь создан. Передайте ему временный пароль безопасным способом.',
                'user' => $this->serialize($user),
            ], 201);
        } catch (PDOException $exception) {
            $this->uniqueError($exception);
        }
    }

    public function update(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }

        $errors = Validator::user($_POST, false);
        if ($errors) {
            Response::json(['ok' => false, 'message' => 'Проверьте заполнение полей.', 'errors' => $errors], 422);
        }
        if ($target->role === User::ROLE_ADMIN && ($_POST['role'] ?? '') !== User::ROLE_ADMIN && User::countActiveAdmins() <= 1) {
            Response::json(['ok' => false, 'message' => 'Нельзя изменить роль единственного активного администратора.'], 422);
        }

        try {
            $user = User::updateUser($target->id, $_POST, $admin->id);
            Response::json(['ok' => true, 'message' => 'Данные пользователя обновлены.', 'user' => $this->serialize($user)]);
        } catch (PDOException $exception) {
            $this->uniqueError($exception);
        }
    }

    public function status(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }
        if ($target->id === $admin->id) {
            Response::json(['ok' => false, 'message' => 'Нельзя заблокировать собственную учётную запись.'], 422);
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_BLOCKED], true)) {
            Response::json(['ok' => false, 'message' => 'Некорректный статус.'], 422);
        }
        if ($target->role === User::ROLE_ADMIN && $status === User::STATUS_BLOCKED && User::countActiveAdmins() <= 1) {
            Response::json(['ok' => false, 'message' => 'Нельзя заблокировать единственного активного администратора.'], 422);
        }

        User::setStatus($target->id, $status, $admin->id);
        Response::json(['ok' => true, 'message' => $status === User::STATUS_BLOCKED ? 'Доступ пользователя заблокирован.' : 'Доступ пользователя восстановлен.']);
    }

    public function resetPassword(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }

        $password = $this->temporaryPassword();
        User::resetPassword($target->id, $password, $admin->id);
        Response::json([
            'ok' => true,
            'message' => 'Создан новый временный пароль.',
            'password' => $password,
            'user_name' => $target->fullName(),
        ]);
    }

    public function delete(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }
        if ($target->id === $admin->id) {
            Response::json(['ok' => false, 'message' => 'Нельзя удалить собственную учётную запись.'], 422);
        }
        if ($target->role === User::ROLE_ADMIN && User::countActiveAdmins() <= 1) {
            Response::json(['ok' => false, 'message' => 'Нельзя удалить единственного активного администратора.'], 422);
        }

        User::softDelete($target->id, $admin->id);
        Response::json(['ok' => true, 'message' => 'Пользователь удалён.']);
    }

    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'login' => $user->login,
            'email' => $user->email,
            'phone' => $user->phone,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'middle_name' => $user->middleName,
            'role' => $user->role,
            'role_title' => $user->roleTitle(),
            'status' => $user->status,
            'full_name' => $user->fullName(),
        ];
    }

    private function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $characters = ['A', '9', '!'];
        for ($i = 0; $i < 11; $i++) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $swap = random_int(0, $i);
            [$characters[$i], $characters[$swap]] = [$characters[$swap], $characters[$i]];
        }
        return implode('', $characters);
    }

    private function uniqueError(PDOException $exception): never
    {
        if ((string) $exception->getCode() === '23000') {
            Response::json(['ok' => false, 'message' => 'Такой логин или email уже используется.'], 422);
        }
        throw $exception;
    }
}
