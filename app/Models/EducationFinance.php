<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

final class EducationFinance
{
    public static function kassas(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE k.status = "active"' : '';
        return Database::connection()->query(
            'SELECT k.*,
                    COALESCE((SELECT SUM(p.amount) FROM student_payments p WHERE p.kassa_id = k.id), 0) AS received_total,
                    (SELECT COUNT(*) FROM student_payments p WHERE p.kassa_id = k.id) AS payment_count
             FROM kassas k ' . $where . ' ORDER BY k.status, k.name'
        )->fetchAll();
    }

    public static function saveKassa(array $data, int $userId, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('Укажите название кассы.');
        }
        $status = (string) ($data['status'] ?? 'active');
        $payload = [
            'name' => $name,
            'description' => self::nullable($data['description'] ?? null),
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE kassas SET name = :name, description = :description, status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO kassas (name, description, status, created_by, updated_by)
             VALUES (:name, :description, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function services(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE status = "active"' : '';
        return Database::connection()->query('SELECT * FROM education_services ' . $where . ' ORDER BY status, name')->fetchAll();
    }

    public static function serviceFolders(): array
    {
        return Database::connection()->query('SELECT * FROM education_service_folders ORDER BY name')->fetchAll();
    }

    public static function createServiceFolder(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $parent = (int) ($data['parent_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 190) {
            throw new RuntimeException('Укажите название папки.');
        }
        if ($parent) {
            $check = Database::connection()->prepare('SELECT id FROM education_service_folders WHERE id = ?');
            $check->execute([$parent]);
            if (!$check->fetch()) { throw new RuntimeException('Папка не найдена.'); }
        }
        $stmt = Database::connection()->prepare('INSERT INTO education_service_folders (name, parent_id) VALUES (?, ?)');
        $stmt->execute([$name, $parent ?: null]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function moveServiceFolder(int $id, ?int $parentId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $folders = [];
            foreach ($pdo->query('SELECT id, parent_id FROM education_service_folders FOR UPDATE')->fetchAll() as $folder) $folders[(int) $folder['id']] = $folder;
            if (!isset($folders[$id])) throw new RuntimeException('Папка не найдена.');
            if ($parentId !== null && !isset($folders[$parentId])) throw new RuntimeException('Папка назначения не найдена.');
            $seen = [];
            for ($cursor = $parentId; $cursor !== null; $cursor = isset($folders[$cursor]['parent_id']) ? (int) $folders[$cursor]['parent_id'] : null) {
                if ($cursor === $id || isset($seen[$cursor])) throw new RuntimeException('Нельзя переместить папку в неё саму или её вложенную папку.');
                $seen[$cursor] = true;
            }
            $stmt = $pdo->prepare('UPDATE education_service_folders SET parent_id = ? WHERE id = ?');
            $stmt->execute([$parentId, $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function moveService(int $id, ?int $folderId, int $userId): void
    {
        if (!self::service($id)) throw new RuntimeException('Услуга не найдена.');
        if ($folderId !== null) {
            $stmt = Database::connection()->prepare('SELECT id FROM education_service_folders WHERE id = ?');
            $stmt->execute([$folderId]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Папка назначения не найдена.');
        }
        $stmt = Database::connection()->prepare('UPDATE education_services SET folder_id = ?, updated_by = ? WHERE id = ?');
        $stmt->execute([$folderId, $userId, $id]);
    }

    public static function service(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM education_services WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function saveService(array $data, int $userId, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $price = self::money($data['price'] ?? null);
        if ($name === '' || $price === null) {
            throw new RuntimeException('Укажите название и корректную стоимость услуги.');
        }
        $status = (string) ($data['status'] ?? 'active');
        $folder = (int) ($data['folder_id'] ?? 0);
        if ($folder) {
            $check = Database::connection()->prepare('SELECT id FROM education_service_folders WHERE id = ?');
            $check->execute([$folder]);
            if (!$check->fetch()) { throw new RuntimeException('Папка не найдена.'); }
        }
        $rows = [];
        foreach (($data['allocations'] ?? []) as $row) {
            if (!is_array($row)) { continue; }
            if (trim((string) ($row['amount'] ?? '')) === '') {
                foreach (($row['balances'] ?? []) as $value) {
                    if (trim((string) $value) !== '') { throw new RuntimeException('Укажите сумму оплаты для строки начислений.'); }
                }
                continue;
            }
            $amount = self::money($row['amount']);
            if ($amount === null || (float) $amount <= 0) { throw new RuntimeException('Сумма должна быть больше нуля.'); }
            $values = [];
            foreach (($row['balances'] ?? []) as $typeId => $value) {
                if ($value === '') { continue; }
                if (!ctype_digit((string) $typeId) || !is_numeric($value) || (float) $value < 0) { throw new RuntimeException('Укажите корректные значения балансов.'); }
                $values[(int) $typeId] = (float) $value;
            }
            $rows[] = ['amount' => $amount, 'balances' => $values];
        }
        $details = ['type' => in_array(($data['type'] ?? ''), ['service', 'product'], true) ? $data['type'] : 'service', 'code' => mb_substr(trim((string) ($data['code'] ?? '')), 0, 190), 'main_activity' => !empty($data['main_activity']), 'no_discounts' => !empty($data['no_discounts']), 'allocations' => $rows];
        $payload = [
            'folder_id' => $folder ?: null,
            'service_data' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'name' => $name,
            'description' => self::nullable($data['description'] ?? null),
            'price' => $price,
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE education_services SET folder_id = :folder_id, service_data = :service_data, name = :name, description = :description, price = :price,
                 status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO education_services (folder_id, service_data, name, description, price, status, created_by, updated_by)
             VALUES (:folder_id, :service_data, :name, :description, :price, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function sales(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.*, CONCAT_WS(" ", seller.last_name, seller.first_name) AS seller_name,
                    COALESCE((SELECT SUM(p.amount) FROM student_payments p WHERE p.sale_id = s.id), 0) AS paid_total,
                    (SELECT COUNT(*) FROM student_payments p WHERE p.sale_id = s.id) AS payment_count
             FROM student_sales s INNER JOIN users seller ON seller.id = s.sold_by
             WHERE s.student_id = :student_id AND s.status = "active" ORDER BY s.sold_on DESC, s.id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public static function createSale(int $studentId, array $data, int $userId): int
    {
        $service = self::service((int) ($data['service_id'] ?? 0));
        if (!$service || $service['status'] !== 'active') {
            throw new RuntimeException('Выберите действующую услугу.');
        }
        $amount = self::money($data['amount'] ?? $service['price']);
        if ($amount === null || (float) $amount <= 0) {
            throw new RuntimeException('Стоимость продажи должна быть больше нуля.');
        }
        $details = json_decode($service['service_data'] ?? '', true) ?: [];
        if (!empty($details['no_discounts']) && (float) $amount < (float) $service['price']) {
            throw new RuntimeException('Скидки запрещены.');
        }
        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contractId < 1) throw new RuntimeException('Выберите договор для продажи услуги.');
        $check = Database::connection()->prepare('SELECT id FROM crm_contracts WHERE id = :id AND student_id = :student_id AND deleted_at IS NULL AND status = "active"');
        $check->execute(['id' => $contractId, 'student_id' => $studentId]);
        if (!$check->fetchColumn()) throw new RuntimeException('Выберите действующий договор этого курсанта.');
        $soldOn = self::date($data['sold_on'] ?? null) ?: date('Y-m-d');
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_sales (student_id, contract_id, service_id, service_name, service_data, amount, sold_on, notes, sold_by)
             VALUES (:student_id, :contract_id, :service_id, :service_name, :service_data, :amount, :sold_on, :notes, :sold_by)'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'contract_id' => $contractId,
            'service_id' => (int) $service['id'],
            'service_name' => (string) $service['name'],
            'service_data' => $service['service_data'],
            'amount' => $amount,
            'sold_on' => $soldOn,
            'notes' => self::nullable($data['notes'] ?? null),
            'sold_by' => $userId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function linkLegacySaleToContract(int $studentId, int $saleId, int $contractId): void
    {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction(); else $pdo->exec('SAVEPOINT link_legacy_sale');
        try {
            $check = $pdo->prepare('SELECT id FROM crm_contracts WHERE id = ? AND student_id = ? AND deleted_at IS NULL');
            $check->execute([$contractId, $studentId]);
            if (!$check->fetchColumn()) throw new RuntimeException('Выберите договор этого курсанта.');
            $sale = $pdo->prepare('SELECT id, contract_id FROM student_sales WHERE id = ? AND student_id = ? AND status = "active" FOR UPDATE');
            $sale->execute([$saleId, $studentId]);
            $row = $sale->fetch();
            if (!$row || $row['contract_id'] !== null) throw new RuntimeException('Продажа не найдена или уже привязана к договору.');
            $pdo->prepare('UPDATE student_sales SET contract_id = ? WHERE id = ?')->execute([$contractId, $saleId]);
            $pdo->prepare('UPDATE student_payments SET contract_id = ? WHERE sale_id = ? AND student_id = ?')->execute([$contractId, $saleId, $studentId]);
            if ($ownsTransaction) $pdo->commit(); else $pdo->exec('RELEASE SAVEPOINT link_legacy_sale');
        } catch (\Throwable $exception) {
            if ($ownsTransaction) $pdo->rollBack(); else $pdo->exec('ROLLBACK TO SAVEPOINT link_legacy_sale');
            throw $exception;
        }
    }

    public static function changeUnpaidSale(int $studentId, int $saleId, ?array $data): void
    {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) { $pdo->beginTransaction(); } else { $pdo->exec('SAVEPOINT change_sale'); }
        try {
            $stmt = $pdo->prepare('SELECT * FROM student_sales WHERE id = ? AND student_id = ? AND status = "active" FOR UPDATE');
            $stmt->execute([$saleId, $studentId]);
            $sale = $stmt->fetch();
            if (!$sale) { throw new RuntimeException('Продажа не найдена.'); }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM student_payments WHERE sale_id = ?');
            $stmt->execute([$saleId]);
            if ((int) $stmt->fetchColumn() > 0) { throw new RuntimeException('Продажу с оплатами нельзя изменить или удалить.'); }
            if ($data === null) {
                if (!empty($sale['contract_id'])) {
                    $allocated = [];
                    $details = json_decode($sale['service_data'] ?? '', true) ?: [];
                    foreach ($details['allocations'] ?? [] as $row) foreach ($row['balances'] ?? [] as $typeId => $amount) $allocated[(int) $typeId] = ($allocated[(int) $typeId] ?? 0) + max(0, (float) $amount);
                    foreach (self::contractBalanceProgress($studentId) as $group) {
                        if ((int) $group['contract']['id'] !== (int) $sale['contract_id']) continue;
                        foreach ($allocated as $typeId => $amount) {
                            $balance = $group['balances'][$typeId] ?? null;
                            if ($balance && $balance['manual'] + $balance['automatic'] > $balance['assigned'] - $amount + 0.00001) throw new RuntimeException('Нельзя удалить продажу: по договору уже учтены занятия из этой услуги.');
                        }
                    }
                }
                $stmt = $pdo->prepare('UPDATE student_sales SET status = "cancelled" WHERE id = ?');
                $stmt->execute([$saleId]);
            } else {
                $amount = self::money($data['amount'] ?? null);
                $date = self::date($data['sold_on'] ?? null);
                if ($amount === null || (float) $amount <= 0 || !$date) { throw new RuntimeException('Укажите корректную сумму и дату продажи.'); }
                $details = json_decode($sale['service_data'] ?? '', true) ?: [];
                $service = $sale['service_id'] ? self::service((int) $sale['service_id']) : null;
                if (!empty($details['no_discounts']) && $service && (float) $amount < (float) $service['price']) { throw new RuntimeException('Для этой услуги скидки запрещены.'); }
                $stmt = $pdo->prepare('UPDATE student_sales SET amount = ?, sold_on = ?, notes = ? WHERE id = ?');
                $stmt->execute([$amount, $date, self::nullable($data['notes'] ?? null), $saleId]);
            }
            if ($ownsTransaction) { $pdo->commit(); } else { $pdo->exec('RELEASE SAVEPOINT change_sale'); }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) { $pdo->rollBack(); } else { $pdo->exec('ROLLBACK TO SAVEPOINT change_sale'); }
            throw $exception;
        }
    }

    /** Sold allocations are taken from the immutable sale snapshot, not the current service. */
    public static function contractBalanceProgress(int $studentId): array
    {
        return self::contractBalanceProgressMany([$studentId])[$studentId] ?? [];
    }

    public static function contractBalanceProgressMany(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), static fn (int $id): bool => $id > 0)));
        if (!$studentIds) return [];
        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $pdo->prepare('SELECT c.id, c.student_id, c.contract_number FROM crm_contracts c WHERE c.student_id IN (' . $placeholders . ') AND c.deleted_at IS NULL ORDER BY c.signed_on DESC, c.id DESC');
        $stmt->execute($studentIds);
        $contracts = [];
        foreach ($stmt->fetchAll() as $contract) $contracts[(int) $contract['id']] = ['contract' => $contract, 'balances' => []];
        if (!$contracts) return [];
        $types = [];
        foreach (EducationCatalog::balanceTypes() as $type) $types[(int) $type['id']] = $type;
        $stmt = $pdo->prepare('SELECT s.contract_id, s.service_data, s.amount, COALESCE((SELECT SUM(p.amount) FROM student_payments p WHERE p.sale_id = s.id), 0) AS paid_total FROM student_sales s JOIN crm_contracts c ON c.id = s.contract_id AND c.student_id = s.student_id WHERE s.student_id IN (' . $placeholders . ') AND c.deleted_at IS NULL AND s.status = "active"');
        $stmt->execute($studentIds);
        foreach ($stmt->fetchAll() as $sale) {
            $contractId = (int) $sale['contract_id'];
            if (!isset($contracts[$contractId])) continue;
            $details = json_decode($sale['service_data'] ?? '', true) ?: [];
            foreach ($details['allocations'] ?? [] as $allocation) {
                $threshold = (float) ($allocation['amount'] ?? 0);
                foreach ($allocation['balances'] ?? [] as $typeId => $rawAmount) {
                    $typeId = (int) $typeId;
                    $amount = (float) $rawAmount;
                    if ($amount <= 0 || !isset($types[$typeId])) continue;
                    if (!isset($contracts[$contractId]['balances'][$typeId])) $contracts[$contractId]['balances'][$typeId] = ['type' => $types[$typeId], 'assigned' => 0.0, 'paid' => 0.0, 'manual' => 0.0, 'automatic' => 0.0];
                    $contracts[$contractId]['balances'][$typeId]['assigned'] += $amount;
                    if (($threshold > 0 && $threshold <= (float) $sale['paid_total'] + 0.00001) || ((float) $sale['amount'] > 0 && (float) $sale['paid_total'] + 0.00001 >= (float) $sale['amount'])) $contracts[$contractId]['balances'][$typeId]['paid'] += $amount;
                }
            }
        }
        $stmt = $pdo->prepare('SELECT u.contract_id, u.balance_type_id, u.amount FROM contract_balance_manual_usage u JOIN crm_contracts c ON c.id = u.contract_id WHERE c.student_id IN (' . $placeholders . ') AND c.deleted_at IS NULL');
        $stmt->execute($studentIds);
        foreach ($stmt->fetchAll() as $usage) {
            $contractId = (int) $usage['contract_id']; $typeId = (int) $usage['balance_type_id'];
            if (isset($contracts[$contractId]['balances'][$typeId])) $contracts[$contractId]['balances'][$typeId]['manual'] = (float) $usage['amount'];
        }
        $stmt = $pdo->prepare('SELECT a.contract_id, a.balance_type_id, SUM(a.amount) AS amount FROM class_lesson_attendance a JOIN crm_contracts c ON c.id = a.contract_id WHERE c.student_id IN (' . $placeholders . ') AND c.deleted_at IS NULL GROUP BY a.contract_id, a.balance_type_id');
        $stmt->execute($studentIds);
        foreach ($stmt->fetchAll() as $usage) {
            $contractId = (int) $usage['contract_id']; $typeId = (int) $usage['balance_type_id'];
            if (isset($contracts[$contractId]['balances'][$typeId])) $contracts[$contractId]['balances'][$typeId]['automatic'] = (float) $usage['amount'];
        }
        $result = [];
        foreach ($contracts as $contract) if ($contract['balances']) $result[(int) $contract['contract']['student_id']][] = $contract;
        return $result;
    }

    public static function setContractManualUsage(int $studentId, int $contractId, int $typeId, $value, int $userId): void
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value)) throw new RuntimeException('Укажите корректное количество проведённых часов или занятий.');
        $pdo = Database::connection();
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction(); else $pdo->exec('SAVEPOINT manual_contract_usage');
        try {
            $lock = $pdo->prepare('SELECT id FROM student_sales WHERE contract_id = ? AND student_id = ? AND status = "active" ORDER BY id FOR UPDATE');
            $lock->execute([$contractId, $studentId]);
            $lock->fetchAll();
            $found = false;
            foreach (self::contractBalanceProgress($studentId) as $group) {
                if ((int) $group['contract']['id'] === $contractId && isset($group['balances'][$typeId])) { $found = true; break; }
            }
            if (!$found) throw new RuntimeException('Начисление по договору не найдено.');
            $stmt = $pdo->prepare('INSERT INTO contract_balance_manual_usage (contract_id, balance_type_id, amount, updated_by) VALUES (:contract_id, :type_id, :amount, :updated_by) ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_by = VALUES(updated_by)');
            $stmt->execute(['contract_id' => $contractId, 'type_id' => $typeId, 'amount' => $value, 'updated_by' => $userId]);
            if ($owns) $pdo->commit(); else $pdo->exec('RELEASE SAVEPOINT manual_contract_usage');
        } catch (\Throwable $exception) {
            if ($owns) $pdo->rollBack(); else $pdo->exec('ROLLBACK TO SAVEPOINT manual_contract_usage');
            throw $exception;
        }
    }

    public static function payments(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, k.name AS kassa_name, s.service_name,
                    CONCAT_WS(" ", acceptor.last_name, acceptor.first_name) AS accepted_by_name
             FROM student_payments p
             INNER JOIN kassas k ON k.id = p.kassa_id
             LEFT JOIN student_sales s ON s.id = p.sale_id
             INNER JOIN users acceptor ON acceptor.id = p.accepted_by
             WHERE p.student_id = :student_id ORDER BY p.paid_on DESC, p.id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public static function createPayment(int $studentId, array $data, int $userId): int
    {
        $amount = self::money($data['amount'] ?? null);
        if ($amount === null || (float) $amount <= 0) {
            throw new RuntimeException('Сумма платежа должна быть больше нуля.');
        }
        $kassaId = (int) ($data['kassa_id'] ?? 0);
        $stmt = Database::connection()->prepare('SELECT id FROM kassas WHERE id = :id AND status = "active"');
        $stmt->execute(['id' => $kassaId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Выберите действующую кассу.');
        }
        $saleId = (int) ($data['sale_id'] ?? 0) ?: null;
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) { $pdo->beginTransaction(); } else { $pdo->exec('SAVEPOINT service_payment'); }
        try {
            $sale = null;
            $paidBefore = 0.0;
            if ($saleId !== null) {
                // Serialize payments for a sale, including the balance credits they trigger.
                $saleCheck = $pdo->prepare('SELECT id, contract_id, amount, service_data FROM student_sales WHERE id = :id AND student_id = :student_id AND status = "active" FOR UPDATE');
                $saleCheck->execute(['id' => $saleId, 'student_id' => $studentId]);
                $sale = $saleCheck->fetch();
                if (!$sale) { throw new RuntimeException('Продажа не найдена.'); }
                $paid = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM student_payments WHERE sale_id = ?');
                $paid->execute([$saleId]);
                $paidBefore = (float) $paid->fetchColumn();
                if ((float) $amount > (float) $sale['amount'] - $paidBefore + 0.00001) {
                    throw new RuntimeException('Сумма платежа превышает остаток.');
                }
            }
            $paidOn = self::dateTime($data['paid_on'] ?? null) ?: date('Y-m-d H:i:s');
            $insert = $pdo->prepare('INSERT INTO student_payments (student_id, contract_id, sale_id, kassa_id, amount, paid_on, notes, accepted_by) VALUES (:student_id, :contract_id, :sale_id, :kassa_id, :amount, :paid_on, :notes, :accepted_by)');
            $insert->execute(['student_id' => $studentId, 'contract_id' => $sale['contract_id'] ?? null, 'sale_id' => $saleId, 'kassa_id' => $kassaId, 'amount' => $amount, 'paid_on' => $paidOn, 'notes' => self::nullable($data['notes'] ?? null), 'accepted_by' => $userId]);
            $paymentId = (int) $pdo->lastInsertId();
            if ($sale && empty($sale['contract_id'])) {
                $details = json_decode($sale['service_data'] ?? '', true) ?: [];
                $credit = $pdo->prepare('INSERT INTO student_balances (student_id, balance_type_id, amount, updated_by) SELECT :student_id, id, :amount, :updated_by FROM student_balance_types WHERE id = :type_id ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount), updated_by = VALUES(updated_by)');
                foreach ($details['allocations'] ?? [] as $row) {
                    $threshold = (float) ($row['amount'] ?? 0);
                    if ($threshold > $paidBefore && $threshold <= $paidBefore + (float) $amount + 0.00001) {
                        foreach ($row['balances'] ?? [] as $typeId => $value) {
                            if ((float) $value > 0) { $credit->execute(['student_id' => $studentId, 'type_id' => (int) $typeId, 'amount' => $value, 'updated_by' => $userId]); }
                        }
                    }
                }
            }
            if ($ownsTransaction) { $pdo->commit(); } else { $pdo->exec('RELEASE SAVEPOINT service_payment'); }
            return $paymentId;
        } catch (\Throwable $exception) {
            if ($ownsTransaction) { $pdo->rollBack(); } else { $pdo->exec('ROLLBACK TO SAVEPOINT service_payment'); }
            throw $exception;
        }
    }

    public static function totals(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                COALESCE((SELECT SUM(amount) FROM student_sales WHERE student_id = :student_sales_id AND status = "active"), 0) AS sold_total,
                COALESCE((SELECT SUM(amount) FROM student_payments WHERE student_id = :student_payments_id), 0) AS paid_total'
        );
        $stmt->execute(['student_sales_id' => $studentId, 'student_payments_id' => $studentId]);
        $row = $stmt->fetch() ?: ['sold_total' => 0, 'paid_total' => 0];
        $row['debt_total'] = max(0, (float) $row['sold_total'] - (float) $row['paid_total']);
        return $row;
    }

    private static function money(mixed $value): ?string
    {
        $value = str_replace([' ', ','], ['', '.'], trim((string) $value));
        if ($value === '' || !is_numeric($value) || (float) $value < 0 || (float) $value > 9999999999.99) {
            return null;
        }
        return number_format((float) $value, 2, '.', '');
    }

    private static function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function dateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        foreach (['!Y-m-d\\TH:i', '!Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
