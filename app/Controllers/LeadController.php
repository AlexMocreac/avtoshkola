<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\User;

final class LeadController
{
    public function index(): void
    {
        $user = Auth::requireCrmAccess();
        $direction = (string) ($_GET['direction'] ?? 'driving_school');
        $focusedLead = Lead::find((int) ($_GET['lead_id'] ?? 0));
        if ($focusedLead) {
            $direction = (string) $focusedLead['direction'];
        }
        if (!isset(Lead::DIRECTIONS[$direction])) {
            $direction = 'driving_school';
        }
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'direction' => $direction,
        ];
        $leads = Lead::all($filters);
        $leadsByStatus = array_fill_keys(array_keys(Lead::STATUSES), []);
        foreach ($leads as $lead) {
            $leadsByStatus[$lead['status']][] = $lead;
        }
        View::render('crm/leads', [
            'pageTitle' => 'Клиенты и лиды',
            'pageEyebrow' => 'CRM',
            'currentUser' => $user,
            'leads' => $leads,
            'leadsByStatus' => $leadsByStatus,
            'stats' => Lead::stats($filters),
            'statuses' => Lead::STATUSES,
            'directions' => Lead::DIRECTIONS,
            'directionTotals' => Lead::directionTotals(),
            'staff' => User::staff(),
            'filters' => $filters,
            'focusedLeadId' => $focusedLead ? (int) $focusedLead['id'] : null,
        ]);
    }

    public function create(): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        if ($this->reject($_POST)) {
            Response::redirect($this->indexPath($_POST));
        }
        $id = Lead::create($_POST, $user->id);
        $lead = Lead::find($id);
        $assignee = User::find((int) ($_POST['assigned_to'] ?? 0));
        ActivityLog::record('lead.created', 'Создан клиент или лид', $user, $assignee ? [$assignee] : [], 'lead', $id, [
            'name' => $lead ? Lead::displayName($lead) : '',
            'status' => $_POST['status'] ?? 'new',
            'direction' => $_POST['direction'] ?? 'driving_school',
        ]);
        Flash::set('success', 'Лид добавлен в CRM.');
        Response::redirect($this->indexPath($_POST));
    }

    public function update(string $id): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        $lead = Lead::find((int) $id);
        if (!$lead) {
            Flash::set('error', 'Лид не найден.');
            Response::redirect('/crm/leads');
        }
        if ($this->reject($_POST)) {
            Response::redirect($this->indexPath($_POST));
        }
        Lead::update((int) $id, $_POST, $user->id);
        $updatedLead = Lead::find((int) $id);
        $assignee = User::find((int) ($_POST['assigned_to'] ?? 0));
        $statusChanged = (string) $lead['status'] !== (string) ($updatedLead['status'] ?? $lead['status']);
        $changes = ActivityLog::changes(
            $this->leadAuditState($lead),
            $this->leadAuditState($updatedLead ?? $lead),
            $this->leadAuditFields()
        );
        if ($changes) {
            ActivityLog::record($statusChanged ? 'lead.stage_changed' : 'lead.updated', $statusChanged ? 'Изменён этап лида' : 'Обновлена карточка лида', $user, $assignee ? [$assignee] : [], 'lead', (int) $id, [
                'name' => Lead::displayName($updatedLead ?? $lead),
                'from_status' => $lead['status'],
                'to_status' => $updatedLead['status'] ?? $lead['status'],
                'from_direction' => $lead['direction'],
                'to_direction' => $updatedLead['direction'] ?? $lead['direction'],
                'direction' => $updatedLead['direction'] ?? $lead['direction'],
                'changes' => $changes,
            ]);
        }
        Flash::set('success', 'Карточка лида обновлена.');
        Response::redirect($this->indexPath($_POST));
    }

    public function status(string $id): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce(true);
        $status = $_POST['status'] ?? null;
        $fromStatus = $_POST['from_status'] ?? null;
        if (!is_string($status) || !isset(Lead::STATUSES[$status])
            || !is_string($fromStatus) || !isset(Lead::STATUSES[$fromStatus])) {
            Response::json(['ok' => false, 'message' => 'Выберите допустимый этап лида.'], 422);
        }
        $lead = Lead::find((int) $id);
        if (!$lead) {
            Response::json(['ok' => false, 'message' => 'Лид не найден.'], 404);
        }
        if ($lead['status'] !== $fromStatus) {
            Response::json(['ok' => false, 'message' => 'Этап уже изменён. Обновите страницу и повторите действие.'], 409);
        }
        if ($status === $fromStatus) {
            Response::json(['ok' => true, 'lead' => $lead]);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (!Lead::moveToStatus((int) $id, $fromStatus, $status, $user->id)) {
                $pdo->rollBack();
                Response::json(['ok' => false, 'message' => 'Лид уже изменён. Обновите страницу и повторите действие.'], 409);
            }
            $updatedLead = Lead::find((int) $id);
            $assignee = User::find((int) ($lead['assigned_to'] ?? 0));
            ActivityLog::record('lead.stage_changed', 'Изменён этап лида', $user, $assignee ? [$assignee] : [], 'lead', (int) $id, [
                'name' => Lead::displayName($lead),
                'from_status' => $fromStatus,
                'to_status' => $status,
                'direction' => $lead['direction'],
                'changes' => ActivityLog::changes(
                    ['status' => Lead::STATUSES[$fromStatus]],
                    ['status' => Lead::STATUSES[$status]],
                    ['status' => 'Этап']
                ),
            ]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        Response::json(['ok' => true, 'lead' => $updatedLead]);
    }

    public function archive(string $id): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        $lead = Lead::find((int) $id);
        if (!$lead) {
            Flash::set('error', 'Лид не найден.');
            Response::redirect('/crm/leads');
        }
        Lead::archive((int) $id, $user->id);
        ActivityLog::record('lead.archived', 'Лид перенесён в архив', $user, [], 'lead', (int) $id, [
            'name' => Lead::displayName($lead),
            'direction' => $lead['direction'],
            'changes' => [['field' => 'Состояние', 'from' => 'Активен', 'to' => 'Архив']],
        ]);
        Flash::set('success', 'Лид перенесён в архив.');
        Response::redirect($this->indexPath($lead));
    }

    public function history(string $id): void
    {
        Auth::requireCrmAccess();
        $lead = Lead::find((int) $id);
        if (!$lead) {
            Response::json(['ok' => false, 'message' => 'Лид не найден.'], 404);
        }

        $events = array_map(function (array $event): array {
            $details = $event['details'] ? (json_decode((string) $event['details'], true) ?: []) : [];
            return [
                'id' => (int) $event['id'],
                'title' => (string) $event['action_name'],
                'actor' => (string) $event['actor_name'],
                'created_at' => (string) $event['created_at'],
                'description' => $this->historyDescription((string) $event['action_key'], $details),
            ];
        }, ActivityLog::forEntity('lead', (int) $id));

        Response::json([
            'ok' => true,
            'lead' => [
                'id' => (int) $lead['id'],
                'name' => Lead::displayName($lead),
                'phone' => (string) ($lead['phone'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'company' => (string) ($lead['company_name'] ?? ''),
                'source' => (string) ($lead['source'] ?? ''),
                'notes' => (string) ($lead['notes'] ?? ''),
                'manager' => (string) ($lead['assignee_name'] ?? ''),
                'status' => Lead::STATUSES[$lead['status']] ?? (string) $lead['status'],
                'direction' => Lead::DIRECTIONS[$lead['direction']] ?? (string) $lead['direction'],
            ],
            'events' => $events,
        ]);
    }

    private function reject(array $data): bool
    {
        $errors = Validator::lead($data);
        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            return true;
        }
        $assigneeId = (int) ($data['assigned_to'] ?? 0);
        if ($assigneeId) {
            $assignee = User::find($assigneeId);
            if (!$assignee || $assignee->isStudent() || $assignee->status !== User::STATUS_ACTIVE) {
                Flash::set('error', 'Ответственным может быть только активный сотрудник.');
                return true;
            }
        }
        return false;
    }

    private function indexPath(array $data): string
    {
        $direction = (string) ($data['direction'] ?? 'driving_school');
        return '/crm/leads?direction=' . rawurlencode(isset(Lead::DIRECTIONS[$direction]) ? $direction : 'driving_school');
    }

    private function historyDescription(string $actionKey, array $details): string
    {
        if ($actionKey === 'lead.created') {
            $status = Lead::STATUSES[(string) ($details['status'] ?? '')] ?? 'Новый';
            $direction = Lead::DIRECTIONS[(string) ($details['direction'] ?? '')] ?? 'Автошкола';
            return 'Добавлен в воронку «' . $direction . '» на этап «' . $status . '».';
        }
        if (in_array($actionKey, ['lead.stage_changed', 'lead.updated'], true) && !empty($details['changes']) && is_array($details['changes'])) {
            return implode(' ', array_map(static function (array $change): string {
                return (string) ($change['field'] ?? 'Поле') . ': «' . (string) ($change['from'] ?? '—') . '» → «' . (string) ($change['to'] ?? '—') . '».';
            }, $details['changes']));
        }
        if ($actionKey === 'lead.stage_changed') {
            $from = Lead::STATUSES[(string) ($details['from_status'] ?? '')] ?? (string) ($details['from_status'] ?? '');
            $to = Lead::STATUSES[(string) ($details['to_status'] ?? '')] ?? (string) ($details['to_status'] ?? '');
            return 'Этап изменён: «' . $from . '» → «' . $to . '».';
        }
        if ($actionKey === 'lead.converted') {
            return 'Создан договор №' . (string) ($details['contract_number'] ?? '—') . '.';
        }
        return '';
    }

    private function leadAuditState(array $lead): array
    {
        return [
            'first_name' => $lead['first_name'] ?? null,
            'last_name' => $lead['last_name'] ?? null,
            'middle_name' => $lead['middle_name'] ?? null,
            'company_name' => $lead['company_name'] ?? null,
            'phone' => $lead['phone'] ?? null,
            'email' => $lead['email'] ?? null,
            'source' => $lead['source'] ?? null,
            'direction' => Lead::DIRECTIONS[(string) ($lead['direction'] ?? '')] ?? ($lead['direction'] ?? null),
            'status' => Lead::STATUSES[(string) ($lead['status'] ?? '')] ?? ($lead['status'] ?? null),
            'notes' => $lead['notes'] ?? null,
            'next_contact_at' => !empty($lead['next_contact_at']) ? format_date((string) $lead['next_contact_at']) : null,
            'assigned_to' => $lead['assignee_name'] ?? null,
        ];
    }

    private function leadAuditFields(): array
    {
        return [
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'middle_name' => 'Отчество',
            'company_name' => 'Организация',
            'phone' => 'Телефон',
            'email' => 'Email',
            'source' => 'Источник',
            'direction' => 'Направление',
            'status' => 'Этап',
            'notes' => 'Заметки',
            'next_contact_at' => 'Следующий контакт',
            'assigned_to' => 'Ответственный',
        ];
    }
}
