<?php

namespace App\Controllers\Diocese;

use App\Controllers\BaseController;
use App\Controllers\EmailSettingsTrait;

/**
 * Diocese-level email settings.
 *
 * General settings can be maintained by diocesan admins; SMTP credentials
 * are additionally restricted to primary diocesan administrators (or system
 * administrators).
 */
class EmailSettingsController extends BaseController
{
    use EmailSettingsTrait;

    public function index(int $dioceseId = 0)
    {
        if ($dioceseId <= 0) {
            $dioceseId = (int) ($this->request->getGet('diocese_id') ?? 0);
        }
        if ($dioceseId <= 0) {
            $profile = $this->getProfile();
            $dioceseId = (int) ($profile['diocese_id'] ?? 0);
        }

        $canEditSmtp = $this->canEditSmtp();

        return $this->emailSettingsIndex('diocese', $dioceseId, 'diocese.admin', $canEditSmtp, base_url('diocesan/dashboard'), lang('Email.dioceseSettingsTitle'));
    }

    public function save(int $dioceseId = 0)
    {
        if ($dioceseId <= 0) {
            $dioceseId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        if ($dioceseId <= 0) {
            $profile = $this->getProfile();
            $dioceseId = (int) ($profile['diocese_id'] ?? 0);
        }

        $canEditSmtp = $this->canEditSmtp();

        return $this->emailSettingsSave('diocese', $dioceseId, 'diocese.admin', $canEditSmtp, base_url('diocesan/dashboard'), lang('Email.dioceseSettingsTitle'));
    }

    public function test(int $dioceseId = 0)
    {
        if ($dioceseId <= 0) {
            $dioceseId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        if ($dioceseId <= 0) {
            $profile = $this->getProfile();
            $dioceseId = (int) ($profile['diocese_id'] ?? 0);
        }

        $canEditSmtp = $this->canEditSmtp();

        return $this->emailSettingsTest('diocese', $dioceseId, 'diocese.admin', $canEditSmtp);
    }

    private function canEditSmtp(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->can('diocese.primary') || $user->can('system.admin');
    }
}
