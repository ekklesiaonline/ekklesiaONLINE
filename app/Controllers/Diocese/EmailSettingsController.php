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
        $dioceseId = $this->guardDioceseScope($dioceseId);
        if ($dioceseId === null) {
            return redirect()->to(base_url('diocese/dashboard'))->with('error', lang('App.errorForbidden'));
        }

        $canEditSmtp = $this->canEditSmtp();

        return $this->emailSettingsIndex('diocese', $dioceseId, 'diocese.admin', $canEditSmtp, base_url('diocese/dashboard'), lang('Email.dioceseSettingsTitle'));
    }

    public function save(int $dioceseId = 0)
    {
        if ($dioceseId <= 0) {
            $dioceseId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        $dioceseId = $this->guardDioceseScope($dioceseId);
        if ($dioceseId === null) {
            return redirect()->back()->with('error', lang('App.errorForbidden'));
        }

        $canEditSmtp = $this->canEditSmtp();

        return $this->emailSettingsSave('diocese', $dioceseId, 'diocese.admin', $canEditSmtp, base_url('diocese/dashboard'), lang('Email.dioceseSettingsTitle'));
    }

    public function test(int $dioceseId = 0)
    {
        if ($dioceseId <= 0) {
            $dioceseId = (int) ($this->request->getPost('scope_id') ?? 0);
        }
        $dioceseId = $this->guardDioceseScope($dioceseId);
        if ($dioceseId === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorForbidden'), 403, 'SCOPE_FORBIDDEN'), 403)
                ->setHeader(config('Security')->headerName, csrf_hash());
        }

        $canEditSmtp = $this->canEditSmtp();

        return $this->emailSettingsTest('diocese', $dioceseId, 'diocese.admin', $canEditSmtp);
    }

    /**
     * Resolve the diocese this admin may manage, rejecting any
     * request-supplied scope outside their own diocese. System admins may
     * address any diocese explicitly; everyone else is pinned to the
     * server-resolved diocesan scope.
     */
    private function guardDioceseScope(int $dioceseId): ?int
    {
        if ($this->isSystemAdmin()) {
            if ($dioceseId <= 0) {
                $profile = $this->getProfile();
                $dioceseId = (int) ($profile['diocese_id'] ?? 0);
            }

            return $dioceseId > 0 ? $dioceseId : null;
        }

        $scopeId = $this->resolveDiocesanScopeId();
        if ($scopeId <= 0 || ($dioceseId > 0 && $dioceseId !== $scopeId)) {
            return null;
        }

        return $scopeId;
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
