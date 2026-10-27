<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\User;

$baseUrl = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8094'), '/');
$managerCookies = tempnam(sys_get_temp_dir(), 'avto-stage-four-manager-');
$teacherCookies = tempnam(sys_get_temp_dir(), 'avto-stage-four-teacher-');
$studentCookies = tempnam(sys_get_temp_dir(), 'avto-stage-four-student-');
$pdo = Database::connection();
$suffix = bin2hex(random_bytes(5));
$password = 'StageFourHttp123!';
$ids = ['manager' => 0, 'teacher' => 0, 'student' => 0, 'created_student' => 0, 'created_contract' => 0, 'class_folder' => 0, 'program_folder' => 0, 'program' => 0, 'checkpoint' => 0, 'balance_type' => 0, 'class' => 0];

function stageFourHttpCall(string $method, string $url, string $cookieFile, array $data = []): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method !== 'GET') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException(curl_error($curl));
    }
    return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $body];
}

function stageFourToken(string $html): string
{
    if (!preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('CSRF token not found.');
    }
    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function stageFourExpect(array $response, int $status, string $label): void
{
    if ($response['status'] !== $status) {
        throw new RuntimeException("{$label}: expected {$status}, got {$response['status']}: {$response['body']}");
    }
}

function stageFourLogin(string $baseUrl, string $cookieFile, string $login, string $password): string
{
    $page = stageFourHttpCall('GET', $baseUrl . '/login', $cookieFile);
    stageFourExpect($page, 200, 'login-page');
    $token = stageFourToken($page['body']);
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/login', $cookieFile, [
        '_token' => $token, 'login' => $login, 'password' => $password,
    ]), 302, 'login');
    return $token;
}

try {
    $insert = $pdo->prepare(
        'INSERT INTO users (login, email, first_name, last_name, role, status, password_hash, must_change_password)
         VALUES (:login, :email, :first_name, :last_name, :role, "active", :password_hash, 0)'
    );
    foreach ([
        'manager' => [User::ROLE_DIRECTOR, 'HTTP4', 'Директор'],
        'teacher' => [User::ROLE_TEACHER, 'HTTP4', 'Преподаватель'],
        'student' => [User::ROLE_STUDENT, 'HTTP4', 'Курсант'],
    ] as $key => [$role, $firstName, $lastName]) {
        $login = 'stage4.' . $key . '.' . $suffix;
        $insert->execute([
            'login' => $login,
            'email' => $login . '@example.test',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $ids[$key] = (int) $pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO student_profiles (student_id) VALUES (:student_id)')->execute(['student_id' => $ids['student']]);

    $token = stageFourLogin($baseUrl, $managerCookies, 'stage4.manager.' . $suffix, $password);
    foreach ([
        '/education/programs' => 'Учебные программы',
        '/education/checkpoints' => 'Контрольные точки обучения',
        '/education/balance-types' => 'Типы балансов курсанта',
    ] as $path => $needle) {
        $page = stageFourHttpCall('GET', $baseUrl . $path, $managerCookies);
        stageFourExpect($page, 200, $path);
        if (!str_contains($page['body'], $needle)) {
            throw new RuntimeException($path . ' is missing its catalog heading.');
        }
    }

    $classFolderName = 'HTTP4 классы ' . $suffix;
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/class-folders', $managerCookies, [
        '_token' => $token, 'name' => $classFolderName, 'status' => 'active',
    ]), 302, 'class-folder-create');
    $lookup = $pdo->prepare('SELECT id FROM class_folders WHERE name = :name');
    $lookup->execute(['name' => $classFolderName]);
    $ids['class_folder'] = (int) $lookup->fetchColumn();

    $programFolderName = 'HTTP4 программы ' . $suffix;
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/program-folders', $managerCookies, [
        '_token' => $token, 'name' => $programFolderName, 'status' => 'active',
    ]), 302, 'program-folder-create');
    $lookup = $pdo->prepare('SELECT id FROM training_program_folders WHERE name = :name');
    $lookup->execute(['name' => $programFolderName]);
    $ids['program_folder'] = (int) $lookup->fetchColumn();

    $programName = 'HTTP4 программа ' . $suffix;
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/programs', $managerCookies, [
        '_token' => $token, 'folder_id' => $ids['program_folder'], 'name' => $programName, 'code' => 'P4-' . $suffix, 'status' => 'active',
    ]), 302, 'program-create');
    $lookup = $pdo->prepare('SELECT id FROM training_programs WHERE name = :name');
    $lookup->execute(['name' => $programName]);
    $ids['program'] = (int) $lookup->fetchColumn();

    $checkpointCode = 'КТ4-' . $suffix;
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/checkpoints', $managerCookies, [
        '_token' => $token, 'name' => 'HTTP4 контрольная точка', 'code' => $checkpointCode, 'sort_order' => 4, 'status' => 'active',
    ]), 302, 'checkpoint-create');
    $lookup = $pdo->prepare('SELECT id FROM learning_checkpoints WHERE code = :code');
    $lookup->execute(['code' => $checkpointCode]);
    $ids['checkpoint'] = (int) $lookup->fetchColumn();

    $balanceCode = 'Б4-' . $suffix;
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/balance-types', $managerCookies, [
        '_token' => $token, 'name' => 'HTTP4 баланс', 'code' => $balanceCode, 'unit' => 'hours', 'color' => '#278766', 'sort_order' => 4, 'status' => 'active',
    ]), 302, 'balance-type-create');
    $lookup = $pdo->prepare('SELECT id FROM student_balance_types WHERE code = :code');
    $lookup->execute(['code' => $balanceCode]);
    $ids['balance_type'] = (int) $lookup->fetchColumn();

    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/classes', $managerCookies, [
        '_token' => $token,
        'folder_id' => $ids['class_folder'],
        'program_id' => $ids['program'],
        'name' => 'HTTP4 категория B',
        'group_number' => 'HTTP4-' . $suffix,
        'classroom' => 'Кабинет 4',
        'teacher_id' => $ids['teacher'],
        'starts_on' => date('Y-m-d', strtotime('+1 day')),
        'planned_lesson_count' => '3',
        'lesson_duration_minutes' => '90',
        'status' => 'active',
        'schedule' => [1 => '09:00', 3 => '09:00'],
    ]), 302, 'class-create');
    $lookup = $pdo->prepare('SELECT id FROM student_classes WHERE group_number = :group_number');
    $lookup->execute(['group_number' => 'HTTP4-' . $suffix]);
    $ids['class'] = (int) $lookup->fetchColumn();
    if (!$ids['class']) {
        throw new RuntimeException('Class was not created with folder and program relationships.');
    }

    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/classes/' . $ids['class'] . '/students', $managerCookies, [
        '_token' => $token, 'student_id' => $ids['student'], 'joined_on' => date('Y-m-d'),
    ]), 302, 'student-enroll');
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/students/' . $ids['student'] . '/balances', $managerCookies, [
        '_token' => $token, 'balances' => [$ids['balance_type'] => '16.5'],
    ]), 302, 'student-balance-update');

    $createdLogin = 'stage4.created.' . $suffix;
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/classes/' . $ids['class'] . '/students/create', $managerCookies, [
        '_token' => $token,
        'last_name' => 'Новый',
        'first_name' => 'Курсант',
        'middle_name' => 'HTTP4',
        'login' => $createdLogin,
        'password' => $password,
        'access_months' => '6',
        'gender' => 'male',
        'birth_date' => '2001-02-03',
        'citizenship' => 'Россия',
        'registration_number' => 'REG-' . $suffix,
        'gearbox' => 'manual',
        'joined_on' => date('Y-m-d'),
        'signed_on' => date('Y-m-d'),
        'contract_amount' => '42000',
    ]), 201, 'student-create-and-enroll');
    $lookup = $pdo->prepare('SELECT id FROM users WHERE login = :login');
    $lookup->execute(['login' => $createdLogin]);
    $ids['created_student'] = (int) $lookup->fetchColumn();
    $createdProfile = $pdo->prepare(
        'SELECT sp.registration_number, sp.gender, ce.class_id
         FROM student_profiles sp INNER JOIN class_enrollments ce ON ce.student_id = sp.student_id AND ce.status = "active"
         WHERE sp.student_id = :student_id'
    );
    $createdProfile->execute(['student_id' => $ids['created_student']]);
    $createdProfile = $createdProfile->fetch();
    if (!$ids['created_student'] || !$createdProfile || $createdProfile['registration_number'] !== 'REG-' . $suffix
        || $createdProfile['gender'] !== 'male' || (int) $createdProfile['class_id'] !== $ids['class']) {
        throw new RuntimeException('The class student creation flow did not persist the profile and enrollment.');
    }
    $lookup = $pdo->prepare('SELECT id FROM crm_contracts WHERE student_id = :student_id AND deleted_at IS NULL');
    $lookup->execute(['student_id' => $ids['created_student']]);
    $ids['created_contract'] = (int) $lookup->fetchColumn();
    if (!$ids['created_contract']) {
        throw new RuntimeException('The class student creation flow did not create the training contract.');
    }

    $classBrowser = stageFourHttpCall('GET', $baseUrl . '/education/classes?folder_id=' . $ids['class_folder'], $managerCookies);
    stageFourExpect($classBrowser, 200, 'class-folder-browser');
    foreach ([$classFolderName, 'HTTP4-' . $suffix, 'Курсант HTTP4', $checkpointCode, $balanceCode] as $needle) {
        if (!str_contains($classBrowser['body'], $needle)) {
            throw new RuntimeException('Class folder browser is missing: ' . $needle);
        }
    }
    $classRoster = stageFourHttpCall('GET', $baseUrl . '/education/classes?folder_id=' . $ids['class_folder'] . '&class_id=' . $ids['class'], $managerCookies);
    stageFourExpect($classRoster, 200, 'class-student-browser');
    foreach (['Папки и классы', 'Добавить курсанта', 'Новый Курсант HTTP4'] as $needle) {
        if (!str_contains($classRoster['body'], $needle)) {
            throw new RuntimeException('Class student browser is missing: ' . $needle);
        }
    }

    $teacherToken = stageFourLogin($baseUrl, $teacherCookies, 'stage4.teacher.' . $suffix, $password);
    stageFourExpect(stageFourHttpCall('POST', $baseUrl . '/education/students/' . $ids['student'] . '/checkpoints/' . $ids['checkpoint'], $teacherCookies, [
        '_token' => $teacherToken, 'completed' => '1', 'class_id' => $ids['class'],
    ]), 302, 'teacher-checkpoint-toggle');
    $state = $pdo->prepare('SELECT is_completed FROM student_checkpoint_states WHERE student_id = :student_id AND checkpoint_id = :checkpoint_id');
    $state->execute(['student_id' => $ids['student'], 'checkpoint_id' => $ids['checkpoint']]);
    if ((int) $state->fetchColumn() !== 1) {
        throw new RuntimeException('Teacher checkpoint toggle was not persisted.');
    }
    stageFourExpect(stageFourHttpCall('GET', $baseUrl . '/education/checkpoints', $teacherCookies), 403, 'teacher-checkpoint-catalog-denied');

    stageFourLogin($baseUrl, $studentCookies, 'stage4.student.' . $suffix, $password);
    $studentPage = stageFourHttpCall('GET', $baseUrl . '/education/students/' . $ids['student'], $studentCookies);
    stageFourExpect($studentPage, 200, 'student-own-progress');
    foreach (['Прогресс обучения', $checkpointCode, 'Пройдено'] as $needle) {
        if (!str_contains($studentPage['body'], $needle)) {
            throw new RuntimeException('Student progress page is missing: ' . $needle);
        }
    }

    echo "PASS stage-four-http\n";
} finally {
    if ($ids['manager']) {
        $userIds = array_filter([$ids['manager'], $ids['teacher'], $ids['student'], $ids['created_student']]);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE actor_user_id IN ({$placeholders}) OR id IN (SELECT activity_log_id FROM activity_log_targets WHERE impacted_user_id IN ({$placeholders}))");
        $stmt->execute(array_merge($userIds, $userIds));
    }
    if ($ids['class']) {
        $pdo->prepare('DELETE FROM class_enrollments WHERE class_id = :id')->execute(['id' => $ids['class']]);
        $pdo->prepare('DELETE FROM student_classes WHERE id = :id')->execute(['id' => $ids['class']]);
    }
    if ($ids['created_contract']) $pdo->prepare('DELETE FROM crm_contracts WHERE id = :id')->execute(['id' => $ids['created_contract']]);
    if ($ids['checkpoint']) $pdo->prepare('DELETE FROM learning_checkpoints WHERE id = :id')->execute(['id' => $ids['checkpoint']]);
    if ($ids['balance_type']) $pdo->prepare('DELETE FROM student_balance_types WHERE id = :id')->execute(['id' => $ids['balance_type']]);
    if ($ids['program']) $pdo->prepare('DELETE FROM training_programs WHERE id = :id')->execute(['id' => $ids['program']]);
    if ($ids['class_folder']) $pdo->prepare('DELETE FROM class_folders WHERE id = :id')->execute(['id' => $ids['class_folder']]);
    if ($ids['program_folder']) $pdo->prepare('DELETE FROM training_program_folders WHERE id = :id')->execute(['id' => $ids['program_folder']]);
    foreach (['created_student', 'student', 'teacher', 'manager'] as $key) {
        if ($ids[$key]) $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $ids[$key]]);
    }
    @unlink($managerCookies);
    @unlink($teacherCookies);
    @unlink($studentCookies);
}
