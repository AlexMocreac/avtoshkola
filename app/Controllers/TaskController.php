<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;

final class TaskController
{
    public function index(): void
    {
        $user = Auth::requireStaff();
        $filters = [
            'status' => (string) ($_GET['status'] ?? 'open'),
            'mine' => (string) ($_GET['mine'] ?? ''),
        ];
        View::render('tasks/index', [
            'pageTitle' => 'Задачи',
            'pageEyebrow' => 'CRM',
            'currentUser' => $user,
            'tasks' => Task::all($user, $filters),
            'filters' => $filters,
        ]);
    }

    public function assignees(): void
    {
        Auth::requireStaff();
        $query = trim((string) ($_GET['q'] ?? ''));
        Response::json([
            'ok' => true,
            'items' => User::searchStaff($query),
        ]);
    }

    public function create(): void
    {
        $user = Auth::requireStaff();
        Csrf::enforce();
        $errors = Validator::task($_POST);
        $assignees = [];
        foreach (array_values(array_unique(array_map('intval', (array) ($_POST['assignee_ids'] ?? [])))) as $id) {
            $assignee = User::find($id);
            if (!$assignee || $assignee->isStudent() || $assignee->status !== User::STATUS_ACTIVE) {
                $errors[] = 'Один из выбранных исполнителей недоступен.';
                continue;
            }
            $assignees[] = $assignee;
        }
        if ($errors) {
            Flash::set('error', implode(' ', array_unique($errors)));
            Response::redirect('/tasks');
        }

        $ids = Task::createSeries($_POST, $user->id, array_map(static fn (User $assignee): int => $assignee->id, $assignees));
        ActivityLog::record(
            count($ids) > 1 ? 'task.series_created' : 'task.created',
            count($ids) > 1 ? 'Создана серия повторяющихся задач' : 'Создана задача',
            $user,
            $assignees,
            'task',
            $ids[0] ?? null,
            [
                'description' => mb_substr(trim((string) $_POST['description']), 0, 240),
                'count' => count($ids),
                'confidential' => !empty($_POST['confidential']),
            ]
        );
        Flash::set('success', count($ids) > 1 ? 'Создано задач: ' . count($ids) . '.' : 'Задача создана.');
        Response::redirect('/tasks');
    }

    public function complete(string $id): void
    {
        $user = Auth::requireStaff();
        Csrf::enforce();
        $task = Task::findVisible((int) $id, $user);
        if (!$task) {
            Flash::set('error', 'Задача не найдена или недоступна.');
            Response::redirect('/tasks');
        }
        if (!Task::complete((int) $id, $user)) {
            Flash::set('error', 'Только исполнитель может отметить задачу выполненной.');
            Response::redirect('/tasks');
        }
        ActivityLog::record('task.completed', 'Задача выполнена', $user, Task::assignees((int) $id), 'task', (int) $id, [
            'description' => mb_substr((string) $task['description'], 0, 240),
            'changes' => [['field' => 'Статус', 'from' => 'В работе', 'to' => 'Выполнена']],
        ]);
        Flash::set('success', 'Задача отмечена выполненной.');
        Response::redirect('/tasks');
    }
}
