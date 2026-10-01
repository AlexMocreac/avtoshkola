<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Contract;
use App\Models\EducationFinance;
use App\Models\SchoolCalendar;
use App\Models\StudentProfile;
use App\Models\StudyClass;
use App\Models\User;

function stageThreeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$actor = User::staff()[0] ?? null;
if (!$actor) {
    throw new RuntimeException('Stage-three test requires one active employee.');
}

$pdo->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $teacher = User::create([
        'login' => 'stage3.teacher.' . $suffix,
        'email' => 'stage3.teacher.' . $suffix . '@example.test',
        'phone' => '',
        'first_name' => 'Тестовый',
        'last_name' => 'Преподаватель',
        'middle_name' => '',
        'role' => User::ROLE_TEACHER,
        'password' => 'StageThree123!',
        'access_months' => '',
    ], $actor->id);
    $student = User::create([
        'login' => 'stage3.student.' . $suffix,
        'email' => 'stage3.student.' . $suffix . '@example.test',
        'phone' => '+7 900 333-44-55',
        'first_name' => 'Тестовый',
        'last_name' => 'Курсант',
        'middle_name' => '',
        'role' => User::ROLE_STUDENT,
        'password' => 'StageThree123!',
        'access_months' => '12',
    ], $actor->id);

    $startsOn = '2098-01-01';
    $holidayDate = (new DateTimeImmutable($startsOn))->modify('next monday')->format('Y-m-d');
    SchoolCalendar::createHoliday(['holiday_date' => $holidayDate, 'name' => 'Проверочный выходной'], $actor->id);

    $classId = StudyClass::create([
        'name' => 'Тестовая категория B',
        'group_number' => 'TEST-' . $suffix,
        'classroom' => 'Кабинет 1',
        'study_program' => 'Подготовка водителей категории B',
        'teacher_id' => $teacher->id,
        'starts_on' => $startsOn,
        'driving_starts_on' => '',
        'internal_exam_on' => '',
        'inspection_registration_on' => '',
        'planned_lesson_count' => '8',
        'lesson_duration_minutes' => '90',
        'status' => 'active',
        'schedule' => [1 => '09:00', 3 => '09:00', 5 => '09:00'],
    ], $actor->id);
    $lessons = StudyClass::lessons($classId);
    stageThreeCheck(count($lessons) === 8, 'Automatic lesson generator created an invalid lesson count.');
    stageThreeCheck(!in_array($holidayDate, array_map(static fn (array $lesson): string => substr((string) $lesson['starts_at'], 0, 10), $lessons), true), 'Holiday was not skipped by the lesson generator.');

    StudyClass::enroll($classId, $student->id, $actor->id, $startsOn);
    stageThreeCheck((int) (StudyClass::currentForStudent($student->id)['id'] ?? 0) === $classId, 'Student was not enrolled in the class.');
    StudentProfile::update($student->id, [
        'gearbox' => 'manual',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'citizenship' => 'Россия',
        'registration_number' => 'REG-' . $suffix,
        'customer_type' => 'student',
    ]);
    stageThreeCheck((StudentProfile::find($student->id)['gearbox'] ?? null) === 'manual', 'Student profile was not updated.');

    $serviceId = EducationFinance::saveService(['name' => 'Курс B ' . $suffix, 'price' => '50000', 'status' => 'active'], $actor->id);
    $kassaId = EducationFinance::saveKassa(['name' => 'Тестовая касса ' . $suffix, 'status' => 'active'], $actor->id);
    $saleId = EducationFinance::createSale($student->id, ['service_id' => $serviceId, 'amount' => '50000', 'sold_on' => $startsOn], $actor->id);
    EducationFinance::createPayment($student->id, ['sale_id' => $saleId, 'kassa_id' => $kassaId, 'amount' => '20000', 'paid_on' => $startsOn . 'T09:00'], $actor->id);
    $totals = EducationFinance::totals($student->id);
    stageThreeCheck((float) $totals['sold_total'] === 50000.0 && (float) $totals['paid_total'] === 20000.0 && (float) $totals['debt_total'] === 30000.0, 'Installment payment totals are incorrect.');
    $overpaymentRejected = false;
    try {
        EducationFinance::createPayment($student->id, ['sale_id' => $saleId, 'kassa_id' => $kassaId, 'amount' => '40000', 'paid_on' => $startsOn . 'T10:00'], $actor->id);
    } catch (RuntimeException) {
        $overpaymentRejected = true;
    }
    stageThreeCheck($overpaymentRejected, 'Payment exceeding the remaining sale balance was accepted.');

    $contractId = Contract::create([
        'student_id' => $student->id,
        'contract_number' => '',
        'registration_number' => 'REG-' . $suffix,
        'counterparty' => $student->fullName(),
        'client_phone' => $student->phone,
        'subject' => 'Обучение в автошколе',
        'direction' => 'driving_school',
        'signed_on' => $startsOn,
        'starts_on' => $startsOn,
        'ends_on' => '2098-06-01',
        'amount' => '50000',
        'status' => 'active',
    ], $actor->id);
    stageThreeCheck((int) (Contract::find($contractId)['student_id'] ?? 0) === $student->id, 'Contract was not linked to the student.');

    $calendar = SchoolCalendar::month($teacher, '2098-01');
    $calendarEvents = array_merge(...array_values($calendar['events'] ?: [[]]));
    stageThreeCheck(count($calendarEvents) > 0, 'Teacher calendar did not include assigned lessons.');
    stageThreeCheck(count(array_filter($calendarEvents, static fn (array $event): bool => (int) $event['teacher_id'] !== $teacher->id)) === 0, 'Teacher calendar leaked another teacher schedule.');

    echo "PASS stage-three-classes-students-payments-calendar\n";
} finally {
    $pdo->rollBack();
}
