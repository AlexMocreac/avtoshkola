<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Conversation;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Models\Warning;

final class DashboardController
{
    public function index(): void
    {
        $user = Auth::requireLogin();
        $stats = $user->canManageUsers() ? User::stats() : null;
        $recentUsers = $user->canManageUsers()
            ? array_slice(User::all(['group' => 'students']), 0, 5) : [];
        $leadStats = $user->canUseCrm() ? Lead::stats() : null;
        $allWarnings = $user->canUseCrm() ? Warning::all($user) : [];
        $warnings = array_slice($allWarnings, 0, 5);
        $recentEvents = $user->canUseCrm() ? array_slice(ActivityLog::all([], 6, $user->id), 0, 6) : [];

        View::render('dashboard/index', [
            'pageTitle' => 'Главная',
            'pageEyebrow' => 'Рабочее пространство',
            'currentUser' => $user,
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'leadStats' => $leadStats,
            'activeContracts' => $user->canUseCrm() ? Contract::activeCount() : null,
            'warnings' => $warnings,
            'warningCount' => count($allWarnings),
            'recentEvents' => $recentEvents,
            'openTasks' => $user->canUseTasks() ? Task::openCountFor($user) : null,
            'unreadMessages' => Conversation::totalUnread($user->id),
        ]);
    }
}
