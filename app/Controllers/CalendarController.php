<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\SchoolCalendar;
use App\Models\StudyClass;
use App\Models\User;

final class CalendarController
{
    public function index(): void
    {
        $user = Auth::requireLogin();
        $month = trim((string) ($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $filters = [
            'class_id' => (int) ($_GET['class_id'] ?? 0),
            'teacher_id' => (int) ($_GET['teacher_id'] ?? 0),
        ];
        View::render('education/calendar/index', [
            'pageTitle' => 'Календарь занятий',
            'pageEyebrow' => 'Единое расписание',
            'currentUser' => $user,
            'month' => $month,
            'calendar' => SchoolCalendar::month($user, $month, $filters),
            'classes' => $user->canManageEducation() ? StudyClass::all($user) : [],
            'teachers' => $user->canManageEducation() ? User::teachers() : [],
            'filters' => $filters,
        ]);
    }
}
