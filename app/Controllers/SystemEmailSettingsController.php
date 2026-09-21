<?php

namespace App\Controllers;

use App\Controllers\EmailSettingsTrait;

/**
 * System-level email settings.
 *
 * Configures the default sender identity and SMTP fallback used when a
 * tenant tier does not override them. Restricted to system administrators.
 */
class SystemEmailSettingsController extends BaseController
{
    use EmailSettingsTrait;

    public function index()
    {
        return $this->emailSettingsIndex('system', null, 'system.admin', true, base_url('system/settings'), lang('Email.systemSettingsTitle'));
    }

    public function save()
    {
        return $this->emailSettingsSave('system', null, 'system.admin', true, base_url('system/settings'), lang('Email.systemSettingsTitle'));
    }

    public function test()
    {
        return $this->emailSettingsTest('system', null, 'system.admin', true);
    }
}
