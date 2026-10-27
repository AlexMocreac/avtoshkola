<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\EducationFinance;
use App\Models\EducationCatalog;
use App\Models\SchoolCalendar;
use App\Models\StudentFile;
use App\Models\StudentProfile;
use App\Models\StudyClass;
use App\Models\User;
use PDOException;
use RuntimeException;

final class EducationController
{
    public function classes(): void
    {
        $user = Auth::requireEducationAccess();
        $folders = EducationCatalog::classFolders($user);
        $folderIds = array_map(static fn (array $folder): int => (int) $folder['id'], $folders);
        $allClasses = StudyClass::all($user);
        $classesByFolder = [];
        $classMap = [];
        foreach ($allClasses as $class) {
            $folderId = (int) $class['folder_id'];
            $classesByFolder[$folderId][] = $class;
            $classMap[(int) $class['id']] = $class;
        }

        $selectedClassId = (int) ($_GET['class_id'] ?? 0);
        $selectedClass = $classMap[$selectedClassId] ?? null;
        if (!$selectedClass) {
            $selectedClassId = 0;
        }
        $selectedFolderId = (int) ($_GET['folder_id'] ?? 0);
        if ($selectedClass) {
            $selectedFolderId = (int) $selectedClass['folder_id'];
        }
        if (!in_array($selectedFolderId, $folderIds, true)) {
            $selectedFolderId = $folderIds[0] ?? 0;
            $selectedClass = null;
            $selectedClassId = 0;
        }

        $classes = $classesByFolder[$selectedFolderId] ?? [];
        $studentIds = [];
        if ($selectedClass) {
            $selectedClass['students'] = StudyClass::students($selectedClassId);
            foreach ($selectedClass['students'] as $student) {
                $studentIds[] = (int) $student['id'];
            }
        } else {
            foreach ($classes as &$class) {
                $class['students'] = StudyClass::students((int) $class['id']);
                foreach ($class['students'] as $student) {
                    $studentIds[] = (int) $student['id'];
                }
            }
            unset($class);
        }

        View::render('education/classes/index', [
            'pageTitle' => 'Учебные классы',
            'pageEyebrow' => 'Курсанты и группы',
            'currentUser' => $user,
            'classes' => $classes,
            'allClasses' => $allClasses,
            'classesByFolder' => $classesByFolder,
            'folders' => $folders,
            'selectedFolderId' => $selectedFolderId,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'availableStudents' => $selectedClass && $user->canManageEducation() ? StudyClass::availableStudents() : [],
            'programs' => EducationCatalog::activePrograms(),
            'checkpoints' => EducationCatalog::checkpoints(true),
            'checkpointStates' => EducationCatalog::checkpointStateMap($studentIds),
            'balanceTypes' => EducationCatalog::balanceTypes(true),
            'balanceValues' => EducationCatalog::balanceValueMap($studentIds),
            'teachers' => User::teachers(),
            'weekdays' => StudyClass::WEEKDAYS,
            'statuses' => StudyClass::STATUSES,
        ]);
    }

    public function createClassStudent(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce(true);
        $class = $this->classOrFail((int) $id, $user);
        $data = $_POST;
        $data['role'] = User::ROLE_STUDENT;
        $errors = Validator::user($data, true);
        if ($errors) {
            Response::json(['ok' => false, 'message' => 'Проверьте заполнение полей.', 'errors' => $errors], 422);
        }
        $contractData = [
            'contract_number' => $data['contract_number'] ?? '',
            'registration_number' => $data['registration_number'] ?? '',
            'counterparty' => trim((string) ($data['last_name'] ?? '') . ' ' . (string) ($data['first_name'] ?? '') . ' ' . (string) ($data['middle_name'] ?? '')),
            'client_phone' => $data['phone'] ?? '',
            'subject' => 'Обучение в автошколе',
            'direction' => 'driving_school',
            'signed_on' => $data['signed_on'] ?? '',
            'starts_on' => $data['joined_on'] ?? '',
            'ends_on' => $data['training_end_on'] ?? '',
            'amount' => $data['contract_amount'] ?? '',
            'status' => 'active',
            'notes' => $data['notes'] ?? '',
        ];
        $contractErrors = Validator::contract($contractData);
        if ($contractErrors) {
            Response::json(['ok' => false, 'message' => implode(' ', $contractErrors)], 422);
        }

        $pdo = Database::connection();
        $photoPath = null;
        $pdo->beginTransaction();
        try {
            $student = User::create($data, $user->id);
            StudentProfile::ensure($student->id);
            StudentProfile::update($student->id, $data);
            StudyClass::enroll((int) $id, $student->id, $user->id, $_POST['joined_on'] ?? null);
            $contractData['student_id'] = $student->id;
            $contractId = Contract::create($contractData, $user->id);
            $photoPath = StudentFile::storePhoto($student->id, $_FILES['photo'] ?? null);
            if ($photoPath !== null) {
                StudentProfile::setPhoto($student->id, $photoPath);
            }
            $contract = Contract::find($contractId);
            ActivityLog::record('contract.created', 'Создан договор курсанта', $user, [$student], 'contract', $contractId, [
                'number' => $contract['contract_number'] ?? '',
                'counterparty' => $contractData['counterparty'],
            ]);
            ActivityLog::record('student.created_and_enrolled', 'Создан курсант и зачислен в учебный класс', $user, [$student], 'user', $student->id, [
                'name' => $student->fullName(),
                'role_key' => User::ROLE_STUDENT,
                'class' => $class['group_number'],
                'contract_id' => $contractId,
            ]);
            $pdo->commit();
            Response::json([
                'ok' => true,
                'message' => 'Курсант, договор и зачисление в класс ' . $class['group_number'] . ' созданы.',
                'redirect' => url('/education/classes?folder_id=' . (int) $class['folder_id'] . '&class_id=' . (int) $id),
            ], 201);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($photoPath !== null) {
                $absolutePhotoPath = StudentFile::absolutePath($photoPath);
                if ($absolutePhotoPath !== null) {
                    @unlink($absolutePhotoPath);
                }
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                Response::json([
                    'ok' => false,
                    'message' => 'Логин, email или номер договора уже используется.',
                    'errors' => [
                        'login' => 'Проверьте уникальность логина.',
                        'email' => 'Проверьте уникальность email.',
                        'contract_number' => 'Проверьте уникальность номера договора.',
                    ],
                ], 422);
            }
            if ($exception instanceof RuntimeException) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            throw $exception;
        }
    }

    public function createClass(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $id = StudyClass::create($_POST, $user->id);
            $class = StudyClass::find($id);
            ActivityLog::record('class.created', 'Создан учебный класс', $user, [], 'class', $id, [
                'name' => $class['name'] ?? '',
                'group_number' => $class['group_number'] ?? '',
            ]);
            Flash::set('success', 'Учебный класс создан, расписание сформировано автоматически.');
            Response::redirect('/education/classes/' . $id);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                Flash::set('error', 'Класс с таким номером уже существует.');
                Response::redirect('/education/classes');
            }
            throw $exception;
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
            Response::redirect('/education/classes');
        }
    }

    public function showClass(string $id): void
    {
        $user = Auth::requireEducationAccess();
        $class = $this->classOrFail((int) $id, $user);
        $students = StudyClass::students((int) $id);
        $studentIds = array_map(static fn (array $student): int => (int) $student['id'], $students);
        View::render('education/classes/show', [
            'pageTitle' => 'Класс ' . $class['group_number'],
            'pageEyebrow' => 'Учебный класс',
            'currentUser' => $user,
            'class' => $class,
            'patterns' => StudyClass::patterns((int) $id),
            'lessons' => StudyClass::lessons((int) $id),
            'students' => $students,
            'availableStudents' => StudyClass::availableStudents(),
            'teachers' => User::teachers(),
            'weekdays' => StudyClass::WEEKDAYS,
            'statuses' => StudyClass::STATUSES,
            'folders' => EducationCatalog::classFolders($user),
            'programs' => EducationCatalog::activePrograms(),
            'checkpoints' => EducationCatalog::checkpoints(true),
            'checkpointStates' => EducationCatalog::checkpointStateMap($studentIds),
            'balanceTypes' => EducationCatalog::balanceTypes(true),
            'balanceValues' => EducationCatalog::balanceValueMap($studentIds),
        ]);
    }

    public function updateClass(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $class = $this->classOrFail((int) $id, $user);
        try {
            StudyClass::update((int) $id, $_POST, $user->id);
            ActivityLog::record('class.updated', 'Обновлён учебный класс и расписание', $user, [], 'class', (int) $id, [
                'name' => trim((string) ($_POST['name'] ?? $class['name'])),
                'group_number' => trim((string) ($_POST['group_number'] ?? $class['group_number'])),
            ]);
            Flash::set('success', 'Данные класса сохранены, расписание пересчитано.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Класс с таким номером уже существует.' : 'Не удалось сохранить класс.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/classes/' . $id);
    }

    public function regenerateSchedule(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $class = $this->classOrFail((int) $id, $user);
        try {
            StudyClass::generateSchedule((int) $id);
            ActivityLog::record('class.schedule_regenerated', 'Пересчитано расписание класса', $user, [], 'class', (int) $id, [
                'name' => $class['name'], 'group_number' => $class['group_number'],
            ]);
            Flash::set('success', 'Расписание пересчитано с учётом выходных дней.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/classes/' . $id . '#schedule');
    }

    public function enrollStudent(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $class = $this->classOrFail((int) $id, $user);
        $student = User::find((int) ($_POST['student_id'] ?? 0));
        try {
            if (!$student || !$student->isStudent()) {
                throw new RuntimeException('Выберите курсанта.');
            }
            $previous = StudyClass::currentForStudent($student->id);
            StudyClass::enroll((int) $id, $student->id, $user->id, $_POST['joined_on'] ?? null);
            ActivityLog::record($previous ? 'student.transferred' : 'student.enrolled', $previous ? 'Курсант переведён в другой класс' : 'Курсант зачислен в класс', $user, [$student], 'user', $student->id, [
                'name' => $student->fullName(),
                'role_key' => User::ROLE_STUDENT,
                'changes' => [['field' => 'Учебный класс', 'from' => $previous['group_number'] ?? 'Без класса', 'to' => $class['group_number']]],
            ]);
            Flash::set('success', $previous ? 'Курсант переведён в выбранный класс.' : 'Курсант добавлен в класс.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/classes/' . $id . '#students');
    }

    public function roster(string $id): void
    {
        $user = Auth::requireEducationAccess();
        $class = $this->classOrFail((int) $id, $user);
        View::render('education/classes/roster', [
            'pageTitle' => 'Список класса ' . $class['group_number'],
            'currentUser' => $user,
            'class' => $class,
            'students' => StudyClass::students((int) $id),
        ], 'layouts/print');
    }

    public function students(): void
    {
        $user = Auth::requireEducationAccess();
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'class_id' => (int) ($_GET['class_id'] ?? 0),
            'training_status' => trim((string) ($_GET['training_status'] ?? '')),
        ];
        View::render('education/students/index', [
            'pageTitle' => 'Курсанты',
            'pageEyebrow' => 'Курсанты и группы',
            'currentUser' => $user,
            'students' => StudentProfile::all($user, $filters),
            'classes' => StudyClass::all($user),
            'statuses' => StudentProfile::TRAINING_STATUSES,
            'filters' => $filters,
        ]);
    }

    public function student(string $id): void
    {
        $user = Auth::requireLogin();
        $student = $this->studentOrFail((int) $id, $user);
        $class = StudyClass::currentForStudent((int) $id);
        $checkpoints = EducationCatalog::checkpoints(true);
        View::render('education/students/show', [
            'pageTitle' => trim($student['last_name'] . ' ' . $student['first_name']),
            'pageEyebrow' => 'Карточка курсанта',
            'currentUser' => $user,
            'student' => $student,
            'class' => $class,
            'classes' => $user->canManageEducation() ? StudyClass::all($user) : [],
            'contracts' => StudentProfile::contracts((int) $id),
            'documents' => StudentProfile::documents((int) $id),
            'files' => StudentFile::all((int) $id),
            'sales' => EducationFinance::sales((int) $id),
            'payments' => EducationFinance::payments((int) $id),
            'totals' => EducationFinance::totals((int) $id),
            'services' => EducationFinance::services(true),
            'kassas' => EducationFinance::kassas(true),
            'history' => StudentProfile::enrollmentHistory((int) $id),
            'events' => ActivityLog::forEntity('user', (int) $id, 50),
            'checkpoints' => $checkpoints,
            'checkpointStates' => EducationCatalog::checkpointStateMap([(int) $id])[(int) $id] ?? [],
            'balanceTypes' => EducationCatalog::balanceTypes(true),
            'balanceValues' => EducationCatalog::balanceValueMap([(int) $id])[(int) $id] ?? [],
        ]);
    }

    public function setCheckpoint(string $id, string $checkpointId): void
    {
        $user = Auth::requireEducationAccess();
        Csrf::enforce();
        $student = $this->studentOrFail((int) $id, $user);
        try {
            $completed = (string) ($_POST['completed'] ?? '0') === '1';
            EducationCatalog::setCheckpointState((int) $id, (int) $checkpointId, $completed, $user->id);
            $checkpoint = EducationCatalog::findCheckpoint((int) $checkpointId);
            ActivityLog::record('student.checkpoint_updated', $completed ? 'Контрольная точка пройдена' : 'Контрольная точка отменена', $user, [User::find((int) $id)], 'user', (int) $id, [
                'name' => trim($student['last_name'] . ' ' . $student['first_name']),
                'role_key' => User::ROLE_STUDENT,
                'checkpoint' => $checkpoint['name'] ?? '',
            ]);
            Flash::set('success', $completed ? 'Контрольная точка отмечена как пройденная.' : 'Отметка контрольной точки снята.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        $classId = (int) ($_POST['class_id'] ?? 0);
        Response::redirect($classId > 0 ? '/education/classes/' . $classId . '#students' : '/education/students/' . (int) $id . '#learning');
    }

    public function updateBalances(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $student = $this->studentOrFail((int) $id, $user);
        try {
            EducationCatalog::setStudentBalances((int) $id, (array) ($_POST['balances'] ?? []), $user->id);
            ActivityLog::record('student.balances_updated', 'Обновлены балансы курсанта', $user, [User::find((int) $id)], 'user', (int) $id, [
                'name' => trim($student['last_name'] . ' ' . $student['first_name']),
                'role_key' => User::ROLE_STUDENT,
            ]);
            Flash::set('success', 'Балансы курсанта сохранены.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/students/' . (int) $id . '#learning');
    }

    public function updateStudent(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $student = $this->studentOrFail((int) $id, $user);
        try {
            StudentProfile::update((int) $id, $_POST);
            $photo = StudentFile::storePhoto((int) $id, $_FILES['photo'] ?? null);
            if ($photo) {
                StudentProfile::setPhoto((int) $id, $photo);
            }
            $target = User::find((int) $id);
            ActivityLog::record('student.profile_updated', 'Обновлена карточка курсанта', $user, $target ? [$target] : [], 'user', (int) $id, [
                'name' => trim($student['last_name'] . ' ' . $student['first_name']), 'role_key' => User::ROLE_STUDENT,
            ]);
            Flash::set('success', 'Карточка курсанта сохранена.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        $activeTab = (string) ($_POST['active_tab'] ?? 'main') === 'personal' ? 'personal' : 'main';
        Response::redirect('/education/students/' . $id . '#' . $activeTab);
    }

    public function studentAction(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $student = $this->studentOrFail((int) $id, $user);
        $action = (string) ($_POST['action'] ?? '');
        try {
            StudentProfile::setAction((int) $id, $action);
            $labels = [
                'allow_driving' => 'Курсант допущен к вождению',
                'deny_driving' => 'Допуск к вождению отменён',
                'dismiss' => 'Курсант отчислен',
                'restore' => 'Курсант восстановлен',
                'complete' => 'Обучение курсанта завершено',
            ];
            $target = User::find((int) $id);
            ActivityLog::record('student.' . $action, $labels[$action] ?? 'Изменено состояние курсанта', $user, $target ? [$target] : [], 'user', (int) $id, [
                'name' => trim($student['last_name'] . ' ' . $student['first_name']), 'role_key' => User::ROLE_STUDENT,
            ]);
            Flash::set('success', $labels[$action] ?? 'Действие выполнено.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/students/' . $id);
    }

    public function transferStudent(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $student = $this->studentOrFail((int) $id, $user);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $class = StudyClass::find($classId);
        if (!$class) {
            Flash::set('error', 'Выбранный класс не найден.');
            Response::redirect('/education/students/' . $id);
        }
        $previous = StudyClass::currentForStudent((int) $id);
        StudyClass::enroll($classId, (int) $id, $user->id, $_POST['joined_on'] ?? null);
        $target = User::find((int) $id);
        ActivityLog::record('student.transferred', 'Курсант переведён в другой класс', $user, $target ? [$target] : [], 'user', (int) $id, [
            'name' => trim($student['last_name'] . ' ' . $student['first_name']),
            'role_key' => User::ROLE_STUDENT,
            'changes' => [['field' => 'Учебный класс', 'from' => $previous['group_number'] ?? 'Без класса', 'to' => $class['group_number']]],
        ]);
        Flash::set('success', 'Курсант переведён в класс ' . $class['group_number'] . '.');
        Response::redirect('/education/students/' . $id);
    }

    public function addDocument(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $this->studentOrFail((int) $id, $user);
        try {
            StudentProfile::addDocument((int) $id, $_POST, $user->id);
            ActivityLog::record('student.document_added', 'Добавлен документ курсанта', $user, [User::find((int) $id)], 'user', (int) $id, [
                'name' => trim((string) ($_POST['document_type'] ?? '')), 'role_key' => User::ROLE_STUDENT,
            ]);
            Flash::set('success', 'Документ добавлен в карточку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/students/' . $id . '#personal');
    }

    public function createContract(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $student = $this->studentOrFail((int) $id, $user);
        $data = $_POST + [
            'student_id' => (int) $id,
            'counterparty' => trim($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')),
            'client_phone' => $student['phone'] ?? '',
            'subject' => 'Обучение в автошколе',
            'direction' => 'driving_school',
            'status' => 'active',
        ];
        $data['student_id'] = (int) $id;
        $errors = Validator::contract($data);
        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            Response::redirect('/education/students/' . $id . '#contracts');
        }
        try {
            $contractId = Contract::create($data, $user->id);
            $contract = Contract::find($contractId);
            ActivityLog::record('contract.created', 'Создан договор курсанта', $user, [User::find((int) $id)], 'contract', $contractId, [
                'number' => $contract['contract_number'] ?? '', 'counterparty' => $data['counterparty'],
            ]);
            Flash::set('success', 'Договор создан и связан с карточкой курсанта.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Договор с таким номером уже существует.' : 'Не удалось создать договор.');
        }
        Response::redirect('/education/students/' . $id . '#contracts');
    }

    public function createSale(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $this->studentOrFail((int) $id, $user);
        try {
            $saleId = EducationFinance::createSale((int) $id, $_POST, $user->id);
            ActivityLog::record('student.service_sold', 'Курсанту продана услуга', $user, [User::find((int) $id)], 'user', (int) $id, [
                'name' => 'Продажа №' . $saleId, 'role_key' => User::ROLE_STUDENT,
            ]);
            Flash::set('success', 'Продажа услуги добавлена. Можно принять оплату полностью или частями.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/students/' . $id . '#payments');
    }

    public function createPayment(string $id): void
    {
        $user = Auth::requireLogin();
        if (!$user->canAcceptPayments()) {
            $this->deny($user, 'Недостаточно прав для принятия оплаты.');
        }
        Csrf::enforce();
        $this->studentOrFail((int) $id, $user);
        try {
            $paymentId = EducationFinance::createPayment((int) $id, $_POST, $user->id);
            ActivityLog::record('student.payment_received', 'Принята оплата от курсанта', $user, [User::find((int) $id)], 'user', (int) $id, [
                'name' => 'Платёж №' . $paymentId, 'role_key' => User::ROLE_STUDENT,
            ]);
            Flash::set('success', 'Оплата принята в выбранную кассу.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/students/' . $id . '#payments');
    }

    public function uploadFile(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $this->studentOrFail((int) $id, $user);
        try {
            StudentFile::store((int) $id, $user->id, $_FILES['file'] ?? null);
            ActivityLog::record('student.file_uploaded', 'Добавлен файл курсанта', $user, [User::find((int) $id)], 'user', (int) $id, [
                'name' => trim((string) ($_FILES['file']['name'] ?? 'Файл')), 'role_key' => User::ROLE_STUDENT,
            ]);
            Flash::set('success', 'Файл добавлен в карточку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/students/' . $id . '#files');
    }

    public function downloadFile(string $id): void
    {
        $user = Auth::requireLogin();
        $file = StudentFile::find((int) $id);
        if (!$file) {
            $this->deny($user, 'Файл недоступен.');
        }
        $this->studentOrFail((int) $file['student_id'], $user);
        $path = StudentFile::absolutePath((string) $file['stored_path']);
        if ($path === null) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="student-file"; filename*=UTF-8\'\'' . rawurlencode((string) $file['original_name']));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function photo(string $id): void
    {
        $user = Auth::requireLogin();
        $student = $this->studentOrFail((int) $id, $user);
        $path = !empty($student['photo_path']) ? StudentFile::absolutePath((string) $student['photo_path']) : null;
        if ($path === null) {
            http_response_code(404);
            exit;
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function printContract(string $id): void
    {
        $user = Auth::requireLogin();
        $student = $this->studentOrFail((int) $id, $user);
        $contracts = StudentProfile::contracts((int) $id);
        View::render('education/students/contract-print', [
            'pageTitle' => 'Договор курсанта',
            'currentUser' => $user,
            'student' => $student,
            'class' => StudyClass::currentForStudent((int) $id),
            'contract' => $contracts[0] ?? null,
        ], 'layouts/print');
    }

    public function holidays(): void
    {
        $user = Auth::requireEducationManager();
        $year = max(2000, min(2100, (int) ($_GET['year'] ?? date('Y'))));
        View::render('education/holidays/index', [
            'pageTitle' => 'Выходные дни',
            'pageEyebrow' => 'Настройка расписания',
            'currentUser' => $user,
            'year' => $year,
            'holidays' => SchoolCalendar::holidays($year),
        ]);
    }

    public function createHoliday(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            SchoolCalendar::createHoliday($_POST, $user->id);
            ActivityLog::record('holiday.created', 'Добавлен выходной день', $user, [], 'holiday', null, ['name' => trim((string) ($_POST['name'] ?? ''))]);
            Flash::set('success', 'Выходной добавлен. Расписания активных классов пересчитаны.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Эта дата уже отмечена как выходной день.' : 'Не удалось добавить выходной.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/holidays?year=' . (int) substr((string) ($_POST['holiday_date'] ?? date('Y')), 0, 4));
    }

    public function deleteHoliday(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        SchoolCalendar::deleteHoliday((int) $id);
        ActivityLog::record('holiday.deleted', 'Удалён выходной день, расписания пересчитаны', $user, [], 'holiday', (int) $id);
        Flash::set('success', 'Выходной удалён. Расписания активных классов пересчитаны.');
        Response::redirect('/education/holidays');
    }

    private function classOrFail(int $id, User $viewer): array
    {
        $class = StudyClass::find($id);
        if (!$class) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Класс не найден', 'currentUser' => $viewer]);
            exit;
        }
        if ($viewer->role === User::ROLE_TEACHER && (int) $class['teacher_id'] !== $viewer->id) {
            $this->deny($viewer, 'Этот учебный класс вам не назначен.');
        }
        return $class;
    }

    private function studentOrFail(int $id, User $viewer): array
    {
        $student = StudentProfile::find($id);
        if (!$student) {
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Курсант не найден', 'currentUser' => $viewer]);
            exit;
        }
        if (!$viewer->canViewStudent($id)) {
            $this->deny($viewer, 'Карточка курсанта недоступна.');
        }
        if ($viewer->role === User::ROLE_TEACHER) {
            $class = StudyClass::currentForStudent($id);
            if (!$class || (int) $class['teacher_id'] !== $viewer->id) {
                $this->deny($viewer, 'Курсант не состоит в назначенном вам классе.');
            }
        }
        return $student;
    }

    private function deny(User $user, string $message): never
    {
        http_response_code(403);
        View::render('errors/403', ['pageTitle' => 'Нет доступа', 'currentUser' => $user, 'message' => $message]);
        exit;
    }
}
