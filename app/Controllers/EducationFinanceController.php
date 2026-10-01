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
        View::render('education/finance/services', [
            'pageTitle' => 'Услуги',
            'pageEyebrow' => 'Продажи курсантам',
            'currentUser' => $user,
            'services' => EducationFinance::services(),
        ]);
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
        Response::redirect('/education/services');
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
        Response::redirect('/education/services');
    }
}
