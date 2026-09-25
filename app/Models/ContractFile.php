<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

final class ContractFile
{
    public const MAX_FILES = 10;
    public const MAX_BYTES = 20 * 1024 * 1024;

    private const ALLOWED_MIMES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-tika-msoffice', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-tika-msoffice', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    public static function storeMany(int $contractId, int $uploadedBy, ?array $files): array
    {
        $uploads = self::normalize($files);
        if (!$uploads) {
            return [];
        }
        if (count($uploads) > self::MAX_FILES) {
            throw new RuntimeException('К одному договору можно добавить не более 10 файлов за раз.');
        }

        $validated = array_map([self::class, 'validateUpload'], $uploads);
        $stored = [];
        try {
            foreach ($validated as $upload) {
                $relativeDirectory = 'storage/contracts/' . date('Y/m');
                $absoluteDirectory = dirname(__DIR__, 2) . '/' . $relativeDirectory;
                if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
                    throw new RuntimeException('Не удалось подготовить хранилище файлов.');
                }

                $storedName = bin2hex(random_bytes(18)) . '.' . $upload['extension'];
                $relativePath = $relativeDirectory . '/' . $storedName;
                $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
                if (!move_uploaded_file($upload['tmp_name'], $absolutePath)) {
                    throw new RuntimeException('Не удалось сохранить файл «' . $upload['original_name'] . '».');
                }

                $stored[] = [
                    'id' => null,
                    'stored_path' => $relativePath,
                    'original_name' => $upload['original_name'],
                ];
                $storedIndex = array_key_last($stored);

                $stmt = Database::connection()->prepare(
                    'INSERT INTO contract_files (contract_id, original_name, stored_path, mime_type, size_bytes, uploaded_by)
                     VALUES (:contract_id, :original_name, :stored_path, :mime_type, :size_bytes, :uploaded_by)'
                );
                $stmt->execute([
                    'contract_id' => $contractId,
                    'original_name' => $upload['original_name'],
                    'stored_path' => $relativePath,
                    'mime_type' => $upload['mime_type'],
                    'size_bytes' => $upload['size'],
                    'uploaded_by' => $uploadedBy,
                ]);
                $stored[$storedIndex]['id'] = (int) Database::connection()->lastInsertId();
            }
        } catch (\Throwable $exception) {
            self::deleteStored($stored);
            throw $exception;
        }

        return $stored;
    }

    public static function groupedForContracts(array $contractIds): array
    {
        $contractIds = array_values(array_unique(array_filter(array_map('intval', $contractIds))));
        if (!$contractIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT id, contract_id, original_name, mime_type, size_bytes, created_at
             FROM contract_files WHERE contract_id IN ({$placeholders}) ORDER BY id"
        );
        $stmt->execute($contractIds);
        $grouped = [];
        foreach ($stmt->fetchAll() as $file) {
            $grouped[(int) $file['contract_id']][] = $file;
        }
        return $grouped;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.* FROM contract_files f
             INNER JOIN crm_contracts c ON c.id = f.contract_id
             WHERE f.id = :id AND c.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function absolutePath(string $storedPath): ?string
    {
        $storageRoot = realpath(dirname(__DIR__, 2) . '/storage/contracts');
        $absolute = realpath(dirname(__DIR__, 2) . '/' . ltrim($storedPath, '/'));
        if ($storageRoot === false || $absolute === false || !str_starts_with($absolute, $storageRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return is_file($absolute) ? $absolute : null;
    }

    public static function deleteStored(array $files): void
    {
        foreach ($files as $file) {
            $path = self::absolutePath((string) ($file['stored_path'] ?? ''));
            if ($path !== null) {
                @unlink($path);
            }
        }
    }

    private static function normalize(?array $files): array
    {
        if (!$files || !isset($files['name'])) {
            return [];
        }
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $uploads = [];
        foreach ($names as $index => $name) {
            $error = is_array($files['error']) ? ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) : $files['error'];
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploads[] = [
                'name' => (string) $name,
                'tmp_name' => (string) (is_array($files['tmp_name']) ? ($files['tmp_name'][$index] ?? '') : $files['tmp_name']),
                'size' => (int) (is_array($files['size']) ? ($files['size'][$index] ?? 0) : $files['size']),
                'error' => (int) $error,
            ];
        }
        return $uploads;
    }

    private static function validateUpload(array $upload): array
    {
        $originalName = trim(basename(str_replace('\\', '/', (string) $upload['name'])));
        $originalName = (string) preg_replace('/[\x00-\x1F\x7F"]/', '', $originalName);
        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Не удалось загрузить файл «' . ($originalName ?: 'без имени') . '».');
        }
        if ($originalName === '' || (int) $upload['size'] < 1 || (int) $upload['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Каждый файл должен быть непустым и не больше 20 МБ.');
        }
        if (!is_uploaded_file((string) $upload['tmp_name'])) {
            throw new RuntimeException('Получен некорректный загруженный файл.');
        }

        $extension = mb_strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_MIMES[$extension])) {
            throw new RuntimeException('Формат файла «' . $originalName . '» не поддерживается.');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES[$extension], true)) {
            throw new RuntimeException('Содержимое файла «' . $originalName . '» не соответствует его формату.');
        }

        return [
            'original_name' => mb_substr($originalName, 0, 255),
            'tmp_name' => (string) $upload['tmp_name'],
            'size' => (int) $upload['size'],
            'extension' => $extension,
            'mime_type' => $mime,
        ];
    }
}
