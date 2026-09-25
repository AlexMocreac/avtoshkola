<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\DeadlineEvent;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Models\Warning;

function stageTwoCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$actor = User::staff()[0] ?? null;
if (!$actor) {
    throw new RuntimeException('Stage-two test requires one active employee.');
}

$pdo->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $leadId = Lead::create([
        'first_name' => 'Тестовый',
        'last_name' => 'Лид',
        'phone' => '+7 900 111-22-33',
        'email' => 'lead.' . $suffix . '@example.test',
        'source' => 'Автотест',
        'direction' => 'gostekhnadzor',
        'status' => 'target',
        'next_contact_at' => date('Y-m-d\TH:i', strtotime('+2 days')),
        'assigned_to' => $actor->id,
    ], $actor->id);
    stageTwoCheck(Lead::find($leadId) !== null, 'Lead was not created.');

    $contractId = Contract::create([
        'contract_number' => '',
        'registration_number' => 'REG-' . $suffix,
        'lead_id' => $leadId,
        'counterparty' => 'Тестовый Лид',
        'client_phone' => '+7 900 111-22-33',
        'subject' => 'Проверка договора',
        'direction' => 'gostekhnadzor',
        'signed_on' => date('Y-m-d'),
        'starts_on' => date('Y-m-d'),
        'ends_on' => date('Y-m-d', strtotime('+5 days')),
        'amount' => '25000',
        'status' => 'active',
    ], $actor->id);
    $contract = Contract::find($contractId);
    stageTwoCheck($contract !== null, 'Contract was not created.');
    stageTwoCheck(str_starts_with((string) $contract['contract_number'], 'ДГ-' . date('Y') . '-'), 'Automatic contract number was not generated.');
    stageTwoCheck(count(Contract::all(['search' => '+7 900 111-22-33', 'date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')])) === 1, 'Contract journal phone/date filtering failed.');

    $taskIds = Task::createSeries([
        'description' => 'Проверить конфиденциальную задачу',
        'task_date' => date('Y-m-d'),
        'due_at' => date('Y-m-d\TH:i', strtotime('+1 day')),
        'confidential' => '1',
        'recurrence' => '',
    ], $actor->id, [$actor->id]);
    stageTwoCheck(count($taskIds) === 1, 'Single task created an invalid number of records.');
    stageTwoCheck(Task::findVisible($taskIds[0], $actor) !== null, 'Assignee cannot see confidential task.');
    stageTwoCheck(in_array($taskIds[0], array_map(static fn (array $task): int => (int) $task['id'], Task::all($actor)), true), 'Task list omitted visible confidential task.');
    ActivityLog::record('test.private_task', 'Проверка конфиденциальной задачи', $actor, [$actor], 'task', $taskIds[0]);
    $otherViewer = array_values(array_filter(User::staff(), static fn (User $staffUser): bool => $staffUser->id !== $actor->id))[0] ?? null;
    if ($otherViewer) {
        stageTwoCheck(ActivityLog::all(['action' => 'test.private_task'], 10, $otherViewer->id) === [], 'Confidential task leaked through the event feed.');
    }

    $deadlineId = DeadlineEvent::create([
        'title' => 'Проверочный срок',
        'category' => 'Тест',
        'description' => 'Проверка предупреждений',
        'due_at' => date('Y-m-d\TH:i', strtotime('+3 days')),
        'impacted_user_id' => $actor->id,
    ], $actor->id);

    $warnings = Warning::all($actor);
    $sources = array_column($warnings, 'source');
    stageTwoCheck(in_array('lead', $sources, true), 'Lead follow-up warning is missing.');
    stageTwoCheck(in_array('contract', $sources, true), 'Contract warning is missing.');
    stageTwoCheck(in_array('task', $sources, true), 'Task warning is missing.');
    stageTwoCheck(in_array('event', $sources, true), 'Manual deadline warning is missing.');

    ActivityLog::record('test.stage_two', 'Проверка второго этапа', $actor, [$actor], 'deadline', $deadlineId, [
        'title' => 'Проверочный срок',
        'changes' => ActivityLog::changes(
            ['status' => 'В работе'],
            ['status' => 'Выполнено'],
            ['status' => 'Статус']
        ),
    ]);
    $events = ActivityLog::all(['action' => 'test.stage_two'], 10);
    stageTwoCheck(count($events) === 1 && str_contains((string) $events[0]['impacted_names'], $actor->lastName), 'Audit target was not stored.');
    stageTwoCheck($events[0]['entity_name'] === 'Событие «Проверочный срок»', 'Audit entity title was not presented.');
    stageTwoCheck(str_contains((string) $events[0]['actor_url'], '#user-' . $actor->id), 'Audit author link was not generated.');
    stageTwoCheck(($events[0]['changes'][0] ?? null) === ['field' => 'Статус', 'from' => 'В работе', 'to' => 'Выполнено'], 'Audit field changes were not presented.');
    stageTwoCheck(Task::complete($taskIds[0], $actor), 'Assignee could not complete a task.');

    echo "PASS stage-two-crm-tasks-warnings-audit\n";
} finally {
    $pdo->rollBack();
}
