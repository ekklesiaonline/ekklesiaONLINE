<?php

namespace Config;

use CodeIgniter\Database\QueryInterface;
use CodeIgniter\Debug\Toolbar\Collectors\Database as DatabaseCollector;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;
use CodeIgniter\Shield\Entities\User;
use App\Services\PersonOnboardingService;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', [DatabaseCollector::class, 'collect']);
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

Events::on('DBQuery', static function (QueryInterface $query): void {
    if (ENVIRONMENT === 'testing' || is_cli()) {
        return;
    }

    service('monitoring')->logSlowQuery($query);
});

// Capture onboarding metadata after Shield registration.
Events::on('register', static function (User $user): void {
    $request = service('request');
    $parishId = (int) ($request->getPost('parish_id') ?? 0);
    $provinceId = 0;
    $dioceseId = 0;
    $preferredLocale = strtolower(trim((string) (
        $request->getPost('preferred_locale')
        ?? session('user_locale')
        ?? session('locale')
        ?? (method_exists($request, 'getCookie') ? ($request->getCookie('user_locale') ?? $request->getCookie('locale')) : null)
        ?? $request->getLocale()
        ?? config(\Config\App::class)->defaultLocale
    )));

    $supportedLocales = array_map(static fn(string $locale): string => strtolower($locale), config(\Config\App::class)->supportedLocales);
    $localeAliases = [
        'sp' => 'es',
        'es-do' => 'es',
        'es_mx' => 'es',
        'es-mx' => 'es',
        'fr-ca' => 'fr',
        'fr_fr' => 'fr',
        'fr-fr' => 'fr',
    ];
    $preferredLocale = $localeAliases[$preferredLocale] ?? $preferredLocale;
    if (!in_array($preferredLocale, $supportedLocales, true)) {
        $preferredLocale = '';
    }

    if ($parishId > 0) {
        $db = db_connect();
        $parishFields = [];
        try {
            $parishFields = $db->getFieldNames('parish');
        } catch (\Throwable $e) {
            $parishFields = [];
        }

        $parishScopeBuilder = $db->table('parish c')->where('c.id', $parishId);
        if (in_array('diocese_id', $parishFields, true)) {
            $parishScopeBuilder
                ->select('c.id, d.id as diocese_id, d.province_id')
                ->join('diocese d', 'd.id = c.diocese_id', 'left');
        } else {
            $parishScopeBuilder->select('c.id, NULL as diocese_id, NULL as province_id', false);
        }

        $parishScope = $parishScopeBuilder->get()->getRowArray();

        if ($parishScope) {
            $dioceseId = (int) ($parishScope['diocese_id'] ?? 0);
            $provinceId = (int) ($parishScope['province_id'] ?? 0);
        }
    }

    if ($provinceId <= 0) {
        return;
    }

    // Validate the province actually exists to prevent IDOR via arbitrary IDs.
    $provinceExists = db_connect()
        ->table('province')
        ->where('id', $provinceId)
        ->countAllResults() > 0;

    if (!$provinceExists) {
        return;
    }

    $db = db_connect();

    $resolveExistingTable = static function (array $candidates) use ($db): ?string {
        foreach ($candidates as $table) {
            try {
                $fields = $db->getFieldNames($table);
                if ($fields !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Keep trying candidate table names.
            }
        }

        return null;
    };

    $profileTable = $resolveExistingTable([
        $db->getPrefix() . 'profile',
        'profile',
        $db->getPrefix() . 'profiles',
        'profiles',
    ]);

    $registrationRequestTable = $resolveExistingTable([
        $db->getPrefix() . 'user_registration_request',
        'user_registration_request',
        $db->getPrefix() . 'user_registration_requests',
        'user_registration_requests',
    ]);

    $registrationParishColumn = null;
    if ($registrationRequestTable !== null) {
        foreach (['requested_parish_id', 'parish_id', 'requested_church_id', 'church_id'] as $candidateColumn) {
            try {
                if ($db->fieldExists($candidateColumn, $registrationRequestTable)) {
                    $registrationParishColumn = $candidateColumn;
                    break;
                }
            } catch (\Throwable $e) {
                // Try next candidate column.
            }
        }
    }

    // Allow registration to complete even if onboarding tables are not present yet.
    if ($profileTable === null || $registrationRequestTable === null) {
        if ($preferredLocale !== '') {
            session()->set('locale', $preferredLocale);
            session()->set('user_locale', $preferredLocale);
        }

        return;
    }

    $db->transStart();

    $profile = $db->table($profileTable)
        ->where('user_id', $user->id)
        ->get()
        ->getRowArray();
    if (!$profile) {
        $db->table($profileTable)->insert([
            'user_id' => $user->id,
            'parish_id' => null,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId > 0 ? $dioceseId : null,
            'locale' => $preferredLocale !== '' ? $preferredLocale : null,
            'membership_status' => 'visitor',
        ]);

        $personId = (int) $db->insertID();
        if ($personId > 0) {
            $onboarding = new PersonOnboardingService();
            $onboarding->start($personId, null);
        }
    } else {
        $profileUpdate = [];

        if (empty($profile['province_id'])) {
            $profileUpdate['province_id'] = $provinceId;
        }

        if (empty($profile['diocese_id']) && $dioceseId > 0) {
            $profileUpdate['diocese_id'] = $dioceseId;
        }

        if ($preferredLocale !== '' && strtolower((string) ($profile['locale'] ?? '')) !== $preferredLocale) {
            $profileUpdate['locale'] = $preferredLocale;
        }

        if ($profileUpdate !== []) {
            $db->table($profileTable)
                ->where('id', (int) $profile['id'])
                ->update($profileUpdate);
        }
    }

    $existingRequest = $db->table($registrationRequestTable)
        ->where('user_id', $user->id)
        ->get()
        ->getRowArray();
    if (!$existingRequest) {
        $registrationInsert = [
            'user_id' => $user->id,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId > 0 ? $dioceseId : null,
            'status' => 'pending_profile',
        ];

        if ($registrationParishColumn !== null) {
            $registrationInsert[$registrationParishColumn] = $parishId > 0 ? $parishId : null;
        }

        $db->table($registrationRequestTable)->insert($registrationInsert);
    } elseif ((string) ($existingRequest['status'] ?? '') === 'pending_profile') {
        $registrationUpdate = [
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId > 0 ? $dioceseId : null,
        ];

        if ($registrationParishColumn !== null) {
            $registrationUpdate[$registrationParishColumn] = $parishId > 0 ? $parishId : null;
        }

        $db->table($registrationRequestTable)
            ->where('id', (int) $existingRequest['id'])
            ->update($registrationUpdate);
    }

    $db->transComplete();

    if ($preferredLocale !== '') {
        session()->set('locale', $preferredLocale);
        session()->set('user_locale', $preferredLocale);
    }
});

// Clear the cached locale from session when a user logs out so the next
// user on this browser always reloads their own profile preference.
Events::on('logout', static function (User $user): void {
    session()->remove('user_locale');

    try {
        service('audit')->log('auth.logout', [
            'entity_type' => 'user',
            'entity_id' => (int) $user->id,
        ]);
    } catch (\Throwable $e) {
        log_message('warning', 'Failed to write logout audit log: {error}', ['error' => $e->getMessage()]);
    }
});

Events::on('login', static function (User $user): void {
    try {
        service('audit')->log('auth.login.success', [
            'entity_type' => 'user',
            'entity_id' => (int) $user->id,
        ]);
    } catch (\Throwable $e) {
        log_message('warning', 'Failed to write login audit log: {error}', ['error' => $e->getMessage()]);
    }
});

Events::on('failedLogin', static function (mixed $credentials): void {
    try {
        $username = null;
        if (is_array($credentials)) {
            $username = $credentials['email'] ?? ($credentials['username'] ?? null);
        }

        service('audit')->log('auth.login.failed', [
            'entity_type' => 'user',
            'meta' => [
                'username' => $username,
            ],
        ]);
    } catch (\Throwable $e) {
        log_message('warning', 'Failed to write failed-login audit log: {error}', ['error' => $e->getMessage()]);
    }
});
