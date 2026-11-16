<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\EducationFinance;
use App\Models\EducationCatalog;
use PDOException;
use RuntimeException;

final class EducationFinanceController
{
    public function kassas(): void
    {
        $user = Auth::requireKassaManager();
        View::render('education/finance/kassas', [
            'pageTitle' => 'Кассы',
            'pageEyebrow' => 'Точки приёма оплаты',
            'currentUser' => $user,
            'kassas' => EducationFinance::kassas(),
        ]);
    }

    public function createKassa(): void
    {
        $user = Auth::requireKassaManager();
        Csrf::enforce();
        try {
            $id = EducationFinance::saveKassa($_POST, $user->id);
            ActivityLog::record('kassa.created', 'Создана касса', $user, [], 'kassa', $id, ['name' => trim((string) ($_POST['name'] ?? ''))]);
            Flash::set('success', 'Касса добавлена.');
        } catch (PDOException $exception) {
            Flash::set('error', (string) $exception->getCode() === '23000' ? 'Касса с таким названием уже существует.' : 'Не удалось сохранить кассу.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/kassas');
    }

    public function updateKassa(string $id): void
    {
        $user = Auth::requireKassaManager();
        Csrf::enforce();
        try {
            EducationFinance::saveKassa($_POST, $user->id, (int) $id);
            ActivityLog::record('kassa.updated', 'Обновлена касса', $user, [], 'kassa', (int) $id, ['name' => trim((string) ($_POST['name'] ?? ''))]);
            Flash::set('success', 'Касса обновлена.');
        } catch (RuntimeException|PDOException $exception) {
            Flash::set('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Не удалось обновить кассу.');
        }
        Response::redirect('/education/kassas');
    }

    public function services(): void
    {
        $user = Auth::requireEducationManager();
        $folders = EducationFinance::serviceFolders();
        $folderIds = array_map(static fn (array $folder): int => (int) $folder['id'], $folders);
        $selectedFolderId = (int) ($_GET['folder_id'] ?? 0);
        if ($selectedFolderId && !in_array($selectedFolderId, $folderIds, true)) $selectedFolderId = 0;
        View::render('education/finance/services', [
            'pageTitle' => 'Услуги',
            'pageEyebrow' => 'Продажи курсантам',
            'currentUser' => $user,
            'services' => EducationFinance::services(),
            'folders' => $folders,
            'selectedFolderId' => $selectedFolderId,
            'balanceTypes' => EducationCatalog::balanceTypes(),
        ]);
    }

    public function createServiceFolder(): void
    {
        Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $id = EducationFinance::createServiceFolder($_POST);
            Flash::set('success', 'Папка добавлена.');
            Response::redirect('/education/services?folder_id=' . $id);
        } catch (RuntimeException|PDOException $exception) {
            Flash::set('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Не удалось создать папку.');
        }
        Response::redirect('/education/services');
    }

    public function moveServiceFolder(string $id): void
    {
        Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $parentId = (int) ($_POST['parent_id'] ?? 0);
            EducationFinance::moveServiceFolder((int) $id, $parentId ?: null);
            Flash::set('success', 'Папка перемещена.');
        } catch (RuntimeException|PDOException $exception) {
            Flash::set('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Не удалось переместить папку.');
        }
        Response::redirect('/education/services?folder_id=' . (int) $id);
    }

    public function moveService(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        try {
            EducationFinance::moveService((int) $id, $folderId ?: null, $user->id);
            Flash::set('success', 'Услуга перемещена.');
        } catch (RuntimeException|PDOException $exception) {
            Flash::set('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Не удалось переместить услугу.');
        }
        Response::redirect('/education/services?folder_id=' . $folderId);
    }

    public function createService(): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            $id = EducationFinance::saveService($_POST, $user->id);
            ActivityLog::record('service.created', 'Создана услуга', $user, [], 'service', $id, ['name' => trim((string) ($_POST['name'] ?? ''))]);
            Flash::set('success', 'Услуга добавлена.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/services?folder_id=' . (int) ($_POST['folder_id'] ?? 0));
    }

    public function updateService(string $id): void
    {
        $user = Auth::requireEducationManager();
        Csrf::enforce();
        try {
            EducationFinance::saveService($_POST, $user->id, (int) $id);
            ActivityLog::record('service.updated', 'Обновлена услуга', $user, [], 'service', (int) $id, ['name' => trim((string) ($_POST['name'] ?? ''))]);
            Flash::set('success', 'Услуга обновлена.');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::redirect('/education/services?folder_id=' . (int) ($_POST['folder_id'] ?? 0));
    }
}
