<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\EducationCatalog;
use App\Models\StudyClass;
use App\Models\User;

function stageFourCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$actor = User::staff()[0] ?? null;
if (!$actor) {
    throw new RuntimeException('Stage-four test requires one active employee.');
}

$seededCheckpoints = EducationCatalog::checkpoints(false);
stageFourCheck(count($seededCheckpoints) >= 17, 'Learning checkpoint defaults were not seeded.');
stageFourCheck(count(array_filter(
    $seededCheckpoints,
    static fn (array $checkpoint): bool => $checkpoint['code'] === 'ВнТ'
)) >= 2, 'Repeated checkpoint codes from the reference catalog were not preserved.');

$seededBalanceTypes = EducationCatalog::balanceTypes(false);
stageFourCheck(count($seededBalanceTypes) >= 18, 'Student balance type defaults were not seeded.');
stageFourCheck(count(array_filter(
    $seededBalanceTypes,
    static fn (array $type): bool => $type['code'] === 'A'
)) >= 4, 'Repeated balance type codes from the reference catalog were not preserved.');
stageFourCheck(count(array_filter(
    $seededBalanceTypes,
    static fn (array $type): bool => $type['name'] === 'Практическое вождение' && $type['status'] === 'archived'
)) === 1, 'The hidden reference balance type was not seeded as archived.');

$pdo->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $teacher = User::create([
        'login' => 'stage4.teacher.' . $suffix,
        'email' => 'stage4.teacher.' . $suffix . '@example.test',
        'phone' => '',
        'first_name' => 'Преподаватель',
        'last_name' => 'Структура',
        'middle_name' => '',
        'role' => User::ROLE_TEACHER,
        'password' => 'StageFour123!',
        'access_months' => '',
    ], $actor->id);
    $student = User::create([
        'login' => 'stage4.student.' . $suffix,
        'email' => 'stage4.student.' . $suffix . '@example.test',
        'phone' => '',
        'first_name' => 'Курсант',
        'last_name' => 'Структура',
        'middle_name' => '',
        'role' => User::ROLE_STUDENT,
        'password' => 'StageFour123!',
        'access_months' => '12',
    ], $actor->id);

    $classFolderId = EducationCatalog::saveClassFolder(['name' => 'Папка ' . $suffix, 'sort_order' => 5], $actor->id);
    $programFolderId = EducationCatalog::saveProgramFolder(['name' => 'Программы ' . $suffix], $actor->id);
    $programId = EducationCatalog::saveProgram([
        'folder_id' => $programFolderId,
        'name' => 'Программа ' . $suffix,
        'code' => 'P-' . $suffix,
    ], $actor->id);
    $checkpointId = EducationCatalog::saveCheckpoint([
        'name' => 'Контрольная точка ' . $suffix,
        'code' => 'КТ-' . $suffix,
        'sort_order' => 10,
    ], $actor->id);
    $balanceTypeId = EducationCatalog::saveBalanceType([
        'name' => 'Баланс ' . $suffix,
        'code' => 'Б-' . $suffix,
        'unit' => 'hours',
        'color' => '#4768db',
    ], $actor->id);

    $classId = StudyClass::create([
        'folder_id' => $classFolderId,
        'program_id' => $programId,
        'name' => 'Класс ' . $suffix,
        'group_number' => 'S4-' . $suffix,
        'classroom' => 'Кабинет 4',
        'teacher_id' => $teacher->id,
        'starts_on' => '2097-01-01',
        'planned_lesson_count' => '2',
        'lesson_duration_minutes' => '90',
        'status' => 'active',
        'schedule' => [1 => '09:00'],
    ], $actor->id);
    StudyClass::enroll($classId, $student->id, $actor->id, '2097-01-01');

    $class = StudyClass::find($classId);
    stageFourCheck((int) ($class['folder_id'] ?? 0) === $classFolderId, 'Class folder was not saved.');
    stageFourCheck((int) ($class['program_id'] ?? 0) === $programId, 'Training program was not linked.');
    stageFourCheck(($class['study_program_name'] ?? '') === 'Программа ' . $suffix, 'Program name was not resolved.');

    $teacherFolders = EducationCatalog::classFolders($teacher);
    stageFourCheck(count(array_filter($teacherFolders, static fn (array $folder): bool => (int) $folder['id'] === $classFolderId)) === 1, 'Teacher cannot see the assigned class folder.');

    EducationCatalog::setCheckpointState($student->id, $checkpointId, true, $teacher->id);
    $states = EducationCatalog::checkpointStateMap([$student->id]);
    stageFourCheck(!empty($states[$student->id][$checkpointId]['is_completed']), 'Checkpoint state was not saved.');
    EducationCatalog::setCheckpointState($student->id, $checkpointId, false, $teacher->id);
    $states = EducationCatalog::checkpointStateMap([$student->id]);
    stageFourCheck(empty($states[$student->id][$checkpointId]['is_completed']), 'Checkpoint state was not deactivated.');

    EducationCatalog::setStudentBalances($student->id, [$balanceTypeId => '12.5'], $actor->id);
    $balances = EducationCatalog::balanceValueMap([$student->id]);
    stageFourCheck((float) ($balances[$student->id][$balanceTypeId] ?? 0) === 12.5, 'Student balance was not saved.');

    echo "PASS stage-four-learning-structure\n";
} finally {
    $pdo->rollBack();
}
