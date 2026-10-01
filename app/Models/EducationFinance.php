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
        $payload = [
            'name' => $name,
            'description' => self::nullable($data['description'] ?? null),
            'price' => $price,
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            'updated_by' => $userId,
        ];
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE education_services SET name = :name, description = :description, price = :price,
                 status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO education_services (name, description, price, status, created_by, updated_by)
             VALUES (:name, :description, :price, :status, :created_by, :updated_by)'
        );
        $stmt->execute($payload + ['created_by' => $userId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function sales(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.*, CONCAT_WS(" ", seller.last_name, seller.first_name) AS seller_name,
                    COALESCE((SELECT SUM(p.amount) FROM student_payments p WHERE p.sale_id = s.id), 0) AS paid_total
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
        $soldOn = self::date($data['sold_on'] ?? null) ?: date('Y-m-d');
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_sales (student_id, service_id, service_name, amount, sold_on, notes, sold_by)
             VALUES (:student_id, :service_id, :service_name, :amount, :sold_on, :notes, :sold_by)'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'service_id' => (int) $service['id'],
            'service_name' => (string) $service['name'],
            'amount' => $amount,
            'sold_on' => $soldOn,
            'notes' => self::nullable($data['notes'] ?? null),
            'sold_by' => $userId,
        ]);
        return (int) Database::connection()->lastInsertId();
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
        if ($saleId !== null) {
            $saleCheck = Database::connection()->prepare(
                'SELECT s.id, s.amount,
                        COALESCE((SELECT SUM(p.amount) FROM student_payments p WHERE p.sale_id = s.id), 0) AS paid_total
                 FROM student_sales s WHERE s.id = :id AND s.student_id = :student_id AND s.status = "active"'
            );
            $saleCheck->execute(['id' => $saleId, 'student_id' => $studentId]);
            $sale = $saleCheck->fetch();
            if (!$sale) {
                throw new RuntimeException('Продажа услуги не найдена.');
            }
            $remaining = (float) $sale['amount'] - (float) $sale['paid_total'];
            if ((float) $amount > $remaining + 0.00001) {
                throw new RuntimeException('Сумма платежа превышает остаток по выбранной услуге.');
            }
        }
        $paidOn = self::dateTime($data['paid_on'] ?? null) ?: date('Y-m-d H:i:s');
        $insert = Database::connection()->prepare(
            'INSERT INTO student_payments (student_id, sale_id, kassa_id, amount, paid_on, notes, accepted_by)
             VALUES (:student_id, :sale_id, :kassa_id, :amount, :paid_on, :notes, :accepted_by)'
        );
        $insert->execute([
            'student_id' => $studentId,
            'sale_id' => $saleId,
            'kassa_id' => $kassaId,
            'amount' => $amount,
            'paid_on' => $paidOn,
            'notes' => self::nullable($data['notes'] ?? null),
            'accepted_by' => $userId,
        ]);
        return (int) Database::connection()->lastInsertId();
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
