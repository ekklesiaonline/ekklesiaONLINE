<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * LocaleFilter
 *
 * Sets the active locale on every request using canonical precedence:
 *
 *  1) System default English ('en')
 *  2) Authenticated profile locale (if valid)
 *  3) Browser locale from Accept-Language (if valid)
 *
 * Session keys are updated as cache/output only; they are not decision inputs.
 *
 * Part of Phase 2 of the i18n implementation plan.
 * See docs/I18N_IMPLEMENTATION_PLAN.md
 */
class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $supported  = array_map(static fn(string $locale): string => strtolower($locale), config('App')->supportedLocales);
        $aliases = [
            'sp' => 'es',
            'es-do' => 'es',
            'es_mx' => 'es',
            'es-mx' => 'es',
            'fr-ca' => 'fr',
            'fr_fr' => 'fr',
            'fr-fr' => 'fr',
        ];
        $normalizeLocale = static function (?string $locale) use ($aliases): string {
            $normalized = strtolower(trim((string) $locale));
            return $aliases[$normalized] ?? $normalized;
        };

        $incomingRequest = service('request');
        $locale = 'en';

        $cookieLocale = '';
        if ($incomingRequest instanceof \CodeIgniter\HTTP\IncomingRequest) {
            $cookieLocale = $normalizeLocale((string) ($incomingRequest->getCookie('user_locale') ?? $incomingRequest->getCookie('locale') ?? ''));
        }
        if (in_array($cookieLocale, $supported, true)) {
            $locale = $cookieLocale;
        }

        $sessionLocale = $normalizeLocale((string) (session('user_locale') ?? session('locale') ?? ''));
        if (in_array($sessionLocale, $supported, true)) {
            $locale = $sessionLocale;
        }

        if (auth()->loggedIn() && $locale === 'en') {
            try {
                $profile = model('ProfileModel')
                    ->where('user_id', auth()->id())
                    ->first();

                $profileLocale = $normalizeLocale((string) ($profile['locale'] ?? ''));
                if (in_array($profileLocale, $supported, true)) {
                    $locale = $profileLocale;
                }
            } catch (\Throwable $e) {
                // Profile table may not be available; skip profile locale.
            }
        }

        if ($locale === 'en') {
            $browserLocale = $normalizeLocale((string) service('negotiator')->language($supported));
            if (in_array($browserLocale, $supported, true)) {
                $locale = $browserLocale;
            }
        }

        session()->set('user_locale', $locale);
        session()->set('locale', $locale);
        $incomingRequest->setLocale($locale);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nothing needed
    }
}
