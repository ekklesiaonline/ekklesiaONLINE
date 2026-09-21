<?php

namespace App\Controllers\Province;

use App\Controllers\BaseController;
use App\Controllers\EmailSettingsTrait;

/**
 * Province-level email settings.
 */
class EmailSettingsController extends BaseController
{
    use EmailSettingsTrait;

    public function index(int $provinceId = 0)
    {
        if ($provinceId <= 0) {
            $provinceId = (int) ($this->request->getGet('province_id') ?? 0);
        }
        if ($provinceId <= 0) {
            $profile = $this->getProfile();
            $provinceId = (int) ($profile['province_id'] ?? 0);
        }

        return $this->emailSettingsIndex('province', $provinceId, 'province.admin', true, base_url('province/dashboard'), lang('Email.provinceSettingsTitle'));
    }

    public function save(int $provinceId = 0)
    {
        if ($provinceId <= 0) {
            $provinceId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        if ($provinceId <= 0) {
            $profile = $this->getProfile();
            $provinceId = (int) ($profile['province_id'] ?? 0);
        }

        return $this->emailSettingsSave('province', $provinceId, 'province.admin', true, base_url('province/dashboard'), lang('Email.provinceSettingsTitle'));
    }

    public function test(int $provinceId = 0)
    {
        if ($provinceId <= 0) {
            $provinceId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        if ($provinceId <= 0) {
            $profile = $this->getProfile();
            $provinceId = (int) ($profile['province_id'] ?? 0);
        }

        return $this->emailSettingsTest('province', $provinceId, 'province.admin', true);
    }
}
