<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\ContractController;
use App\Controllers\DashboardController;
use App\Controllers\EventController;
use App\Controllers\LeadController;
use App\Controllers\TaskController;
use App\Controllers\UserController;
use App\Controllers\WarningController;
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

$router->get('/events', [EventController::class, 'index']);
$router->get('/warnings', [WarningController::class, 'index']);
$router->post('/warnings', [WarningController::class, 'create']);
$router->post('/warnings/{id}/complete', [WarningController::class, 'complete']);

$router->get('/crm', static function (): void {
    Response::redirect('/crm/leads');
});
$router->get('/crm/leads', [LeadController::class, 'index']);
$router->get('/crm/leads/{id}/history', [LeadController::class, 'history']);
$router->post('/crm/leads', [LeadController::class, 'create']);
$router->post('/crm/leads/{id}/update', [LeadController::class, 'update']);
$router->post('/crm/leads/{id}/status', [LeadController::class, 'status']);
$router->post('/crm/leads/{id}/archive', [LeadController::class, 'archive']);
$router->get('/crm/contracts', [ContractController::class, 'index']);
$router->get('/crm/contracts/files/{id}', [ContractController::class, 'download']);
$router->post('/crm/contracts', [ContractController::class, 'create']);
$router->post('/crm/contracts/{id}/update', [ContractController::class, 'update']);
$router->post('/crm/contracts/{id}/archive', [ContractController::class, 'archive']);

$router->get('/tasks', [TaskController::class, 'index']);
$router->get('/api/staff/search', [TaskController::class, 'assignees']);
$router->post('/tasks', [TaskController::class, 'create']);
$router->post('/tasks/{id}/complete', [TaskController::class, 'complete']);

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
