<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(190) NOT NULL UNIQUE,
        ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $file) {
    $name = basename($file);
    $check = $pdo->prepare('SELECT 1 FROM migrations WHERE migration = :migration');
    $check->execute(['migration' => $name]);
    if ($check->fetchColumn()) {
        echo "SKIP {$name}\n";
        continue;
    }

    $sql = trim((string) file_get_contents($file));
    try {
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])) as $statement) {
            $pdo->exec($statement);
        }
        $record = $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
        $record->execute(['migration' => $name]);
        echo "DONE {$name}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "FAILED {$name}: {$exception->getMessage()}\n");
        exit(1);
    }
}
