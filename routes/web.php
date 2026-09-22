<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;
use App\Core\Auth;
use App\Core\Response;

$router->get('/', static function (): void {
    Response::redirect(Auth::check() ? '/dashboard' : '/login');
});

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/change-password', [AuthController::class, 'showChangePassword']);
$router->post('/change-password', [AuthController::class, 'changePassword']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/users', [UserController::class, 'index']);
$router->get('/students', [UserController::class, 'students']);
$router->get('/staff', [UserController::class, 'staff']);
$router->post('/users', [UserController::class, 'create']);
$router->post('/users/{id}/update', [UserController::class, 'update']);
$router->post('/users/{id}/status', [UserController::class, 'status']);
$router->post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
$router->post('/users/{id}/delete', [UserController::class, 'delete']);

$router->get('/chat', [ChatController::class, 'index']);
$router->post('/chat/conversations', [ChatController::class, 'createConversation']);
$router->get('/chat/messages', [ChatController::class, 'messages']);
$router->post('/chat/messages', [ChatController::class, 'send']);
$router->get('/chat/unread', [ChatController::class, 'unread']);
