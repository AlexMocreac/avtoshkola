<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Validator;
use App\Models\User;

function actor(string $role): User
{
    $user = new User();
    $user->role = $role;
    return $user;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$admin = actor(User::ROLE_ADMIN);
$student = actor(User::ROLE_STUDENT);
$teacher = actor(User::ROLE_TEACHER);
$instructor = actor(User::ROLE_INSTRUCTOR);
$director = actor(User::ROLE_DIRECTOR);
$deputy = actor(User::ROLE_DEPUTY_DIRECTOR);

check($admin->canManage($student), 'Administrator must manage students.');
check(!$admin->canManage($teacher), 'Administrator must not manage staff.');
check(!$admin->canAssignRole(User::ROLE_DIRECTOR), 'Administrator must not assign director.');
check($admin->canAssignRole(User::ROLE_STUDENT), 'Administrator must assign student.');
foreach ([$director, $deputy] as $manager) {
    check($manager->canManage($teacher), 'Leadership must manage staff.');
    check($manager->canAssignRole(User::ROLE_DIRECTOR), 'Leadership must assign all roles.');
}
foreach ([$teacher, $instructor, $student] as $user) {
    check(!$user->canManageUsers(), 'Non-manager must not manage users.');
}
check($director->canUseCrm() && $deputy->canUseCrm() && $admin->canUseCrm(), 'Management must access CRM.');
check(!$teacher->canUseCrm() && !$student->canUseCrm(), 'Non-management must not access CRM.');
check($teacher->canUseTasks() && $instructor->canUseTasks(), 'Employees must access tasks.');
check(!$student->canUseTasks(), 'Students must not access tasks.');

$input = [
    'login' => 'test.student',
    'first_name' => 'Иван',
    'last_name' => 'Иванов',
    'role' => User::ROLE_STUDENT,
    'password' => 'Student123!',
    'access_months' => '6',
];
check(Validator::user($input) === [], 'Valid student access term was rejected.');
foreach (['0', '121', 'x'] as $invalid) {
    $input['access_months'] = $invalid;
    check(isset(Validator::user($input)['access_months']), 'Invalid student access term was accepted.');
}

$task = [
    'description' => 'Проверить документы',
    'task_date' => date('Y-m-d'),
    'due_at' => '',
    'assignee_ids' => ['1', '2'],
    'recurrence' => 'monthly',
    'recurrence_day' => '15',
    'recurrence_until' => date('Y-m-d', strtotime('+1 year')),
];
check(Validator::task($task) === [], 'Valid recurring task was rejected.');
$task['recurrence_day'] = '32';
check(Validator::task($task) !== [], 'Invalid recurrence day was accepted.');

$lead = ['first_name' => 'Иван', 'status' => 'new', 'direction' => 'driving_school', 'email' => 'ivan@example.test', 'phone' => '+7 900 000-00-00'];
check(Validator::lead($lead) === [], 'Valid lead was rejected.');

$contract = [
    'contract_number' => 'TEST-1',
    'counterparty' => 'Иванов Иван',
    'subject' => 'Обучение',
    'direction' => 'driving_school',
    'signed_on' => date('Y-m-d'),
    'starts_on' => '',
    'ends_on' => '',
    'amount' => '1000.00',
    'status' => 'active',
];
check(Validator::contract($contract) === [], 'Valid contract was rejected.');

echo "PASS access-policy\n";
