<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Cookie\Cookie;
use Config\App as AppConfig;

/**
 * Module-owned public locale switching surface.
 */
class PublicLocale extends BaseController
{
    /**
     * Resolve a safe in-app URL to return to after locale changes.
     */
    private function resolveReturnUrl(): string
    {
        $request = service('request');
        $candidates = [
            (string) $request->getGet('return'),
            (string) $request->getHeaderLine('Referer'),
            (string) previous_url(),
        ];

        $baseUrl = rtrim((string) base_url(), '/');
        $baseHost = (string) parse_url($baseUrl, PHP_URL_HOST);

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $parts = parse_url($candidate);
            if ($parts === false) {
                continue;
            }

            $host = strtolower((string) ($parts['host'] ?? ''));
            if ($host !== '' && $baseHost !== '' && $host !== strtolower($baseHost)) {
                continue;
            }

            $path = '/' . ltrim((string) ($parts['path'] ?? '/'), '/');
            $pathLower = strtolower($path);

            // Never redirect to locale switch endpoints, JSON/API endpoints, or media/file streams.
            $blockedPrefixes = [
                '/locale/set/',
                '/media/',
                '/uploads/',
                '/api/',
            ];
            $isBlocked = false;
            foreach ($blockedPrefixes as $prefix) {
                if (str_starts_with($pathLower, $prefix)) {
                    $isBlocked = true;
                    break;
                }
            }
            if ($isBlocked) {
                continue;
            }

            if (str_ends_with($pathLower, '.json')) {
                continue;
            }

            $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
            $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? ('#' . $parts['fragment']) : '';

            return $path . $query . $fragment;
        }

        return '/';
    }

    public function set(string $locale = 'en')
    {
        $config = config(AppConfig::class);
        $supported = array_map(static fn(string $item): string => strtolower($item), $config->supportedLocales);
        $aliases = [
            'sp' => 'es',
            'es-do' => 'es',
            'es_mx' => 'es',
            'es-mx' => 'es',
            'fr-ca' => 'fr',
            'fr_fr' => 'fr',
            'fr-fr' => 'fr',
        ];
        $normalized = strtolower(trim($locale));
        $normalized = $aliases[$normalized] ?? $normalized;

        if (!in_array($normalized, $supported, true)) {
            $normalized = strtolower((string) $config->defaultLocale);
        }

        $this->response->setCookie(new Cookie('locale', $normalized, [
            'expires' => YEAR,
            'path' => '/',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]));
        $this->response->setCookie(new Cookie('user_locale', $normalized, [
            'expires' => YEAR,
            'path' => '/',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]));
        session()->set('locale', $normalized);
        session()->set('user_locale', $normalized);

        if (auth()->loggedIn()) {
            $profileModel = model('ProfileModel');
            $profile = $profileModel->where('user_id', (int) auth()->id())->first();

            if ($profile) {
                $profileModel->update((int) $profile['id'], ['locale' => $normalized]);
            }

            return redirect()->to($this->resolveReturnUrl());
        }

        return redirect()->to($this->resolveReturnUrl());
    }
}
