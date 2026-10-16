<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\EducationCatalog;
use PDOException;
use RuntimeException;

final class EducationCatalogController
{
    public function createClassFolder(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $id = EducationCatalog::saveClassFolder($_POST, $user->id);
            ActivityLog::record('class_folder.created', 'Создана папка учебных классов', $user, [], 'class_folder', $id, [
                'name' => trim((string) ($_POST['name'] ?? '')),
            ]);
            Flash::set('success', 'Папка учебных классов создана.');
            Response::redirect('/education/classes?folder_id=' . $id);
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Папка с таким названием уже существует.' : 'Не удалось создать папку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/classes');
    }

    public function updateClassFolder(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            if (!EducationCatalog::findClassFolder((int) $id)) {
                throw new RuntimeException('Папка учебных классов не найдена.');
            }
            EducationCatalog::saveClassFolder($_POST, $user->id, (int) $id);
            Flash::set('success', 'Папка сохранена.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Папка с таким названием уже существует.' : 'Не удалось сохранить папку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/classes?folder_id=' . (int) $id);
    }

    public function programs(): void
    {
        $user = Auth::requireEducationManager();
        View::render('education/programs/index', [
            'pageTitle' => 'Учебные программы',
            'pageEyebrow' => 'Справочники обучения',
            'currentUser' => $user,
            'folders' => EducationCatalog::programFolders(),
        ]);
    }

    public function createProgramFolder(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            EducationCatalog::saveProgramFolder($_POST, $user->id);
            Flash::set('success', 'Папка учебных программ создана.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Папка с таким названием уже существует.' : 'Не удалось создать папку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/programs');
    }

    public function updateProgramFolder(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            EducationCatalog::saveProgramFolder($_POST, $user->id, (int) $id);
            Flash::set('success', 'Папка учебных программ сохранена.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Папка с таким названием уже существует.' : 'Не удалось сохранить папку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/programs');
    }

    public function createProgram(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $id = EducationCatalog::saveProgram($_POST, $user->id);
            ActivityLog::record('training_program.created', 'Создана учебная программа', $user, [], 'training_program', $id, [
                'name' => trim((string) ($_POST['name'] ?? '')),
            ]);
            Flash::set('success', 'Учебная программа добавлена.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Такая программа уже есть в выбранной папке.' : 'Не удалось добавить программу.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/programs');
    }

    public function updateProgram(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            if (!EducationCatalog::findProgram((int) $id)) {
                throw new RuntimeException('Учебная программа не найдена.');
            }
            EducationCatalog::saveProgram($_POST, $user->id, (int) $id);
            Flash::set('success', 'Учебная программа сохранена.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Такая программа уже есть в выбранной папке.' : 'Не удалось сохранить программу.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/programs');
    }

    public function checkpoints(): void
    {
        $user = Auth::requireEducationManager();
        View::render('education/checkpoints/index', [
            'pageTitle' => 'Контрольные точки обучения',
            'pageEyebrow' => 'Справочники обучения',
            'currentUser' => $user,
            'checkpoints' => EducationCatalog::checkpoints(),
        ]);
    }

    public function createCheckpoint(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $id = EducationCatalog::saveCheckpoint($_POST, $user->id);
            ActivityLog::record('checkpoint.created', 'Создана контрольная точка обучения', $user, [], 'checkpoint', $id, [
                'name' => trim((string) ($_POST['name'] ?? '')),
            ]);
            Flash::set('success', 'Контрольная точка добавлена.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Такая контрольная точка уже существует.' : 'Не удалось добавить контрольную точку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/checkpoints');
    }

    public function updateCheckpoint(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            if (!EducationCatalog::findCheckpoint((int) $id)) {
                throw new RuntimeException('Контрольная точка не найдена.');
            }
            EducationCatalog::saveCheckpoint($_POST, $user->id, (int) $id);
            Flash::set('success', 'Контрольная точка сохранена.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Такая контрольная точка уже существует.' : 'Не удалось сохранить контрольную точку.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/checkpoints');
    }

    public function balanceTypes(): void
    {
        $user = Auth::requireEducationManager();
        View::render('education/balance-types/index', [
            'pageTitle' => 'Типы балансов курсанта',
            'pageEyebrow' => 'Справочники обучения',
            'currentUser' => $user,
            'types' => EducationCatalog::balanceTypes(),
            'units' => EducationCatalog::BALANCE_UNITS,
        ]);
    }

    public function createBalanceType(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $id = EducationCatalog::saveBalanceType($_POST, $user->id);
            ActivityLog::record('balance_type.created', 'Создан тип баланса курсанта', $user, [], 'balance_type', $id, [
                'name' => trim((string) ($_POST['name'] ?? '')),
            ]);
            Flash::set('success', 'Тип баланса добавлен.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Такой тип баланса уже существует.' : 'Не удалось добавить тип баланса.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/balance-types');
    }

    public function updateBalanceType(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            if (!EducationCatalog::findBalanceType((int) $id)) {
                throw new RuntimeException('Тип баланса не найден.');
            }
            EducationCatalog::saveBalanceType($_POST, $user->id, (int) $id);
            Flash::set('success', 'Тип баланса сохранён.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Такой тип баланса уже существует.' : 'Не удалось сохранить тип баланса.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/balance-types');
    }
}
