<?php

namespace App\Controllers\Parish;

use App\Controllers\BaseController;
use App\Controllers\EmailSettingsTrait;

/**
 * Parish-level email settings.
 *
 * Parish admins can configure sender identity; SMTP credential editing is
 * restricted to the parish primary admin.
 */
class EmailSettingsController extends BaseController
{
    use EmailSettingsTrait;

    public function index()
    {
        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return redirect()->to('/profile')->with('error', lang('App.noParishSelected'));
        }

        return $this->emailSettingsIndex('parish', $parishId, 'parish.edit', $this->canEditSmtp($parishId), base_url('parish/dashboard'), lang('Email.parishSettingsTitle'));
    }

    public function save()
    {
        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return redirect()->to('/profile')->with('error', lang('App.noParishSelected'));
        }

        return $this->emailSettingsSave('parish', $parishId, 'parish.edit', $this->canEditSmtp($parishId), base_url('parish/dashboard'), lang('Email.parishSettingsTitle'));
    }

    public function test()
    {
        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return $this->response->setJSON([
                'success' => false,
                'status'  => 'error',
                'message' => lang('App.noParishSelected'),
                'error'   => ['code' => 403, 'message' => lang('App.noParishSelected')],
                'meta'    => ['http_status' => 403, 'ts' => date('c')],
            ])->setStatusCode(403);
        }

        return $this->emailSettingsTest('parish', $parishId, 'parish.edit', $this->canEditSmtp($parishId));
    }

    private function canEditSmtp(int $parishId): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->can('parish.primary') || $user->can('system.admin');
    }
}
