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
use App\Models\Contract;
use App\Models\ContractFile;
use App\Models\Lead;
use PDOException;
use RuntimeException;

final class ContractController
{
    public function index(): void
    {
        $user = Auth::requireCrmAccess();
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'date_from' => $this->filterDate($_GET['date_from'] ?? null),
            'date_to' => $this->filterDate($_GET['date_to'] ?? null),
        ];
        $selectedLead = null;
        if ((string) ($_GET['create'] ?? '') === '1') {
            $selectedLead = Lead::find((int) ($_GET['lead_id'] ?? 0));
        }
        $contracts = Contract::all($filters);
        View::render('crm/contracts', [
            'pageTitle' => 'Журнал договоров',
            'pageEyebrow' => 'CRM',
            'currentUser' => $user,
            'contracts' => $contracts,
            'contractFiles' => ContractFile::groupedForContracts(array_column($contracts, 'id')),
            'statuses' => Contract::STATUSES,
            'leadStatuses' => Lead::STATUSES,
            'directions' => Lead::DIRECTIONS,
            'selectedLead' => $selectedLead,
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        $data = $this->contractData($_POST);
        if ($this->reject($data)) {
            Response::redirect($this->createPath($data));
        }
        $sourceLead = !empty($data['lead_id']) ? Lead::find((int) $data['lead_id']) : null;
        $pdo = Database::connection();
        $storedFiles = [];
        try {
            $pdo->beginTransaction();
            $id = Contract::create($data, $user->id);
            $storedFiles = ContractFile::storeMany($id, $user->id, $_FILES['files'] ?? null);
            if (!empty($data['lead_id'])) {
                Lead::markConverted((int) $data['lead_id'], $user->id);
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ContractFile::deleteStored($storedFiles);
            $this->databaseError($exception);
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ContractFile::deleteStored($storedFiles);
            Flash::set('error', $exception->getMessage());
            Response::redirect($this->createPath($data));
        }
        $contract = Contract::find($id);
        $contractNumber = (string) ($contract['contract_number'] ?? '');
        ActivityLog::record('contract.created', 'Создан договор', $user, [], 'contract', $id, [
            'number' => $contractNumber,
            'counterparty' => trim((string) $data['counterparty']),
            'direction' => (string) ($data['direction'] ?? 'driving_school'),
            'files_uploaded' => count($storedFiles),
        ]);
        if (!empty($data['lead_id'])) {
            ActivityLog::record('lead.converted', 'Лид переведён в договор', $user, [], 'lead', (int) $data['lead_id'], [
                'name' => $sourceLead ? Lead::displayName($sourceLead) : '',
                'direction' => $sourceLead['direction'] ?? 'driving_school',
                'contract_id' => $id,
                'contract_number' => $contractNumber,
                'changes' => [['field' => 'Этап', 'from' => Lead::STATUSES[(string) ($sourceLead['status'] ?? '')] ?? '—', 'to' => Lead::STATUSES['contract']]],
            ]);
        }
        Flash::set('success', 'Договор №' . $contractNumber . ' добавлен в журнал.' . ($storedFiles ? ' Файлов загружено: ' . count($storedFiles) . '.' : ''));
        Response::redirect('/crm/contracts');
    }

    public function update(string $id): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        $contract = Contract::find((int) $id);
        if (!$contract) {
            Flash::set('error', 'Договор не найден.');
            Response::redirect('/crm/contracts');
        }
        $data = $this->contractData($_POST);
        if ($this->reject($data)) {
            Response::redirect('/crm/contracts');
        }
        $pdo = Database::connection();
        $storedFiles = [];
        try {
            $pdo->beginTransaction();
            Contract::update((int) $id, $data, $user->id);
            $storedFiles = ContractFile::storeMany((int) $id, $user->id, $_FILES['files'] ?? null);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ContractFile::deleteStored($storedFiles);
            $this->databaseError($exception);
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ContractFile::deleteStored($storedFiles);
            Flash::set('error', $exception->getMessage());
            Response::redirect('/crm/contracts');
        }
        $updatedContract = Contract::find((int) $id);
        $changes = ActivityLog::changes(
            $this->contractAuditState($contract),
            $this->contractAuditState($updatedContract ?? $contract),
            $this->contractAuditFields()
        );
        if ($storedFiles) {
            $changes[] = ['field' => 'Файлы', 'from' => 'Без новых файлов', 'to' => 'Добавлено: ' . count($storedFiles)];
        }
        if ($changes) {
            ActivityLog::record('contract.updated', 'Обновлён договор', $user, [], 'contract', (int) $id, [
                'number' => (string) ($updatedContract['contract_number'] ?? ''),
                'counterparty' => (string) ($updatedContract['counterparty'] ?? $contract['counterparty']),
                'from_status' => $contract['status'],
                'to_status' => $updatedContract['status'] ?? $contract['status'],
                'files_uploaded' => count($storedFiles),
                'changes' => $changes,
            ]);
        }
        Flash::set('success', 'Договор обновлён.' . ($storedFiles ? ' Новых файлов: ' . count($storedFiles) . '.' : ''));
        Response::redirect('/crm/contracts');
    }

    public function archive(string $id): void
    {
        $user = Auth::requireCrmAccess();
        Csrf::enforce();
        $contract = Contract::find((int) $id);
        if (!$contract) {
            Flash::set('error', 'Договор не найден.');
            Response::redirect('/crm/contracts');
        }
        Contract::archive((int) $id, $user->id);
        ActivityLog::record('contract.archived', 'Договор перенесён в архив', $user, [], 'contract', (int) $id, [
            'number' => $contract['contract_number'],
            'counterparty' => $contract['counterparty'],
            'changes' => [['field' => 'Состояние', 'from' => 'Активен', 'to' => 'Архив']],
        ]);
        Flash::set('success', 'Договор перенесён в архив.');
        Response::redirect('/crm/contracts');
    }

    public function download(string $id): void
    {
        Auth::requireCrmAccess();
        $file = ContractFile::find((int) $id);
        $path = $file ? ContractFile::absolutePath((string) $file['stored_path']) : null;
        if (!$file || $path === null) {
            Flash::set('error', 'Файл договора не найден.');
            Response::redirect('/crm/contracts');
        }

        $extension = pathinfo((string) $file['original_name'], PATHINFO_EXTENSION);
        $fallback = 'contract-file' . ($extension !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extension) : '');
        header('Content-Type: ' . (string) $file['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode((string) $file['original_name']));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private function reject(array $data): bool
    {
        $errors = Validator::contract($data);
        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            return true;
        }
        $leadId = (int) ($data['lead_id'] ?? 0);
        if ($leadId && !Lead::find($leadId)) {
            Flash::set('error', 'Выбранный лид не найден или уже недоступен.');
            return true;
        }
        return false;
    }

    private function contractData(array $data): array
    {
        $lead = Lead::find((int) ($data['lead_id'] ?? 0));
        if (!$lead) {
            return $data;
        }
        if (trim((string) ($data['counterparty'] ?? '')) === '') {
            $data['counterparty'] = Lead::displayName($lead);
        }
        if (trim((string) ($data['client_phone'] ?? '')) === '') {
            $data['client_phone'] = $lead['phone'] ?? '';
        }
        if (!isset(Lead::DIRECTIONS[(string) ($data['direction'] ?? '')])) {
            $data['direction'] = $lead['direction'];
        }
        return $data;
    }

    private function createPath(array $data): string
    {
        $leadId = (int) ($data['lead_id'] ?? 0);
        return $leadId ? '/crm/contracts?create=1&lead_id=' . $leadId : '/crm/contracts';
    }

    private function filterDate(mixed $value): string
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function databaseError(PDOException $exception): never
    {
        if ((string) $exception->getCode() === '23000') {
            Flash::set('error', 'Договор с таким номером уже существует.');
            Response::redirect('/crm/contracts');
        }
        throw $exception;
    }

    private function contractAuditState(array $contract): array
    {
        return [
            'contract_number' => $contract['contract_number'] ?? null,
            'registration_number' => $contract['registration_number'] ?? null,
            'lead_id' => !empty($contract['lead_id']) ? 'Лид №' . (int) $contract['lead_id'] : null,
            'counterparty' => $contract['counterparty'] ?? null,
            'client_phone' => $contract['client_phone'] ?? null,
            'subject' => $contract['subject'] ?? null,
            'direction' => Lead::DIRECTIONS[(string) ($contract['direction'] ?? '')] ?? ($contract['direction'] ?? null),
            'signed_on' => !empty($contract['signed_on']) ? format_date((string) $contract['signed_on'], 'd.m.Y') : null,
            'starts_on' => !empty($contract['starts_on']) ? format_date((string) $contract['starts_on'], 'd.m.Y') : null,
            'ends_on' => !empty($contract['ends_on']) ? format_date((string) $contract['ends_on'], 'd.m.Y') : null,
            'amount' => $contract['amount'] !== null ? number_format((float) $contract['amount'], 2, ',', ' ') . ' ₽' : null,
            'status' => Contract::STATUSES[(string) ($contract['status'] ?? '')] ?? ($contract['status'] ?? null),
            'notes' => $contract['notes'] ?? null,
        ];
    }

    private function contractAuditFields(): array
    {
        return [
            'contract_number' => 'Номер договора',
            'registration_number' => 'Регистрационный номер',
            'lead_id' => 'Лид',
            'counterparty' => 'Клиент',
            'client_phone' => 'Телефон',
            'subject' => 'Предмет договора',
            'direction' => 'Направление',
            'signed_on' => 'Дата заключения',
            'starts_on' => 'Начало действия',
            'ends_on' => 'Окончание действия',
            'amount' => 'Сумма',
            'status' => 'Статус',
            'notes' => 'Примечание',
        ];
    }
}
