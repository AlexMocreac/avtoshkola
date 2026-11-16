<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\User;

$baseUrl = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8094'), '/');
$managerCookies = tempnam(sys_get_temp_dir(), 'avto-stage-three-manager-');
$teacherCookies = tempnam(sys_get_temp_dir(), 'avto-stage-three-teacher-');
$studentCookies = tempnam(sys_get_temp_dir(), 'avto-stage-three-student-');
$pdo = Database::connection();
$suffix = bin2hex(random_bytes(5));
$password = 'StageThreeHttp123!';
$ids = ['manager' => 0, 'teacher' => 0, 'student' => 0, 'class' => 0, 'program' => 0, 'service' => 0, 'kassa' => 0, 'sale' => 0, 'payment' => 0, 'contract' => 0];

function stageThreeHttpCall(string $method, string $url, string $cookieFile, array $data = []): array
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

function stageThreeToken(string $html): string
{
    if (!preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('CSRF token not found.');
    }
    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function stageThreeExpect(array $response, int $status, string $label): void
{
    if ($response['status'] !== $status) {
        throw new RuntimeException("{$label}: expected {$status}, got {$response['status']}: {$response['body']}");
    }
}

function stageThreeLogin(string $baseUrl, string $cookieFile, string $login, string $password): string
{
    $page = stageThreeHttpCall('GET', $baseUrl . '/login', $cookieFile);
    stageThreeExpect($page, 200, 'login-page');
    $token = stageThreeToken($page['body']);
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/login', $cookieFile, [
        '_token' => $token, 'login' => $login, 'password' => $password,
    ]), 302, 'login');
    return $token;
}

try {
    $insert = $pdo->prepare(
        'INSERT INTO users (login, email, phone, first_name, last_name, role, status, password_hash, must_change_password)
         VALUES (:login, :email, :phone, :first_name, :last_name, :role, "active", :password_hash, 0)'
    );
    foreach ([
        'manager' => [User::ROLE_DIRECTOR, 'HTTP', 'Директор'],
        'teacher' => [User::ROLE_TEACHER, 'HTTP', 'Преподаватель'],
        'student' => [User::ROLE_STUDENT, 'HTTP', 'Курсант'],
    ] as $key => [$role, $firstName, $lastName]) {
        $login = 'stage3.' . $key . '.' . $suffix;
        $insert->execute([
            'login' => $login,
            'email' => $login . '@example.test',
            'phone' => $key === 'student' ? '+7 900 444-55-66' : null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $ids[$key] = (int) $pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO student_profiles (student_id) VALUES (:student_id)')->execute(['student_id' => $ids['student']]);

    $managerLogin = 'stage3.manager.' . $suffix;
    $token = stageThreeLogin($baseUrl, $managerCookies, $managerLogin, $password);
    foreach (['/education/classes', '/education/students', '/education/services', '/education/kassas', '/education/holidays', '/calendar'] as $path) {
        $page = stageThreeHttpCall('GET', $baseUrl . $path, $managerCookies);
        stageThreeExpect($page, 200, $path);
        if (!str_contains($page['body'], '<main class="main-content">')) {
            throw new RuntimeException("{$path}: application layout missing.");
        }
        if ($path === '/education/classes') {
            if (substr_count($page['body'], 'data-date-picker') < 4 || substr_count($page['body'], 'data-time-picker') < 7) {
                throw new RuntimeException('Class form is missing branded date or time controls.');
            }
            foreach (['schedule-day__toggle', 'stage-form__notes', 'name="starts_on"', 'name="schedule[1]"'] as $needle) {
                if (!str_contains($page['body'], $needle)) {
                    throw new RuntimeException('Class form is missing enhanced control: ' . $needle);
                }
            }
        } elseif ($path === '/education/services' && (!str_contains($page['body'], 'id="service-dialog"') || !str_contains($page['body'], 'id="allocation-rows"'))) {
            throw new RuntimeException('Service editor or balance allocation grid is missing.');
        }
    }

    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/kassas', $managerCookies, [
        '_token' => $token, 'name' => 'HTTP касса ' . $suffix, 'description' => 'E2E', 'status' => 'active',
    ]), 302, 'kassa-create');
    $lookup = $pdo->prepare('SELECT id FROM kassas WHERE name = :name');
    $lookup->execute(['name' => 'HTTP касса ' . $suffix]);
    $ids['kassa'] = (int) $lookup->fetchColumn();

    $balanceTypeId = (int) $pdo->query('SELECT id FROM student_balance_types ORDER BY id LIMIT 1')->fetchColumn();
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/services', $managerCookies, [
        '_token' => $token, 'name' => 'HTTP курс B ' . $suffix, 'price' => '48 000,00', 'status' => 'active', 'allocations' => [['amount' => '18000', 'balances' => [$balanceTypeId => '6']]],
    ]), 302, 'service-create');
    $lookup = $pdo->prepare('SELECT id FROM education_services WHERE name = :name');
    $lookup->execute(['name' => 'HTTP курс B ' . $suffix]);
    $ids['service'] = (int) $lookup->fetchColumn();

    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/classes', $managerCookies, [
        '_token' => $token,
        'name' => 'HTTP категория B',
        'group_number' => 'HTTP-' . $suffix,
        'classroom' => 'Кабинет HTTP',
        'study_program' => 'Подготовка категории B',
        'teacher_id' => $ids['teacher'],
        'starts_on' => date('Y-m-d', strtotime('+1 day')),
        'planned_lesson_count' => '6',
        'lesson_duration_minutes' => '90',
        'status' => 'active',
        'schedule' => [1 => '09:00', 3 => '09:00', 5 => '09:00'],
    ]), 302, 'class-create');
    $lookup = $pdo->prepare('SELECT id FROM student_classes WHERE group_number = :group_number');
    $lookup->execute(['group_number' => 'HTTP-' . $suffix]);
    $ids['class'] = (int) $lookup->fetchColumn();
    if ($ids['class']) {
        $ids['program'] = (int) $pdo->query('SELECT program_id FROM student_classes WHERE id = ' . $ids['class'])->fetchColumn();
    }
    if (!$ids['class']) {
        throw new RuntimeException('Class was not created through HTTP.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM class_lessons WHERE class_id = ' . $ids['class'])->fetchColumn() !== 6) {
        throw new RuntimeException('HTTP class creation did not generate all lessons.');
    }

    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/classes/' . $ids['class'] . '/students', $managerCookies, [
        '_token' => $token, 'student_id' => $ids['student'], 'joined_on' => date('Y-m-d'),
    ]), 302, 'student-enroll');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/students/' . $ids['student'] . '/contracts', $managerCookies, [
        '_token' => $token, 'contract_number' => '', 'registration_number' => 'HTTP-REG-' . $suffix,
        'signed_on' => date('Y-m-d'), 'starts_on' => date('Y-m-d'), 'ends_on' => date('Y-m-d', strtotime('+6 months')),
        'amount' => '48000', 'subject' => 'Обучение в автошколе', 'counterparty' => 'HTTP Курсант', 'direction' => 'driving_school', 'status' => 'active',
    ]), 302, 'contract-create');
    $ids['contract'] = (int) $pdo->query('SELECT id FROM crm_contracts WHERE student_id = ' . $ids['student'] . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/students/' . $ids['student'] . '/sales', $managerCookies, [
        '_token' => $token, 'contract_id' => $ids['contract'], 'service_id' => $ids['service'], 'amount' => '48000', 'sold_on' => date('Y-m-d'),
    ]), 302, 'sale-create');
    $ids['sale'] = (int) $pdo->query('SELECT id FROM student_sales WHERE student_id = ' . $ids['student'] . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/students/' . $ids['student'] . '/payments', $managerCookies, [
        '_token' => $token, 'sale_id' => $ids['sale'], 'kassa_id' => $ids['kassa'], 'amount' => '18000', 'paid_on' => date('Y-m-d\TH:i'),
    ]), 302, 'payment-create');
    $ids['payment'] = (int) $pdo->query('SELECT id FROM student_payments WHERE student_id = ' . $ids['student'] . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/students/' . $ids['student'] . '/contracts/' . $ids['contract'] . '/balances/' . $balanceTypeId . '/progress', $managerCookies, ['_token' => $token, 'amount' => '2.5']), 302, 'contract-progress-update');
    $progress = \App\Models\EducationFinance::contractBalanceProgress($ids['student']);
    $balance = $progress[0]['balances'][$balanceTypeId] ?? null;
    if (!$balance || $balance['assigned'] !== 6.0 || $balance['paid'] !== 6.0 || $balance['manual'] !== 2.5) throw new RuntimeException('Contract progress did not reflect sale, payment and manual usage.');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/classes/' . $ids['class'] . '/balance-type', $managerCookies, ['_token' => $token, 'balance_type_id' => $balanceTypeId]), 302, 'class-balance-type');
    $pdo->prepare('UPDATE class_enrollments SET joined_on = ? WHERE class_id = ? AND student_id = ?')->execute([date('Y-m-d', strtotime('-1 day')), $ids['class'], $ids['student']]);
    $pdo->prepare('INSERT INTO class_lessons (class_id, teacher_id, title, starts_at, ends_at) VALUES (?, ?, ?, ?, ?)')->execute([$ids['class'], $ids['teacher'], 'Проведённое занятие', date('Y-m-d H:i:s', time() - 7200), date('Y-m-d H:i:s', time() - 1800)]);
    $pastLesson = (int) $pdo->lastInsertId();
    $teacherToken = stageThreeLogin($baseUrl, $teacherCookies, 'stage3.teacher.' . $suffix, $password);
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/classes/' . $ids['class'] . '/lessons/' . $pastLesson . '/attendance', $teacherCookies, ['_token' => $teacherToken, 'attendance' => [$ids['student'] => $ids['contract']]]), 302, 'lesson-attendance');
    $progress = \App\Models\EducationFinance::contractBalanceProgress($ids['student']);
    if (($progress[0]['balances'][$balanceTypeId]['automatic'] ?? null) !== 1.5) throw new RuntimeException('Lesson attendance did not update contract progress.');
    $studentToken = stageThreeLogin($baseUrl, $studentCookies, 'stage3.student.' . $suffix, $password);
    $studentHome = stageThreeHttpCall('GET', $baseUrl . '/dashboard', $studentCookies);
    stageThreeExpect($studentHome, 200, 'student-home');
    if (!str_contains($studentHome['body'], 'Занятия по договорам') || !str_contains($studentHome['body'], '>4 ч</td>')) throw new RuntimeException('Student progress is missing from the home page.');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/students/' . $ids['student'] . '/contracts/' . $ids['contract'] . '/balances/' . $balanceTypeId . '/progress', $studentCookies, ['_token' => $studentToken, 'amount' => '3']), 403, 'student-progress-write-denied');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/education/classes/' . $ids['class'] . '/lessons/' . $pastLesson . '/attendance', $studentCookies, ['_token' => $studentToken, 'attendance' => [$ids['student'] => $ids['contract']]]), 403, 'student-attendance-write-denied');

    $roster = stageThreeHttpCall('GET', $baseUrl . '/education/classes?class_id=' . $ids['class'], $managerCookies);
    stageThreeExpect($roster, 200, 'access-roster');
    foreach (['<th>Доступ</th>', 'data-student-id="' . $ids['student'] . '"', 'data-access-active="1"', 'id="studentAccessModal"'] as $needle) {
        if (!str_contains($roster['body'], $needle)) throw new RuntimeException('Access roster missing: ' . $needle);
    }
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/users/' . $ids['student'] . '/grant-access', $teacherCookies, ['_token' => $teacherToken, 'renew' => '1', 'access_months' => '6']), 403, 'teacher-access-write-denied');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/users/' . $ids['teacher'] . '/grant-access', $managerCookies, ['_token' => $token, 'renew' => '1', 'access_months' => '6']), 404, 'staff-access-write-denied');
    $pdo->prepare('UPDATE users SET status = "blocked", access_months = 6, access_expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?')->execute([$ids['student']]);
    $roster = stageThreeHttpCall('GET', $baseUrl . '/education/classes?class_id=' . $ids['class'], $managerCookies);
    if (!str_contains($roster['body'], 'data-access-active="0"') || !str_contains($roster['body'], 'data-access-expired="1"')) throw new RuntimeException('Expired access is not shown as inactive.');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/users/' . $ids['student'] . '/grant-access', $managerCookies, ['_token' => $token, 'renew' => '0']), 422, 'expired-access-requires-renewal');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/users/' . $ids['student'] . '/grant-access', $managerCookies, ['_token' => $token, 'renew' => '1', 'access_months' => '121']), 422, 'invalid-access-term');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/users/' . $ids['student'] . '/grant-access', $managerCookies, ['_token' => $token, 'renew' => '1', 'access_months' => '3']), 200, 'renew-access');
    $renewed = $pdo->query('SELECT status, access_months, access_expires_at > NOW() AS valid FROM users WHERE id = ' . $ids['student'])->fetch();
    if ($renewed['status'] !== 'active' || (int) $renewed['access_months'] !== 3 || !(bool) $renewed['valid']) throw new RuntimeException('Access renewal was not saved.');
    $expiry = $pdo->query('SELECT access_expires_at FROM users WHERE id = ' . $ids['student'])->fetchColumn();
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/users/' . $ids['student'] . '/status', $managerCookies, ['_token' => $token, 'status' => 'blocked']), 200, 'block-access');
    stageThreeExpect(stageThreeHttpCall('POST', $baseUrl . '/users/' . $ids['student'] . '/grant-access', $managerCookies, ['_token' => $token, 'renew' => '0']), 200, 'restore-access');
    if ($pdo->query('SELECT access_expires_at FROM users WHERE id = ' . $ids['student'])->fetchColumn() !== $expiry) throw new RuntimeException('Restoring access changed the existing term.');



    $classPage = stageThreeHttpCall('GET', $baseUrl . '/education/classes/' . $ids['class'], $managerCookies);
    stageThreeExpect($classPage, 200, 'class-page');
    if (!str_contains($classPage['body'], 'data-attendance-lesson="' . $pastLesson . '"') || !str_contains($classPage['body'], 'id="classAttendanceDialog"')) throw new RuntimeException('Attendance controls missing from completed lesson.');
    if (!str_contains($classPage['body'], 'class="panel reveal class-schedule-panel" id="schedule"')) {
        throw new RuntimeException('Class page is missing the spaced schedule panel.');
    }
    $studentPage = stageThreeHttpCall('GET', $baseUrl . '/education/students/' . $ids['student'], $managerCookies);
    stageThreeExpect($studentPage, 200, 'student-page');
    if (substr_count($studentPage['body'], 'data-student-tab="') !== 7
        || substr_count($studentPage['body'], 'data-date-picker') < 10
        || substr_count($studentPage['body'], 'data-file-input') < 2
        || str_contains($studentPage['body'], 'type="date"')
        || str_contains($studentPage['body'], 'type="datetime-local"')) {
        throw new RuntimeException('Student card tabs, date controls, or file uploads are not fully enhanced.');
    }
    foreach (['data-student-tab-panel="events"', 'data-student-tab-panel="main personal"', 'student-file-upload', 'data-student-active-tab', 'data-dialog-open="studentStatusConfirmModal"', 'id="studentStatusConfirmModal"', 'form="studentStatusActionForm"'] as $needle) {
        if (!str_contains($studentPage['body'], $needle)) {
            throw new RuntimeException('Student card is missing enhanced UI marker: ' . $needle);
        }
    }
    foreach (['HTTP-' . $suffix, 'HTTP курс B ' . $suffix, '18 000,00', 'HTTP касса ' . $suffix] as $needle) {
        if (!str_contains($studentPage['body'], $needle)) {
            throw new RuntimeException('Student card is missing: ' . $needle);
        }
    }
    $calendar = stageThreeHttpCall('GET', $baseUrl . '/calendar?month=' . date('Y-m', strtotime('+1 day')), $managerCookies);
    if (!str_contains($calendar['body'], 'HTTP-' . $suffix)) {
        throw new RuntimeException('Generated class is missing from the shared calendar.');
    }

    stageThreeExpect(stageThreeHttpCall('GET', $baseUrl . '/education/classes/' . $ids['class'], $teacherCookies), 200, 'teacher-own-class');
    stageThreeExpect(stageThreeHttpCall('GET', $baseUrl . '/education/kassas', $teacherCookies), 403, 'teacher-kassas-denied');

    echo "PASS stage-three-http\n";
} finally {
    if ($ids['manager']) {
        $userIds = array_filter([$ids['manager'], $ids['teacher'], $ids['student']]);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE actor_user_id IN ({$placeholders}) OR id IN (SELECT activity_log_id FROM activity_log_targets WHERE impacted_user_id IN ({$placeholders}))");
        $stmt->execute(array_merge($userIds, $userIds));
    }
    if ($ids['class']) $pdo->prepare('DELETE a FROM class_lesson_attendance a JOIN class_lessons l ON l.id = a.lesson_id WHERE l.class_id = :id')->execute(['id' => $ids['class']]);
    if ($ids['payment']) $pdo->prepare('DELETE FROM student_payments WHERE id = :id')->execute(['id' => $ids['payment']]);
    if ($ids['sale']) $pdo->prepare('DELETE FROM student_sales WHERE id = :id')->execute(['id' => $ids['sale']]);
    if ($ids['contract']) $pdo->prepare('DELETE FROM contract_balance_manual_usage WHERE contract_id = :id')->execute(['id' => $ids['contract']]);
    if ($ids['contract']) $pdo->prepare('DELETE FROM crm_contracts WHERE id = :id')->execute(['id' => $ids['contract']]);
    if ($ids['class']) {
        $pdo->prepare('DELETE FROM class_enrollments WHERE class_id = :id')->execute(['id' => $ids['class']]);
        $pdo->prepare('DELETE FROM student_classes WHERE id = :id')->execute(['id' => $ids['class']]);
    }
    if ($ids['program']) $pdo->prepare('DELETE FROM training_programs WHERE id = :id AND created_by = :created_by')->execute(['id' => $ids['program'], 'created_by' => $ids['manager']]);
    if ($ids['service']) $pdo->prepare('DELETE FROM education_services WHERE id = :id')->execute(['id' => $ids['service']]);
    if ($ids['kassa']) $pdo->prepare('DELETE FROM kassas WHERE id = :id')->execute(['id' => $ids['kassa']]);
    foreach (['student', 'teacher', 'manager'] as $key) {
        if ($ids[$key]) $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $ids[$key]]);
    }
    @unlink($managerCookies);
    @unlink($teacherCookies);
    @unlink($studentCookies);
}
