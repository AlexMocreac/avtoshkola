<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::user()->mustChangePassword ? '/change-password' : '/dashboard');
        }

        View::render('auth/login', [
            'pageTitle' => 'Вход в систему',
            'error' => $_SESSION['_login_error'] ?? null,
            'login' => $_SESSION['_login_value'] ?? '',
        ], 'layouts/auth');
        unset($_SESSION['_login_error'], $_SESSION['_login_value']);
    }

    public function login(): void
    {
        Csrf::enforce();
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $attempts = array_values(array_filter(
            $_SESSION['_login_attempts'] ?? [],
            static fn (int $timestamp): bool => $timestamp > time() - 300
        ));
        if (count($attempts) >= 8) {
            $_SESSION['_login_error'] = 'Слишком много попыток. Подождите 5 минут и попробуйте снова.';
            $_SESSION['_login_value'] = $login;
            Response::redirect('/login');
        }

        if (!Auth::attempt($login, $password)) {
            $attempts[] = time();
            $_SESSION['_login_attempts'] = $attempts;
            $_SESSION['_login_error'] = 'Неверный логин или пароль. Если доступ заблокирован, обратитесь к администратору.';
            $_SESSION['_login_value'] = $login;
            Response::redirect('/login');
        }

        unset($_SESSION['_login_attempts']);
        $user = Auth::user();
        if ($user->mustChangePassword) {
            Response::redirect('/change-password');
        }

        $intended = $_SESSION['intended_url'] ?? url('/dashboard');
        unset($_SESSION['intended_url']);
        header('Location: ' . $intended);
        exit;
    }

    public function logout(): void
    {
        Csrf::enforce();
        Auth::logout();
        Response::redirect('/login');
    }

    public function showChangePassword(): void
    {
        $user = Auth::requireLogin(true);
        View::render('auth/change-password', [
            'pageTitle' => 'Новый пароль',
            'currentUser' => $user,
            'forced' => $user->mustChangePassword,
        ], 'layouts/auth');
    }

    public function changePassword(): void
    {
        $user = Auth::requireLogin(true);
        Csrf::enforce();

        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!password_verify($current, $user->passwordHash)) {
            Flash::set('error', 'Текущий пароль указан неверно.');
            Response::redirect('/change-password');
        }
        if (mb_strlen($password) < 10 || !preg_match('/[A-Za-zА-Яа-я]/u', $password) || !preg_match('/\d/', $password)) {
            Flash::set('error', 'Новый пароль должен содержать не менее 10 символов, буквы и цифры.');
            Response::redirect('/change-password');
        }
        if ($password !== $confirmation) {
            Flash::set('error', 'Подтверждение пароля не совпадает.');
            Response::redirect('/change-password');
        }
        if (password_verify($password, $user->passwordHash)) {
            Flash::set('error', 'Новый пароль должен отличаться от текущего.');
            Response::redirect('/change-password');
        }

        \App\Models\User::changePassword($user->id, $password);
        Flash::set('success', 'Пароль обновлён. Добро пожаловать!');
        Response::redirect('/dashboard');
    }
}

