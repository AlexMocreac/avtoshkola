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
use App\Models\DeadlineEvent;
use App\Models\User;
use App\Models\Warning;

final class WarningController
{
    public function index(): void
    {
        $user = Auth::requireCrmAccess();
        View::render('warnings/index', [
            'pageTitle' => 'Предупреждения',
            'pageEyebrow' => 'Контроль сроков',
            'currentUser' => $user,
            'warnings' => Warning::all($user),
            'staff' => User::staff(),
        ]);
    }

    public function create(): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        $errors = Validator::deadline($_POST);
        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            Response::redirect('/warnings');
        }
        $impacted = User::find((int) ($_POST['impacted_user_id'] ?? 0));
        $id = DeadlineEvent::create($_POST, $user->id);
        ActivityLog::record('deadline.created', 'Добавлено событие со сроком', $user, $impacted ? [$impacted] : [], 'deadline', $id, [
            'title' => trim((string) $_POST['title']),
        ]);
        Flash::set('success', 'Событие добавлено в предупреждения.');
        Response::redirect('/warnings');
    }

    public function complete(string $id): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        $event = DeadlineEvent::find((int) $id);
        if (!$event) {
            Flash::set('error', 'Событие не найдено.');
            Response::redirect('/warnings');
        }
        if (DeadlineEvent::complete((int) $id, $user->id)) {
            $impacted = User::find((int) ($event['impacted_user_id'] ?? 0));
            ActivityLog::record('deadline.completed', 'Событие со сроком отмечено выполненным', $user, $impacted ? [$impacted] : [], 'deadline', (int) $id, [
                'title' => $event['title'],
                'changes' => [['field' => 'Статус', 'from' => 'В работе', 'to' => 'Выполнено']],
            ]);
            Flash::set('success', 'Предупреждение закрыто.');
        }
        Response::redirect('/warnings');
    }
}
