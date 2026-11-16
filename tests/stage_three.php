<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Contract;
use App\Models\EducationFinance;
use App\Models\EducationCatalog;
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

    $balanceTypeId = (int) $pdo->query('SELECT id FROM student_balance_types ORDER BY id LIMIT 1')->fetchColumn();
    stageThreeCheck($balanceTypeId > 0, 'A balance type is required.');
    $serviceId = EducationFinance::saveService(['name' => 'Курс B ' . $suffix, 'price' => '50000', 'status' => 'active', 'allocations' => [['amount' => '20000', 'balances' => [$balanceTypeId => '6']]]], $actor->id);
    $savedDetails = json_decode(EducationFinance::service($serviceId)['service_data'], true);
    stageThreeCheck((float) $savedDetails['allocations'][0]['balances'][$balanceTypeId] === 6.0, 'Service balance hours were not saved.');
    $missingThresholdRejected = false;
    try {
        EducationFinance::saveService(['name' => 'Курс B ' . $suffix, 'price' => '50000', 'allocations' => [['amount' => '', 'balances' => [$balanceTypeId => '6']]]], $actor->id, $serviceId);
    } catch (RuntimeException $exception) {
        $missingThresholdRejected = true;
    }
    stageThreeCheck($missingThresholdRejected, 'Balance hours without a payment threshold were silently discarded.');
    $kassaId = EducationFinance::saveKassa(['name' => 'Тестовая касса ' . $suffix, 'status' => 'active'], $actor->id);
    $saleId = EducationFinance::createSale($student->id, ['contract_id' => $contractId, 'service_id' => $serviceId, 'amount' => '50000', 'sold_on' => $startsOn], $actor->id);
    $progress = EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId];
    stageThreeCheck($progress['assigned'] === 6.0 && $progress['paid'] === 0.0, 'Hours were not assigned at sale before payment.');
    $invalidSaleRejected = false;
    try { EducationFinance::createSale($student->id, ['contract_id' => 999999999, 'service_id' => $serviceId, 'amount' => '50000'], $actor->id); } catch (RuntimeException) { $invalidSaleRejected = true; }
    stageThreeCheck($invalidSaleRejected, 'Sale accepted a contract belonging to another student or a nonexistent contract.');
    EducationFinance::saveService(['name' => 'Курс B ' . $suffix, 'price' => '50000', 'status' => 'active', 'allocations' => [['amount' => '20000', 'balances' => [$balanceTypeId => '12']]]], $actor->id, $serviceId);
    stageThreeCheck(EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId]['assigned'] === 6.0, 'Editing a service changed the sold allocation snapshot.');
    EducationFinance::changeUnpaidSale($student->id, $saleId, ['amount' => '50000', 'sold_on' => $startsOn, 'notes' => 'Updated']);
    stageThreeCheck(EducationFinance::sales($student->id)[0]['notes'] === 'Updated', 'Unpaid sale was not updated.');
    $removableSale = EducationFinance::createSale($student->id, ['contract_id' => $contractId, 'service_id' => $serviceId, 'amount' => '50000', 'sold_on' => $startsOn], $actor->id);
    EducationFinance::changeUnpaidSale($student->id, $removableSale, null);
    stageThreeCheck(count(EducationFinance::sales($student->id)) === 1, 'Unpaid sale was not removed.');
    EducationFinance::createPayment($student->id, ['sale_id' => $saleId, 'kassa_id' => $kassaId, 'amount' => '20000', 'paid_on' => $startsOn . 'T09:00'], $actor->id);
    foreach ([null, ['amount' => '50000', 'sold_on' => $startsOn]] as $change) {
        $rejected = false;
        try { EducationFinance::changeUnpaidSale($student->id, $saleId, $change); } catch (RuntimeException $exception) { $rejected = true; }
        stageThreeCheck($rejected, 'A sale with a payment was changed.');
    }
    $progress = EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId];
    stageThreeCheck($progress['assigned'] === 6.0 && $progress['paid'] === 6.0, 'Payment did not update paid hours.');
    EducationFinance::setContractManualUsage($student->id, $contractId, $balanceTypeId, '2.5', $actor->id);
    $progress = EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId];
    stageThreeCheck($progress['manual'] === 2.5, 'Manual contract progress was not saved.');
    $rejected = false;
    try { EducationFinance::setContractManualUsage($teacher->id, $contractId, $balanceTypeId, '1', $actor->id); } catch (RuntimeException) { $rejected = true; }
    stageThreeCheck($rejected, 'Progress from another student was accepted.');
    StudyClass::setDefaultBalanceType($classId, $balanceTypeId, $actor->id);
    $futureRejected = false;
    try { StudyClass::saveAttendance($classId, (int) $lessons[0]['id'], [$student->id => $contractId], $actor->id); } catch (RuntimeException) { $futureRejected = true; }
    stageThreeCheck($futureRejected, 'Attendance was recorded before a lesson ended.');
    $pdo->prepare('UPDATE class_enrollments SET joined_on = ? WHERE class_id = ? AND student_id = ?')->execute(['2000-01-01', $classId, $student->id]);
    $pdo->prepare('INSERT INTO class_lessons (class_id, teacher_id, title, starts_at, ends_at) VALUES (?, ?, ?, ?, ?)')->execute([$classId, $teacher->id, 'Проведённое занятие', '2001-02-01 09:00:00', '2001-02-01 10:30:00']);
    $pastLessonId = (int) $pdo->lastInsertId();
    StudyClass::saveAttendance($classId, $pastLessonId, [$student->id => $contractId], $actor->id);
    StudyClass::saveAttendance($classId, $pastLessonId, [$student->id => $contractId], $actor->id);
    $progress = EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId];
    stageThreeCheck($progress['automatic'] === 1.5 && $progress['manual'] === 2.5, 'Attendance was not recorded once and combined with manual progress.');
    $countType = EducationCatalog::saveBalanceType(['name' => 'Занятия ' . $suffix, 'code' => 'У-' . $suffix, 'unit' => 'count', 'color' => '#4768db'], $actor->id);
    StudyClass::setDefaultBalanceType($classId, $countType, $actor->id);
    StudyClass::saveAttendance($classId, $pastLessonId, [$student->id => $contractId], $actor->id);
    stageThreeCheck(EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId]['automatic'] === 1.5, 'Changing the class default rewrote past attendance.');
    StudyClass::setDefaultBalanceType($classId, $balanceTypeId, $actor->id);
    $wrongContractRejected = false;
    try { StudyClass::saveAttendance($classId, $pastLessonId, [$student->id => 999999999], $actor->id); } catch (RuntimeException) { $wrongContractRejected = true; }
    stageThreeCheck($wrongContractRejected && EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId]['automatic'] === 1.5, 'Invalid attendance changed existing progress.');

    StudyClass::generateSchedule($classId);
    stageThreeCheck(isset(StudyClass::attendanceForClass($classId)[$pastLessonId][$student->id]), 'Schedule regeneration removed attended lessons.');
    StudyClass::saveAttendance($classId, $pastLessonId, [$student->id => 0], $actor->id);
    stageThreeCheck(EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId]['automatic'] === 0.0, 'Cleared attendance still counted.');
    $currentService = EducationFinance::service($serviceId);
    $pdo->prepare('INSERT INTO student_sales (student_id, service_id, service_name, service_data, amount, sold_on, sold_by) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$student->id, $serviceId, $currentService['name'], $currentService['service_data'], '10000', $startsOn, $actor->id]);
    $legacySale = (int) $pdo->lastInsertId();
    EducationFinance::linkLegacySaleToContract($student->id, $legacySale, $contractId);
    stageThreeCheck(EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId]['assigned'] === 18.0, 'Linking a historical sale did not assign its snapshot to the contract.');
    $relinkRejected = false;
    try { EducationFinance::linkLegacySaleToContract($student->id, $legacySale, $contractId); } catch (RuntimeException) { $relinkRejected = true; }
    stageThreeCheck($relinkRejected, 'A linked sale was linked a second time.');
    EducationFinance::changeUnpaidSale($student->id, $legacySale, null);


    $totals = EducationFinance::totals($student->id);
    stageThreeCheck((float) $totals['sold_total'] === 50000.0 && (float) $totals['paid_total'] === 20000.0 && (float) $totals['debt_total'] === 30000.0, 'Installment payment totals are incorrect.');
    $overpaymentRejected = false;
    try {
        EducationFinance::createPayment($student->id, ['sale_id' => $saleId, 'kassa_id' => $kassaId, 'amount' => '40000', 'paid_on' => $startsOn . 'T10:00'], $actor->id);
    } catch (RuntimeException) {
        $overpaymentRejected = true;
    }
    stageThreeCheck($overpaymentRejected, 'Payment exceeding the remaining sale balance was accepted.');


    $calendar = SchoolCalendar::month($teacher, '2098-01');
    $calendarEvents = array_merge(...array_values($calendar['events'] ?: [[]]));
    stageThreeCheck(count($calendarEvents) > 0, 'Teacher calendar did not include assigned lessons.');
    stageThreeCheck(count(array_filter($calendarEvents, static fn (array $event): bool => (int) $event['teacher_id'] !== $teacher->id)) === 0, 'Teacher calendar leaked another teacher schedule.');

    $discountedSale = EducationFinance::createSale($student->id, ['contract_id' => $contractId, 'service_id' => $serviceId, 'amount' => '10000', 'sold_on' => $startsOn], $actor->id);
    EducationFinance::createPayment($student->id, ['sale_id' => $discountedSale, 'kassa_id' => $kassaId, 'amount' => '10000', 'paid_on' => $startsOn . 'T10:00'], $actor->id);
    $discountedProgress = EducationFinance::contractBalanceProgress($student->id)[0]['balances'][$balanceTypeId];
    stageThreeCheck($discountedProgress['assigned'] === 18.0 && $discountedProgress['paid'] === 18.0, 'Fully paid discounted sale did not count all purchased hours.');

    echo "PASS stage-three-classes-students-payments-calendar\n";
} finally {
    $pdo->rollBack();
}
