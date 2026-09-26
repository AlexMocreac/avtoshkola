<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\User;

$baseUrl = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8094'), '/');
$cookieFile = tempnam(sys_get_temp_dir(), 'avto-stage-two-http-');
$pdo = Database::connection();
$suffix = bin2hex(random_bytes(5));
$login = 'stage2.http.' . $suffix;
$password = 'StageTwo12345!';
$userId = 0;
$leadId = 0;
$contractId = 0;
$contractNumber = '';
$taskIds = [];
$deadlineId = 0;
$uploadedPaths = [];
$uploadTempFiles = [];

function httpCall(string $method, string $url, string $cookieFile, array $data = [], array $headers = []): array
{
    $curl = curl_init($url);
    $responseHeaders = '';
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
            $responseHeaders .= $header;
            return strlen($header);
        },
    ]);
    if ($method !== 'GET') {
        $multipart = false;
        array_walk_recursive($data, static function (mixed $value) use (&$multipart): void {
            if ($value instanceof CURLFile) {
                $multipart = true;
            }
        });
        curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
    }
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException(curl_error($curl));
    }
    return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => $responseHeaders, 'body' => $body];
}

function csrfFrom(string $html): string
{
    if (!preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('CSRF token not found.');
    }
    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function expectStatus(array $response, int $status, string $label): void
{
    if ($response['status'] !== $status) {
        throw new RuntimeException("{$label}: expected {$status}, got {$response['status']}: {$response['body']}");
    }
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (login, email, first_name, last_name, role, status, password_hash, must_change_password)
         VALUES (:login, :email, "Эндтуэнд", "Проверка", :role, "active", :password_hash, 0)'
    );
    $stmt->execute([
        'login' => $login,
        'email' => $login . '@example.test',
        'role' => User::ROLE_DIRECTOR,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $loginPage = httpCall('GET', $baseUrl . '/login', $cookieFile);
    expectStatus($loginPage, 200, 'login-page');
    $token = csrfFrom($loginPage['body']);
    $loginResponse = httpCall('POST', $baseUrl . '/login', $cookieFile, ['_token' => $token, 'login' => $login, 'password' => $password]);
    expectStatus($loginResponse, 302, 'login');

    foreach (['/dashboard', '/events', '/warnings', '/crm/leads', '/crm/contracts', '/tasks'] as $path) {
        $page = httpCall('GET', $baseUrl . $path, $cookieFile);
        expectStatus($page, 200, $path);
        if (!str_contains($page['body'], '<main class="main-content">')) {
            throw new RuntimeException("{$path}: application layout missing.");
        }
    }

    $tasksBeforeCreate = httpCall('GET', $baseUrl . '/tasks', $cookieFile);
    if (!str_contains($tasksBeforeCreate['body'], '/api/staff/search') || str_contains($tasksBeforeCreate['body'], 'name="assignee_ids[]"')) {
        throw new RuntimeException('Task form must use API search and must not embed the staff list.');
    }
    $shortSearch = httpCall('GET', $baseUrl . '/api/staff/search?q=%D0%AD', $cookieFile);
    expectStatus($shortSearch, 200, 'staff-search-short');
    if ((json_decode($shortSearch['body'], true, 512, JSON_THROW_ON_ERROR)['items'] ?? null) !== []) {
        throw new RuntimeException('Staff API returned users for a query shorter than two characters.');
    }
    $wildcardSearch = httpCall('GET', $baseUrl . '/api/staff/search?q=%25%25', $cookieFile);
    if ((json_decode($wildcardSearch['body'], true, 512, JSON_THROW_ON_ERROR)['items'] ?? null) !== []) {
        throw new RuntimeException('Staff API interpreted search characters as a request for the full directory.');
    }
    $staffSearch = httpCall('GET', $baseUrl . '/api/staff/search?q=%D0%AD%D0%BD%D0%B4', $cookieFile);
    expectStatus($staffSearch, 200, 'staff-search');
    $staffPayload = json_decode($staffSearch['body'], true, 512, JSON_THROW_ON_ERROR);
    if (($staffPayload['items'][0]['id'] ?? 0) !== $userId || isset($staffPayload['items'][0]['login'], $staffPayload['items'][0]['email'])) {
        throw new RuntimeException('Staff API response is missing the match or exposes private fields.');
    }

    $leadsPage = httpCall('GET', $baseUrl . '/crm/leads', $cookieFile);
    $token = csrfFrom($leadsPage['body']);
    expectStatus(httpCall('POST', $baseUrl . '/crm/leads', $cookieFile, [
        '_token' => $token,
        'first_name' => 'HTTP',
        'last_name' => 'Лид',
        'phone' => '+7 900 555-44-33',
        'email' => 'http.lead.' . $suffix . '@example.test',
        'source' => 'E2E',
        'direction' => 'dpo',
        'status' => 'target',
        'assigned_to' => $userId,
        'next_contact_at' => date('Y-m-d\TH:i', strtotime('+2 days')),
    ]), 302, 'lead-create');
    $lookup = $pdo->prepare('SELECT id FROM crm_leads WHERE email = :email');
    $lookup->execute(['email' => 'http.lead.' . $suffix . '@example.test']);
    $leadId = (int) $lookup->fetchColumn();

    $moveUrl = $baseUrl . '/crm/leads/' . $leadId . '/status';
    expectStatus(httpCall('POST', $moveUrl, $cookieFile, ['status' => 'contacted', 'from_status' => 'target']), 419, 'lead-move-csrf');
    expectStatus(httpCall('POST', $moveUrl, $cookieFile, ['_token' => $token, 'status' => 'unknown', 'from_status' => 'target']), 422, 'lead-move-invalid-status');
    expectStatus(httpCall('POST', $moveUrl, $cookieFile, ['_token' => $token, 'status' => ['target'], 'from_status' => 'target']), 422, 'lead-move-invalid-payload');
    $move = httpCall('POST', $moveUrl, $cookieFile, ['_token' => $token, 'status' => 'contacted', 'from_status' => 'target']);
    expectStatus($move, 200, 'lead-move');
    $movedLead = json_decode($move['body'], true, 512, JSON_THROW_ON_ERROR)['lead'];
    if ($movedLead['status'] !== 'contacted' || $movedLead['direction'] !== 'dpo'
        || $movedLead['email'] !== 'http.lead.' . $suffix . '@example.test' || (int) $movedLead['assigned_to'] !== $userId) {
        throw new RuntimeException('Moving a lead failed or changed unrelated lead fields.');
    }
    expectStatus(httpCall('POST', $moveUrl, $cookieFile, ['_token' => $token, 'status' => 'refused', 'from_status' => 'target']), 409, 'lead-move-stale-stage');
    expectStatus(httpCall('POST', $moveUrl, $cookieFile, ['_token' => $token, 'status' => 'contacted', 'from_status' => 'contacted']), 200, 'lead-move-same-stage');
    $moveHistory = httpCall('GET', $baseUrl . '/crm/leads/' . $leadId . '/history', $cookieFile);
    $moveEvents = json_decode($moveHistory['body'], true, 512, JSON_THROW_ON_ERROR)['events'];
    $stageEvents = array_values(array_filter($moveEvents, static fn (array $event): bool => $event['title'] === 'Изменён этап лида'));
    if (count($stageEvents) !== 1 || !str_contains($stageEvents[0]['description'], 'Целевой') || !str_contains($stageEvents[0]['description'], 'Связались')) {
        throw new RuntimeException('Moving a lead must record exactly one history event with the old and new stages.');
    }

    $uploadTempFiles = [tempnam(sys_get_temp_dir(), 'contract-pdf-'), tempnam(sys_get_temp_dir(), 'contract-txt-')];
    file_put_contents($uploadTempFiles[0], "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
    file_put_contents($uploadTempFiles[1], "Приложение к договору {$suffix}\n");
    expectStatus(httpCall('POST', $baseUrl . '/crm/contracts', $cookieFile, [
        '_token' => $token,
        'contract_number' => '',
        'registration_number' => 'HTTP-REG-' . $suffix,
        'lead_id' => $leadId,
        'counterparty' => 'HTTP Лид',
        'client_phone' => '+7 900 555-44-33',
        'subject' => 'E2E договор',
        'direction' => 'dpo',
        'signed_on' => date('Y-m-d'),
        'starts_on' => date('Y-m-d'),
        'ends_on' => date('Y-m-d', strtotime('+7 days')),
        'amount' => '35000',
        'status' => 'active',
        'files[0]' => new CURLFile($uploadTempFiles[0], 'application/pdf', 'договор.pdf'),
        'files[1]' => new CURLFile($uploadTempFiles[1], 'text/plain', 'приложение.txt'),
    ]), 302, 'contract-create');
    $lookup = $pdo->prepare('SELECT id, contract_number FROM crm_contracts WHERE lead_id = :lead_id ORDER BY id DESC LIMIT 1');
    $lookup->execute(['lead_id' => $leadId]);
    $createdContract = $lookup->fetch();
    $contractId = (int) ($createdContract['id'] ?? 0);
    $contractNumber = (string) ($createdContract['contract_number'] ?? '');
    if (!str_starts_with($contractNumber, 'ДГ-' . date('Y') . '-')) {
        throw new RuntimeException('Automatic contract number was not generated.');
    }
    $lookup = $pdo->prepare('SELECT id, stored_path FROM contract_files WHERE contract_id = :contract_id ORDER BY id');
    $lookup->execute(['contract_id' => $contractId]);
    $storedFiles = $lookup->fetchAll();
    if (count($storedFiles) !== 2) {
        throw new RuntimeException('Multiple contract files were not saved.');
    }
    $uploadedPaths = array_column($storedFiles, 'stored_path');
    $download = httpCall('GET', $baseUrl . '/crm/contracts/files/' . $storedFiles[0]['id'], $cookieFile);
    expectStatus($download, 200, 'contract-file-download');
    if (!str_contains($download['headers'], 'Content-Disposition: attachment') || !str_starts_with($download['body'], '%PDF-1.4')) {
        throw new RuntimeException('Contract attachment download is invalid.');
    }
    $journal = httpCall('GET', $baseUrl . '/crm/contracts?search=' . rawurlencode('+7 900 555-44-33') . '&date_from=' . date('Y-m-d') . '&date_to=' . date('Y-m-d'), $cookieFile);
    expectStatus($journal, 200, 'contract-journal-filter');
    if (!str_contains($journal['body'], $contractNumber) || !str_contains($journal['body'], 'HTTP Лид')) {
        throw new RuntimeException('Contract journal phone/date filters did not find the created contract.');
    }
    $history = httpCall('GET', $baseUrl . '/crm/leads/' . $leadId . '/history', $cookieFile);
    expectStatus($history, 200, 'lead-history');
    $historyPayload = json_decode($history['body'], true, 512, JSON_THROW_ON_ERROR);
    if (($historyPayload['lead']['direction'] ?? '') !== 'ДПО' || !in_array('Лид переведён в договор', array_column($historyPayload['events'] ?? [], 'title'), true)) {
        throw new RuntimeException('Lead details or conversion history is incomplete.');
    }

    expectStatus(httpCall('POST', $baseUrl . '/tasks', $cookieFile, [
        '_token' => $token,
        'description' => 'HTTP проверка конфиденциальной задачи ' . $suffix,
        'task_date' => date('Y-m-d'),
        'due_at' => date('Y-m-d\TH:i', strtotime('+1 day')),
        'assignee_ids' => [$userId],
        'confidential' => '1',
    ]), 302, 'task-create');
    $lookup = $pdo->prepare('SELECT id FROM tasks WHERE created_by = :created_by AND description LIKE :description');
    $lookup->execute(['created_by' => $userId, 'description' => '%' . $suffix]);
    $taskIds = array_map('intval', $lookup->fetchAll(PDO::FETCH_COLUMN));

    expectStatus(httpCall('POST', $baseUrl . '/warnings', $cookieFile, [
        '_token' => $token,
        'title' => 'HTTP проверка срока ' . $suffix,
        'category' => 'E2E',
        'due_at' => date('Y-m-d\TH:i', strtotime('+3 days')),
        'impacted_user_id' => $userId,
    ]), 302, 'deadline-create');
    $lookup = $pdo->prepare('SELECT id FROM deadline_events WHERE created_by = :created_by AND title LIKE :title');
    $lookup->execute(['created_by' => $userId, 'title' => '%' . $suffix]);
    $deadlineId = (int) $lookup->fetchColumn();

    $tasksPage = httpCall('GET', $baseUrl . '/tasks?status=open&mine=1', $cookieFile);
    expectStatus($tasksPage, 200, 'tasks-list');
    if (!str_contains($tasksPage['body'], $suffix)) {
        throw new RuntimeException('Created task is missing from the task list.');
    }
    $warningsPage = httpCall('GET', $baseUrl . '/warnings', $cookieFile);
    if (!str_contains($warningsPage['body'], $contractNumber) || !str_contains($warningsPage['body'], 'HTTP проверка срока ' . $suffix)) {
        throw new RuntimeException('Due-date sources are missing from warnings.');
    }
    $eventsPage = httpCall('GET', $baseUrl . '/events', $cookieFile);
    if (!str_contains($eventsPage['body'], 'Создан договор') || !str_contains($eventsPage['body'], 'Создана задача')) {
        throw new RuntimeException('New actions are missing from the event feed.');
    }
    if (str_contains($eventsPage['body'], 'Затронуты:')) {
        throw new RuntimeException('The event feed still renders the obsolete impacted-users line.');
    }
    if (!str_contains($eventsPage['body'], 'HTTP Лид')
        || !str_contains($eventsPage['body'], '/crm/leads?direction=dpo&amp;lead_id=' . $leadId)
        || !str_contains($eventsPage['body'], '/staff#user-' . $userId)
        || !str_contains($eventsPage['body'], 'event-changes')) {
        throw new RuntimeException('The event feed is missing an entity title, direct links or old/new values.');
    }

    expectStatus(httpCall('POST', $baseUrl . '/tasks/' . $taskIds[0] . '/complete', $cookieFile, ['_token' => $token]), 302, 'task-complete');
    $check = $pdo->prepare('SELECT status FROM tasks WHERE id = :id');
    $check->execute(['id' => $taskIds[0]]);
    if ($check->fetchColumn() !== 'completed') {
        throw new RuntimeException('Task was not marked completed.');
    }

    echo "PASS stage-two-http\n";
} finally {
    foreach ($uploadTempFiles as $tempFile) {
        if ($tempFile) {
            @unlink($tempFile);
        }
    }
    if ($deadlineId) {
        $pdo->prepare('DELETE FROM deadline_events WHERE id = :id')->execute(['id' => $deadlineId]);
    }
    foreach ($taskIds as $taskId) {
        $pdo->prepare('DELETE FROM tasks WHERE id = :id')->execute(['id' => $taskId]);
    }
    if ($contractId) {
        $pdo->prepare('DELETE FROM crm_contracts WHERE id = :id')->execute(['id' => $contractId]);
    }
    foreach ($uploadedPaths as $storedPath) {
        $absolutePath = dirname(__DIR__) . '/' . ltrim((string) $storedPath, '/');
        if (str_starts_with($absolutePath, dirname(__DIR__) . '/storage/contracts/')) {
            @unlink($absolutePath);
        }
    }
    if ($leadId) {
        $pdo->prepare('DELETE FROM crm_leads WHERE id = :id')->execute(['id' => $leadId]);
    }
    if ($userId) {
        $stmt = $pdo->prepare('DELETE FROM activity_logs WHERE actor_user_id = :actor OR id IN (SELECT activity_log_id FROM activity_log_targets WHERE impacted_user_id = :target)');
        $stmt->execute(['actor' => $userId, 'target' => $userId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    }
    @unlink($cookieFile);
}
