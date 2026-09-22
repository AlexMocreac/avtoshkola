<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Conversation;
use App\Models\User;

final class DashboardController
{
    public function index(): void
    {
        $user = Auth::requireLogin();
        $stats = $user->canManageUsers() ? User::stats() : null;
        $recentUsers = $user->canManageUsers()
            ? array_slice(User::all(['group' => 'students']), 0, 5) : [];

        View::render('dashboard/index', [
            'pageTitle' => 'Главная',
            'pageEyebrow' => 'Рабочее пространство',
            'currentUser' => $user,
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'unreadMessages' => Conversation::totalUnread($user->id),
        ]);
    }
}
