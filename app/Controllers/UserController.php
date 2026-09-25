<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\User;
use App\Models\ActivityLog;
use PDOException;

final class UserController
{
    public function index(): void
    {
        Response::redirect('/students');
    }

    public function students(): void
    {
        $this->list('students');
    }

    public function staff(): void
    {
        $this->list('staff');
    }

    private function list(string $group): void
    {
        $manager = Auth::requireUserManager();
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'role' => (string) ($_GET['role'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'group' => $group,
        ];
        View::render('users/index', [
            'pageTitle' => $group === 'students' ? 'Курсанты' : 'Сотрудники',
            'pageEyebrow' => 'Управление доступом',
            'currentUser' => $manager,
            'users' => User::all($filters),
            'filters' => $filters,
            'roles' => $group === 'students' ? [User::ROLE_STUDENT => User::ROLES[User::ROLE_STUDENT]] : array_diff_key(User::ROLES, [User::ROLE_STUDENT => true]),
            'assignableRoles' => $group === 'students' ? [User::ROLE_STUDENT => User::ROLES[User::ROLE_STUDENT]] : array_diff_key(User::ROLES, [User::ROLE_STUDENT => true]),
            'group' => $group,
            'canEditGroup' => $group === 'students' || !$manager->isAdmin(),
        ]);
    }

    public function create(): void
    {
        $admin = Auth::requireUserManager();
        Csrf::enforce(true);
        if (!$admin->canAssignRole((string) ($_POST['role'] ?? ''))) {
            Response::json(['ok' => false, 'message' => 'Недостаточно прав для назначения этой роли.'], 403);
        }
        $errors = Validator::user($_POST, true);
        if ($errors) {
            Response::json(['ok' => false, 'message' => 'Проверьте заполнение полей.', 'errors' => $errors], 422);
        }

        try {
            $user = User::create($_POST, $admin->id);
            ActivityLog::record('user.created', 'Создан пользователь', $admin, [$user], 'user', $user->id, [
                'name' => $user->fullName(),
                'role' => $user->roleTitle(),
                'role_key' => $user->role,
            ]);
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
        $admin = Auth::requireUserManager();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }
        $this->authorizeTarget($admin, $target);
        if (!$admin->canAssignRole((string) ($_POST['role'] ?? ''))) {
            Response::json(['ok' => false, 'message' => 'Недостаточно прав для назначения этой роли.'], 403);
        }

        $errors = Validator::user($_POST, false);
        if ($errors) {
            Response::json(['ok' => false, 'message' => 'Проверьте заполнение полей.', 'errors' => $errors], 422);
        }
        try {
            $data = $_POST;
            $newMonths = $data['role'] === User::ROLE_STUDENT && ($data['access_months'] ?? '') !== '' ? (int) $data['access_months'] : null;
            $data['access_months_changed'] = $target->role !== $data['role'] || $target->accessMonths !== $newMonths
                || ($data['restart_access'] ?? '') === '1';
            $user = User::updateUser($target->id, $data, $admin->id);
            $changes = ActivityLog::changes(
                $this->userAuditState($target),
                $this->userAuditState($user),
                $this->userAuditFields()
            );
            if ($changes) {
                ActivityLog::record('user.updated', 'Обновлена учётная запись', $admin, [$user], 'user', $user->id, [
                    'name' => $user->fullName(),
                    'role' => $user->roleTitle(),
                    'role_key' => $user->role,
                    'changes' => $changes,
                ]);
            }
            Response::json(['ok' => true, 'message' => 'Данные пользователя обновлены.', 'user' => $this->serialize($user)]);
        } catch (PDOException $exception) {
            $this->uniqueError($exception);
        }
    }

    public function status(string $id): void
    {
        $admin = Auth::requireUserManager();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }
        $this->authorizeTarget($admin, $target);
        if ($target->id === $admin->id) {
            Response::json(['ok' => false, 'message' => 'Нельзя заблокировать собственную учётную запись.'], 422);
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_BLOCKED], true)) {
            Response::json(['ok' => false, 'message' => 'Некорректный статус.'], 422);
        }
        if ($status === User::STATUS_ACTIVE && $target->accessExpired()) {
            Response::json(['ok' => false, 'message' => 'Срок доступа курсанта истёк. Сначала измените срок обучения.'], 422);
        }
        User::setStatus($target->id, $status, $admin->id);
        ActivityLog::record(
            $status === User::STATUS_BLOCKED ? 'user.blocked' : 'user.unblocked',
            $status === User::STATUS_BLOCKED ? 'Заблокирован доступ пользователя' : 'Восстановлен доступ пользователя',
            $admin,
            [$target],
            'user',
            $target->id,
            [
                'name' => $target->fullName(),
                'role_key' => $target->role,
                'changes' => [[
                    'field' => 'Статус',
                    'from' => $target->status === User::STATUS_ACTIVE ? 'Активен' : 'Заблокирован',
                    'to' => $status === User::STATUS_ACTIVE ? 'Активен' : 'Заблокирован',
                ]],
            ]
        );
        Response::json(['ok' => true, 'message' => $status === User::STATUS_BLOCKED ? 'Доступ пользователя заблокирован.' : 'Доступ пользователя восстановлен.']);
    }

    public function resetPassword(string $id): void
    {
        $admin = Auth::requireUserManager();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }
        $this->authorizeTarget($admin, $target);

        $password = $this->temporaryPassword();
        User::resetPassword($target->id, $password, $admin->id);
        ActivityLog::record('user.password_reset', 'Создан временный пароль', $admin, [$target], 'user', $target->id, [
            'name' => $target->fullName(),
            'role_key' => $target->role,
        ]);
        Response::json([
            'ok' => true,
            'message' => 'Создан новый временный пароль.',
            'password' => $password,
            'user_name' => $target->fullName(),
        ]);
    }

    public function delete(string $id): void
    {
        $admin = Auth::requireUserManager();
        Csrf::enforce(true);
        $target = User::find((int) $id);
        if (!$target) {
            Response::json(['ok' => false, 'message' => 'Пользователь не найден.'], 404);
        }
        $this->authorizeTarget($admin, $target);
        if ($target->id === $admin->id) {
            Response::json(['ok' => false, 'message' => 'Нельзя удалить собственную учётную запись.'], 422);
        }
        User::softDelete($target->id, $admin->id);
        ActivityLog::record('user.deleted', 'Пользователь удалён', $admin, [$target], 'user', $target->id, [
            'name' => $target->fullName(),
            'role_key' => $target->role,
            'changes' => [['field' => 'Состояние', 'from' => $target->status === User::STATUS_ACTIVE ? 'Активен' : 'Заблокирован', 'to' => 'Удалён']],
        ]);
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
            'access_months' => $user->accessMonths,
            'access_expires_at' => $user->accessExpiresAt,
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

    private function authorizeTarget(User $actor, User $target): void
    {
        if (!$actor->canManage($target)) {
            Response::json(['ok' => false, 'message' => 'Недостаточно прав для управления этим пользователем.'], 403);
        }
    }

    private function uniqueError(PDOException $exception): never
    {
        if ((string) $exception->getCode() === '23000') {
            Response::json(['ok' => false, 'message' => 'Такой логин или email уже используется.'], 422);
        }
        throw $exception;
    }

    private function userAuditState(User $user): array
    {
        return [
            'login' => $user->login,
            'email' => $user->email,
            'phone' => $user->phone,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'middle_name' => $user->middleName,
            'role' => $user->roleTitle(),
            'access_months' => $user->accessMonths !== null ? $user->accessMonths . ' мес.' : null,
            'access_expires_at' => $user->accessExpiresAt ? format_date($user->accessExpiresAt) : null,
            'status' => $user->status === User::STATUS_ACTIVE ? 'Активен' : 'Заблокирован',
        ];
    }

    private function userAuditFields(): array
    {
        return [
            'login' => 'Логин',
            'email' => 'Email',
            'phone' => 'Телефон',
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'middle_name' => 'Отчество',
            'role' => 'Роль',
            'access_months' => 'Срок доступа',
            'access_expires_at' => 'Доступ до',
            'status' => 'Статус',
        ];
    }
}
