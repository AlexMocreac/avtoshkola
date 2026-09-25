<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Warning;

final class EventController
{
    public function index(): void
    {
        $user = Auth::requireCrmAccess();
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'action' => trim((string) ($_GET['action'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        View::render('events/index', [
            'pageTitle' => 'Лента событий',
            'pageEyebrow' => 'Контроль действий',
            'currentUser' => $user,
            'events' => ActivityLog::all($filters, 200, $user->id),
            'actions' => ActivityLog::actions($user->id),
            'filters' => $filters,
            'warnings' => array_slice(Warning::all($user), 0, 8),
        ]);
    }
}
