<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

final class StudentFile
{
    private const MAX_BYTES = 20 * 1024 * 1024;
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

    public static function all(int $studentId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM student_files WHERE student_id = :student_id ORDER BY id DESC');
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public static function store(int $studentId, int $uploadedBy, ?array $file): int
    {
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Выберите файл.');
        }
        $name = mb_substr(trim(basename(str_replace('\\', '/', (string) ($file['name'] ?? '')))), 0, 255);
        $size = (int) ($file['size'] ?? 0);
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ((int) $file['error'] !== UPLOAD_ERR_OK || $name === '' || $size < 1 || $size > self::MAX_BYTES || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Файл должен быть непустым, корректно загруженным и не больше 20 МБ.');
        }
        $extension = mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::ALLOWED_MIMES[$extension]) || !in_array($mime, self::ALLOWED_MIMES[$extension], true)) {
            throw new RuntimeException('Этот формат файла не поддерживается.');
        }
        $relativeDirectory = 'storage/students/' . $studentId . '/' . date('Y/m');
        $absoluteDirectory = dirname(__DIR__, 2) . '/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('Не удалось подготовить хранилище файлов.');
        }
        $relativePath = $relativeDirectory . '/' . bin2hex(random_bytes(18)) . '.' . $extension;
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new RuntimeException('Не удалось сохранить файл.');
        }
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO student_files (student_id, original_name, stored_path, mime_type, size_bytes, uploaded_by)
                 VALUES (:student_id, :original_name, :stored_path, :mime_type, :size_bytes, :uploaded_by)'
            );
            $stmt->execute([
                'student_id' => $studentId,
                'original_name' => $name,
                'stored_path' => $relativePath,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'uploaded_by' => $uploadedBy,
            ]);
            return (int) Database::connection()->lastInsertId();
        } catch (\Throwable $exception) {
            @unlink($absolutePath);
            throw $exception;
        }
    }

    public static function storePhoto(int $studentId, ?array $file): ?string
    {
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $mime = $tmp !== '' && is_uploaded_file($tmp) ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ((int) $file['error'] !== UPLOAD_ERR_OK || $size < 1 || $size > 5 * 1024 * 1024 || !isset($extensions[$mime])) {
            throw new RuntimeException('Фото должно быть JPG, PNG или WEBP и не больше 5 МБ.');
        }
        $directory = 'storage/students/' . $studentId . '/photos';
        $absoluteDirectory = dirname(__DIR__, 2) . '/' . $directory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('Не удалось подготовить хранилище фотографий.');
        }
        $relative = $directory . '/' . bin2hex(random_bytes(18)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, dirname(__DIR__, 2) . '/' . $relative)) {
            throw new RuntimeException('Не удалось сохранить фотографию.');
        }
        return $relative;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM student_files WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function absolutePath(string $storedPath): ?string
    {
        $storageRoot = realpath(dirname(__DIR__, 2) . '/storage/students');
        $absolute = realpath(dirname(__DIR__, 2) . '/' . ltrim($storedPath, '/'));
        if ($storageRoot === false || $absolute === false || !str_starts_with($absolute, $storageRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return is_file($absolute) ? $absolute : null;
    }
}
