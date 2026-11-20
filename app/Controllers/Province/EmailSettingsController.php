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
        $provinceId = $this->guardProvinceScope($provinceId);
        if ($provinceId === null) {
            return redirect()->to(base_url('province/dashboard'))->with('error', lang('App.errorForbidden'));
        }

        return $this->emailSettingsIndex('province', $provinceId, 'province.admin', true, base_url('province/dashboard'), lang('Email.provinceSettingsTitle'));
    }

    public function save(int $provinceId = 0)
    {
        if ($provinceId <= 0) {
            $provinceId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        $provinceId = $this->guardProvinceScope($provinceId);
        if ($provinceId === null) {
            return redirect()->back()->with('error', lang('App.errorForbidden'));
        }

        return $this->emailSettingsSave('province', $provinceId, 'province.admin', true, base_url('province/dashboard'), lang('Email.provinceSettingsTitle'));
    }

    public function test(int $provinceId = 0)
    {
        if ($provinceId <= 0) {
            $provinceId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        $provinceId = $this->guardProvinceScope($provinceId);
        if ($provinceId === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorForbidden'), 403, 'SCOPE_FORBIDDEN'), 403)
                ->setHeader(config('Security')->headerName, csrf_hash());
        }

        return $this->emailSettingsTest('province', $provinceId, 'province.admin', true);
    }

    public function googleConnect(int $provinceId = 0)
    {
        if ($provinceId <= 0) {
            $provinceId = (int) ($this->request->getGet('province_id') ?? 0);
        }
        $provinceId = $this->guardProvinceScope($provinceId);
        if ($provinceId === null) {
            return redirect()->to(base_url('province/dashboard'))->with('error', lang('App.errorForbidden'));
        }

        return $this->gmailConnectRedirect(
            ['type' => 'province', 'id' => $provinceId],
            'province.admin',
            true,
            base_url('province/email-settings') . '?province_id=' . $provinceId
        );
    }

    public function googleDisconnect(int $provinceId = 0)
    {
        if ($provinceId <= 0) {
            $provinceId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        $provinceId = $this->guardProvinceScope($provinceId);
        if ($provinceId === null) {
            return redirect()->back()->with('error', lang('App.errorForbidden'));
        }

        return $this->gmailDisconnectRedirect(
            ['type' => 'province', 'id' => $provinceId],
            'province.admin',
            true,
            base_url('province/email-settings') . '?province_id=' . $provinceId
        );
    }

    /**
     * Resolve the province this admin may manage, rejecting any
     * request-supplied scope outside their own province. System admins may
     * address any province explicitly; everyone else is pinned to the
     * server-resolved admin province.
     */
    private function guardProvinceScope(int $provinceId): ?int
    {
        if ($this->isSystemAdmin()) {
            if ($provinceId <= 0) {
                $profile = $this->getProfile();
                $provinceId = (int) ($profile['province_id'] ?? 0);
            }

            return $provinceId > 0 ? $provinceId : null;
        }

        $scopeId = $this->resolveAdminProvinceId();
        if ($scopeId <= 0 || ($provinceId > 0 && $provinceId !== $scopeId)) {
            return null;
        }

        return $scopeId;
    }
}
