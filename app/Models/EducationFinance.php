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

    public static function createServiceFolder(array $data): void
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
        $soldOn = self::date($data['sold_on'] ?? null) ?: date('Y-m-d');
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_sales (student_id, service_id, service_name, service_data, amount, sold_on, notes, sold_by)
             VALUES (:student_id, :service_id, :service_name, :service_data, :amount, :sold_on, :notes, :sold_by)'
        );
        $stmt->execute([
            'student_id' => $studentId,
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
                $saleCheck = $pdo->prepare('SELECT id, amount, service_data FROM student_sales WHERE id = :id AND student_id = :student_id AND status = "active" FOR UPDATE');
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
            $insert = $pdo->prepare('INSERT INTO student_payments (student_id, sale_id, kassa_id, amount, paid_on, notes, accepted_by) VALUES (:student_id, :sale_id, :kassa_id, :amount, :paid_on, :notes, :accepted_by)');
            $insert->execute(['student_id' => $studentId, 'sale_id' => $saleId, 'kassa_id' => $kassaId, 'amount' => $amount, 'paid_on' => $paidOn, 'notes' => self::nullable($data['notes'] ?? null), 'accepted_by' => $userId]);
            $paymentId = (int) $pdo->lastInsertId();
            if ($sale) {
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
