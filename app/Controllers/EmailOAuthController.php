<?php

namespace App\Controllers;

use App\Services\GmailOAuthService;

/**
 * EmailOAuthController
 *
 * Single Google OAuth callback for every email-settings tier. The tier and
 * scope id travel inside the HMAC-signed, single-use state payload created
 * by GmailOAuthService::connectUrl(); this controller re-authorizes the
 * session user against that scope before exchanging the code — state is
 * never trusted on its own.
 */
class EmailOAuthController extends BaseController
{
    public function callback()
    {
        $service = new GmailOAuthService();
        $state   = (string) $this->request->getGet('state');
        $code    = (string) $this->request->getGet('code');
        $oauthError = $this->request->getGet('error');

        $payload = $service->parseAndConsumeState($state);
        if ($payload === null) {
            return redirect()->to('/')
                ->with('error', lang('Email.gmailConnectFailed'));
        }

        $tier        = (string) ($payload['tier'] ?? '');
        $settingsUrl = $this->settingsUrlForTier($tier, $payload);

        if (($oauthError !== null && $oauthError !== '') || $code === '') {
            $this->audit()->log('email_settings.gmail_oauth_denied', [
                'user_id'    => (int) (auth()->id() ?? 0),
                'scope_type' => $tier,
                'entity_id'  => $payload['scope_id'] ?? 0,
                'result'     => 'user_denied_or_missing_code',
            ]);

            return redirect()->to($settingsUrl)
                ->with('error', lang('Email.gmailConnectFailed'));
        }

        if (!$this->authorizeStateScope($payload)) {
            $this->audit()->log('email_settings.gmail_oauth_denied', [
                'user_id'    => (int) (auth()->id() ?? 0),
                'scope_type' => $tier,
                'entity_id'  => $payload['scope_id'] ?? 0,
                'result'     => 'scope_forbidden',
            ]);

            return redirect()->to($settingsUrl)
                ->with('error', lang('App.errorForbidden'));
        }

        try {
            $result = $service->handleCallback($code, $payload);
        } catch (\Throwable) {
            $result = ['ok' => false, 'tier' => $tier, 'scope_id' => $payload['scope_id'] ?? null, 'error' => 'GMAIL_API_ERROR'];
        }

        if (!($result['ok'] ?? false)) {
            $errorCode = (string) ($result['error'] ?? 'GMAIL_API_ERROR');
            $this->audit()->log('email_settings.gmail_oauth_failed', [
                'user_id'    => (int) (auth()->id() ?? 0),
                'scope_type' => $tier,
                'entity_id'  => $payload['scope_id'] ?? 0,
                'result'     => $errorCode,
            ]);

            return redirect()->to($settingsUrl)
                ->with('error', $this->oauthErrorMessage($errorCode));
        }

        $this->audit()->log('email_settings.gmail_oauth_connected', [
            'user_id'    => (int) (auth()->id() ?? 0),
            'scope_type' => $tier,
            'entity_id'  => $payload['scope_id'] ?? 0,
            'result'     => 'connected',
        ]);

        return redirect()->to($settingsUrl)
            ->with('success', lang('Email.gmailConnected'));
    }

    /**
     * Server-side settings URL for the tier carried in the state payload.
     * Regional tiers address their scope via a query param so elevated
     * admins land back on the exact scope they connected.
     *
     * @param array<string,mixed> $payload
     */
    private function settingsUrlForTier(string $tier, array $payload = []): string
    {
        $scopeQuery = '';
        $scopeId    = $payload['scope_id'] ?? null;
        if (in_array($tier, ['diocese', 'province'], true) && $scopeId !== null) {
            $scopeQuery = '?' . $tier . '_id=' . (int) $scopeId;
        }

        return match ($tier) {
            'parish'   => base_url('parish/email-settings'),
            'diocese'  => base_url('diocese/email-settings') . $scopeQuery,
            'province' => base_url('province/email-settings') . $scopeQuery,
            'system'   => base_url('system/email'),
            default    => base_url('/'),
        };
    }

    /**
     * Re-authorize the session user for the scope named in the state
     * payload, mirroring each tier controller's permission + scope guards.
     * The state payload is HMAC-verified but still never trusted alone.
     *
     * @param array<string,mixed> $payload
     */
    private function authorizeStateScope(array $payload): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $tier    = (string) ($payload['tier'] ?? '');
        $scopeId = isset($payload['scope_id']) ? (int) $payload['scope_id'] : null;
        $userId  = (int) ($payload['user_id'] ?? 0);

        // The OAuth flow must complete in the same session that started it.
        if ($userId <= 0 || $userId !== (int) (auth()->id() ?? 0)) {
            return false;
        }

        return match ($tier) {
            'system', 'organisation' => $user->can('system.admin'),

            'province' => $user->can('province.admin')
                && ($this->isSystemAdmin() || $this->resolveAdminProvinceId() === (int) $scopeId),

            'diocese' => $user->can('diocese.admin')
                && ($user->can('diocese.primary') || $user->can('system.admin'))
                && ($this->isSystemAdmin() || $this->resolveDiocesanScopeId() === (int) $scopeId),

            'parish' => !$this->isDioceseAdmin()
                && $user->can('parish.edit')
                && ($user->can('parish.primary') || $user->can('system.admin'))
                && $this->getOperationalParishId() === (int) $scopeId,

            default => false,
        };
    }

    private function oauthErrorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'GMAIL_OAUTH_NOT_CONFIGURED'     => lang('Email.gmailNotConfigured'),
            'GMAIL_NO_REFRESH_TOKEN'         => lang('Email.gmailNoRefreshToken'),
            'GMAIL_ACCOUNT_DISCOVERY_FAILED' => lang('Email.gmailAccountDiscoveryFailed'),
            'GMAIL_OAUTH_EXCHANGE_FAILED',
            'GMAIL_OAUTH_BAD_STATE',
            'GMAIL_API_ERROR'                => lang('Email.gmailConnectFailed'),
            default                          => lang('Email.gmailConnectFailed'),
        };
    }
}
