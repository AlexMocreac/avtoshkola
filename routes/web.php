<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\CalendarController;
use App\Controllers\ContractController;
use App\Controllers\DashboardController;
use App\Controllers\EducationController;
use App\Controllers\EducationFinanceController;
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

$router->get('/education', static function (): void {
    Response::redirect('/education/classes');
});
$router->get('/education/classes', [EducationController::class, 'classes']);
$router->post('/education/classes', [EducationController::class, 'createClass']);
$router->get('/education/classes/{id}', [EducationController::class, 'showClass']);
$router->post('/education/classes/{id}/update', [EducationController::class, 'updateClass']);
$router->post('/education/classes/{id}/schedule', [EducationController::class, 'regenerateSchedule']);
$router->post('/education/classes/{id}/students', [EducationController::class, 'enrollStudent']);
$router->get('/education/classes/{id}/roster', [EducationController::class, 'roster']);
$router->get('/education/students', [EducationController::class, 'students']);
$router->get('/education/students/{id}', [EducationController::class, 'student']);
$router->post('/education/students/{id}/profile', [EducationController::class, 'updateStudent']);
$router->post('/education/students/{id}/action', [EducationController::class, 'studentAction']);
$router->post('/education/students/{id}/transfer', [EducationController::class, 'transferStudent']);
$router->post('/education/students/{id}/documents', [EducationController::class, 'addDocument']);
$router->post('/education/students/{id}/contracts', [EducationController::class, 'createContract']);
$router->post('/education/students/{id}/sales', [EducationController::class, 'createSale']);
$router->post('/education/students/{id}/payments', [EducationController::class, 'createPayment']);
$router->post('/education/students/{id}/files', [EducationController::class, 'uploadFile']);
$router->get('/education/students/{id}/photo', [EducationController::class, 'photo']);
$router->get('/education/students/{id}/contract/print', [EducationController::class, 'printContract']);
$router->get('/education/student-files/{id}', [EducationController::class, 'downloadFile']);
$router->get('/education/holidays', [EducationController::class, 'holidays']);
$router->post('/education/holidays', [EducationController::class, 'createHoliday']);
$router->post('/education/holidays/{id}/delete', [EducationController::class, 'deleteHoliday']);
$router->get('/education/kassas', [EducationFinanceController::class, 'kassas']);
$router->post('/education/kassas', [EducationFinanceController::class, 'createKassa']);
$router->post('/education/kassas/{id}/update', [EducationFinanceController::class, 'updateKassa']);
$router->get('/education/services', [EducationFinanceController::class, 'services']);
$router->post('/education/services', [EducationFinanceController::class, 'createService']);
$router->post('/education/services/{id}/update', [EducationFinanceController::class, 'updateService']);
$router->get('/calendar', [CalendarController::class, 'index']);

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
