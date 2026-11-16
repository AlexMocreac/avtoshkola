<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\EducationFinance;
use App\Models\User;

$actor = User::staff()[0] ?? throw new RuntimeException('An active employee is required.');
$pdo = Database::connection();
$suffix = bin2hex(random_bytes(5));
$root = $child = $service = 0;
try {
    $root = EducationFinance::createServiceFolder(['name' => 'Папка услуг ' . $suffix]);
    $child = EducationFinance::createServiceFolder(['name' => 'Вложенная ' . $suffix, 'parent_id' => $root]);
    $service = EducationFinance::saveService(['name' => 'Услуга ' . $suffix, 'price' => '100'], $actor->id);
    EducationFinance::moveService($service, $child, $actor->id);
    if ((int) EducationFinance::service($service)['folder_id'] !== $child) throw new RuntimeException('Service was not moved into the folder.');
    $original = EducationFinance::service($service);
    EducationFinance::moveService($service, null, $actor->id);
    $moved = EducationFinance::service($service);
    if ($moved['folder_id'] !== null || $moved['name'] !== $original['name'] || $moved['service_data'] !== $original['service_data']) throw new RuntimeException('Moving to root changed service data.');
    $rejected = false;
    try { EducationFinance::moveServiceFolder($root, $child); } catch (RuntimeException) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('A folder cycle was accepted.');
    EducationFinance::moveServiceFolder($child, null);
    $check = $pdo->prepare('SELECT parent_id FROM education_service_folders WHERE id = ?');
    $check->execute([$child]);
    if ($check->fetchColumn() !== null) throw new RuntimeException('Folder did not move to root.');
    echo "PASS service-folder-moves\n";
} finally {
    if ($service) $pdo->prepare('DELETE FROM education_services WHERE id = ?')->execute([$service]);
    if ($child) $pdo->prepare('DELETE FROM education_service_folders WHERE id = ?')->execute([$child]);
    if ($root) $pdo->prepare('DELETE FROM education_service_folders WHERE id = ?')->execute([$root]);
}
