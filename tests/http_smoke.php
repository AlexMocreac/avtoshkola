<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$baseUrl = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8080'), '/');
$adminPassword = (string) getenv('ADMIN_PASSWORD');
if ($adminPassword === '') {
    fwrite(STDERR, "Set ADMIN_PASSWORD before running this smoke test.\n");
    exit(1);
}

$cookieFile = tempnam(sys_get_temp_dir(), 'avto-smoke-');
$createdUserId = null;
$createdLogin = null;

function call(string $method, string $url, string $cookieFile, array $data = [], array $headers = []): array
{
    $curl = curl_init($url);
    $responseHeaders = '';
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
            $responseHeaders .= $header;
            return strlen($header);
        },
    ]);
    if ($method !== 'GET') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = curl_exec($curl);
    if ($raw === false) {
        throw new RuntimeException(curl_error($curl));
    }
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    return ['status' => $status, 'headers' => $responseHeaders, 'body' => $raw];
}

function csrf(string $html): string
{
    if (!preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('CSRF token not found.');
    }
    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function jsonResult(array $response, int $expectedStatus): array
{
    if ($response['status'] !== $expectedStatus) {
        throw new RuntimeException("Expected HTTP {$expectedStatus}; got {$response['status']}: {$response['body']}");
    }
    if ($response['body'] === '') {
        throw new RuntimeException("Empty JSON response. Headers: {$response['headers']}");
    }
    $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    if (($payload['ok'] ?? false) !== true) {
        throw new RuntimeException($payload['message'] ?? 'API request failed.');
    }
    return $payload;
}

try {
    $loginPage = call('GET', $baseUrl . '/login', $cookieFile);
    $token = csrf($loginPage['body']);
    $login = call('POST', $baseUrl . '/login', $cookieFile, [
        '_token' => $token,
        'login' => 'admin',
        'password' => $adminPassword,
    ]);
    if ($login['status'] !== 302 || !str_contains($login['headers'], '/dashboard')) {
        throw new RuntimeException('Administrator login failed.');
    }
    echo "PASS login\n";

    $usersPage = call('GET', $baseUrl . '/users', $cookieFile);
    if ($usersPage['status'] !== 302 || !str_contains($usersPage['headers'], '/students')) {
        throw new RuntimeException('Legacy user list did not redirect to students.');
    }
    $studentsPage = call('GET', $baseUrl . '/students', $cookieFile);
    $token = csrf($studentsPage['body']);
    $staffPage = call('GET', $baseUrl . '/staff', $cookieFile);
    if ($staffPage['status'] !== 200 || str_contains($staffPage['body'], 'data-user-create')) {
        throw new RuntimeException('Administrator staff page must be read-only.');
    }
    $suffix = bin2hex(random_bytes(4));
    $createdLogin = 'smoke.student.' . $suffix;
    $headers = ['X-CSRF-TOKEN: ' . $token, 'X-Requested-With: XMLHttpRequest'];

    if (call('POST', $baseUrl . '/users', $cookieFile, ['role' => 'director'], $headers)['status'] !== 403) {
        throw new RuntimeException('Administrator can assign director role.');
    }

    $created = jsonResult(call('POST', $baseUrl . '/users', $cookieFile, [
        'login' => $createdLogin,
        'email' => 'smoke.' . $suffix . '@example.local',
        'phone' => '+7 900 000-11-22',
        'first_name' => 'Иван',
        'last_name' => 'Проверочный',
        'middle_name' => 'Тестович',
        'role' => 'student',
        'access_months' => '6',
        'password' => 'Student123!',
    ], $headers), 201);
    $createdUserId = (int) $created['user']['id'];
    if (!$created['user']['access_expires_at']) {
        throw new RuntimeException('Student access expiry was not set.');
    }
    echo "PASS create-user\n";

    jsonResult(call('POST', $baseUrl . "/users/{$createdUserId}/update", $cookieFile, [
        'login' => $createdLogin,
        'email' => 'smoke.' . $suffix . '@example.local',
        'phone' => '+7 900 000-11-23',
        'first_name' => 'Иван',
        'last_name' => 'Проверочный',
        'middle_name' => 'Обновлённый',
        'role' => 'student',
        'access_months' => '6',
    ], $headers), 200);
    echo "PASS update-user\n";

    jsonResult(call('POST', $baseUrl . "/users/{$createdUserId}/status", $cookieFile, ['status' => 'blocked'], $headers), 200);
    jsonResult(call('POST', $baseUrl . "/users/{$createdUserId}/status", $cookieFile, ['status' => 'active'], $headers), 200);
    echo "PASS block-unblock\n";

    $reset = jsonResult(call('POST', $baseUrl . "/users/{$createdUserId}/reset-password", $cookieFile, [], $headers), 200);
    if (strlen((string) $reset['password']) < 10) {
        throw new RuntimeException('Generated password is too short.');
    }
    echo "PASS reset-password\n";

    $conversation = jsonResult(call('POST', $baseUrl . '/chat/conversations', $cookieFile, ['contact_id' => $createdUserId], $headers), 200);
    $conversationId = (int) $conversation['conversation_id'];
    jsonResult(call('POST', $baseUrl . '/chat/messages', $cookieFile, [
        'conversation_id' => $conversationId,
        'body' => 'Проверка сообщений первого этапа',
    ], $headers), 201);
    $listed = jsonResult(call('GET', $baseUrl . "/chat/messages?conversation_id={$conversationId}&after_id=0", $cookieFile), 200);
    if (count($listed['messages']) !== 1 || $listed['messages'][0]['body'] !== 'Проверка сообщений первого этапа') {
        throw new RuntimeException('Chat message round trip failed.');
    }
    echo "PASS chat-round-trip\n";

    jsonResult(call('POST', $baseUrl . "/users/{$createdUserId}/delete", $cookieFile, [], $headers), 200);
    echo "PASS soft-delete\n";
    echo "SMOKE TEST COMPLETE\n";
} finally {
    if (!$createdUserId && $createdLogin) {
        $lookup = Database::connection()->prepare('SELECT id FROM users WHERE login = :login LIMIT 1');
        $lookup->execute(['login' => $createdLogin]);
        $createdUserId = (int) ($lookup->fetchColumn() ?: 0);
    }
    if ($createdUserId) {
        $pdo = Database::connection();
        $conversationIds = $pdo->prepare('SELECT id FROM conversations WHERE student_id = :id OR employee_id = :other_id');
        $conversationIds->execute(['id' => $createdUserId, 'other_id' => $createdUserId]);
        $ids = array_map('intval', $conversationIds->fetchAll(PDO::FETCH_COLUMN));
        foreach ($ids as $conversationId) {
            $stmt = $pdo->prepare('DELETE FROM messages WHERE conversation_id = :id');
            $stmt->execute(['id' => $conversationId]);
        }
        $stmt = $pdo->prepare('DELETE FROM conversations WHERE student_id = :student_id OR employee_id = :employee_id');
        $stmt->execute(['student_id' => $createdUserId, 'employee_id' => $createdUserId]);
        $stmt = $pdo->prepare('DELETE FROM password_reset_events WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $createdUserId]);
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $createdUserId]);
    }
    @unlink($cookieFile);
}
