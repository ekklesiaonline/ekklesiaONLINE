<?php

namespace App\Services;

use Config\App;

/**
 * ProductionSecurityPosture
 *
 * Boot-time guard that warns when the application is running in production
 * with development-only or insecure configuration.
 *
 * Several security controls only engage under CI_ENVIRONMENT=production
 * (Cookie.secure, App.forceGlobalSecureRequests, Security CSRF redirect),
 * and DeviceVerificationPolicy silently refuses the device-verification
 * bypass outside testing/development. A production deploy must never rely
 * on those silent refusals, so this check surfaces misconfiguration loudly.
 *
 * This check is log-only: it never blocks or alters the request. It is
 * registered on the pre_system event in app/Config/Events.php (rather than
 * app/Config/Boot/production.php) because env()/log_message()/config() are
 * not yet available while the environment boot files are being included.
 */
final class ProductionSecurityPosture
{
    /**
     * Logs a critical warning when the given environment is production and
     * any dev-only or insecure posture is detected. Safe to call in every
     * environment; returns immediately outside production.
     */
    public static function warnIfMisconfigured(?string $environment = null): void
    {
        $environment ??= defined('ENVIRONMENT') ? ENVIRONMENT : 'production';

        if ($environment !== 'production') {
            return;
        }

        $findings = [];

        $rawFlag = strtolower((string) env('security.disableDeviceVerificationForTesting', '0'));
        if (in_array($rawFlag, ['1', 'true', 'yes', 'on'], true)) {
            $findings[] = 'security.disableDeviceVerificationForTesting is enabled; the device-verification bypass is a development/testing toggle and must be absent or false in production';
        }

        $baseUrl = (string) (config(App::class)->baseURL ?? '');
        if ($baseUrl !== '' && str_starts_with(strtolower($baseUrl), 'http://')) {
            $findings[] = 'app.baseURL uses the insecure http:// scheme (' . $baseUrl . '); production requires https:// so secure cookies, forced secure requests, and CSRF redirects engage correctly';
        }

        if ($findings === []) {
            return;
        }

        log_message(
            'critical',
            'Production security posture warning: {findings}',
            ['findings' => implode(' | ', $findings)]
        );
    }
}
