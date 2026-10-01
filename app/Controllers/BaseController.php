<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Config\App as AppConfig;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\IncomingRequest;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    public const ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT = 'operational_default';
    public const ATTRIBUTION_POLICY_HOME_DEFAULT = 'home_default';

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    protected $helpers = ['auth', 'url', 'form', 'text'];

    /**
     * Current user
     */
    protected $user;

    /**
     * Current user's parish ID
     */
    //protected $parishId;
    protected $parishId;

    /**
     * Current user's profile
     */
    protected $profile;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Load current user if logged in
        if (auth()->loggedIn()) {
            $this->user = auth()->user();
            // Profile is now lazy-loaded via getProfile() method for better performance
            // Only load when actually needed instead of on every request
        }

        $resolvedLocale = $this->resolvePreferredLocale($request);
        $incomingRequest = service('request');
        if ($incomingRequest instanceof IncomingRequest) {
            $incomingRequest->setLocale($resolvedLocale);
        }
        service('language')->setLocale($resolvedLocale);
        session()->set('locale', $resolvedLocale);
    }

    protected function resolvePreferredLocale(RequestInterface $request): string
    {
        $config = config(AppConfig::class);
        $supported = array_map(static fn(string $locale): string => strtolower($locale), $config->supportedLocales);
        $aliases = [
            'sp' => 'es',
            'es-do' => 'es',
            'es_mx' => 'es',
            'es-mx' => 'es',
            'fr-ca' => 'fr',
            'fr_fr' => 'fr',
            'fr-fr' => 'fr',
        ];
        $default = 'en';

        $normalizeLocale = static function (?string $locale) use ($aliases): string {
            $normalized = strtolower(trim((string) $locale));
            return $aliases[$normalized] ?? $normalized;
        };

        // Respect explicit user selection stored in cookie/session first.
        $incomingRequest = service('request');
        $cookieLocale = '';
        if ($incomingRequest instanceof IncomingRequest) {
            $cookieLocale = $normalizeLocale((string) ($incomingRequest->getCookie('user_locale') ?? $incomingRequest->getCookie('locale') ?? ''));
        }
        if (in_array($cookieLocale, $supported, true)) {
            return $cookieLocale;
        }

        $sessionLocale = $normalizeLocale((string) (session('user_locale') ?? session('locale') ?? ''));
        if (in_array($sessionLocale, $supported, true)) {
            return $sessionLocale;
        }

        if ($this->user !== null) {
            $profile = $this->getProfile();
            $profileLocale = $normalizeLocale((string) ($profile['locale'] ?? ''));
            if (in_array($profileLocale, $supported, true)) {
                return $profileLocale;
            }
        }

        $acceptLanguage = strtolower((string) $request->getHeaderLine('Accept-Language'));
        $browserLocaleRaw = trim((string) (explode(',', $acceptLanguage)[0] ?? ''));
        $browserLocale = $normalizeLocale(str_replace('_', '-', strtolower($browserLocaleRaw)));
        if (!in_array($browserLocale, $supported, true)) {
            $browserLocale = $normalizeLocale(substr($browserLocale, 0, 2));
        }
        if (in_array($browserLocale, $supported, true)) {
            return $browserLocale;
        }

        return in_array($default, $supported, true) ? $default : 'en';
    }

    /**
     * Get current user's profile (lazy-loaded and cached)
     */
    protected function getProfile(): ?array
    {
        if ($this->profile === null && $this->user) {
            $profileModel = model('ProfileModel');
            $this->profile = $profileModel->where('user_id', $this->user->id)->first();

            if ($this->profile) {
                $this->parishId = (int) ($this->profile['parish_id'] ?? 0);

                // profile.membership_status was dropped; the canonical status
                // lives on parish_affiliation. Decorate the row so views keep
                // reading $profile['membership_status'].
                $affiliationStatus = model('MemberParishAffiliationModel')
                    ->statusForUser((int) $this->user->id);
                if ($affiliationStatus !== null) {
                    $this->profile['membership_status'] = $affiliationStatus;
                }
            }
        }

        return $this->profile;
    }

    protected function isProvinceAdmin(): bool
    {
        return (bool) ($this->user && $this->user->inGroup('provinceadmin'));
    }

    protected function isSystemAdmin(): bool
    {
        return (bool) ($this->user && $this->user->inGroup('systemadmin'));
    }

    protected function isDioceseAdmin(): bool
    {
        return (bool) ($this->user && $this->user->inGroup('dioceseadmin'));
    }

    /**
     * Determine whether a user is an elevated admin (system/province/diocese).
     */
    protected function isElevatedAdmin($user): bool
    {
        return $user !== null && $user->inGroup('systemadmin', 'provinceadmin', 'dioceseadmin');
    }

    /**
     * Resolve a human-readable label for the logged-in user's primary role,
     * used for at-a-glance feedback in the top header bar. The highest-tier
     * group wins so the label matches the dashboard the user lands on.
     */
    protected function resolveLoggedInRoleLabel($user): string
    {
        if ($user === null) {
            return '';
        }

        $authGroups = config('AuthGroups')->groups ?? [];
        $tierOrder = [
            'systemadmin',
            'provinceadmin',
            'dioceseadmin',
            'parishadmin',
            'finance',
            'vestry_secretary',
            'warden',
            'vestry_member',
            'clergy',
            'volunteer_coordinator',
            'followup_coordinator',
            'worship_coordinator',
            'followup_volunteer',
            'worship_volunteer',
            'member',
        ];

        foreach ($tierOrder as $group) {
            if ($user->inGroup($group) && isset($authGroups[$group]['title'])) {
                return (string) $authGroups[$group]['title'];
            }
        }

        return '';
    }

    /**
     * Resolve the list of Shield groups the logged-in user belongs to,
     * keyed by group name with the human-readable title as the value.
     * Used by the Profile Account tab to show the authoritative role list.
     *
     * @return array<string,string>
     */
    protected function resolveLoggedInGroups($user): array
    {
        if ($user === null) {
            return [];
        }

        $authGroups = config('AuthGroups')->groups ?? [];
        $groups = $user->getGroups();
        $result = [];

        foreach ($groups as $group) {
            $result[$group] = $authGroups[$group]['title'] ?? ucfirst(str_replace('_', ' ', $group));
        }

        return $result;
    }

    /**
     * Elevated admin identities (system/province/diocese) must not be blocked by
     * end-user onboarding review gates, even if a stale pending_review row exists.
     */
    protected function canBypassPendingReviewGate($user): bool
    {
        return $this->isElevatedAdmin($user);
    }

    /**
     * Resolve a human-readable label describing what an elevated admin (system,
     * province, or diocese) actually administers. Elevated admins are not tied
     * to a single parish, so parish-centric affiliation display does not apply
     * to them — this centralizes the "what does this admin oversee" lookup so
     * the sidebar, dashboard, and profile page can render consistent, correct
     * information instead of falling back to "no affiliation".
     *
     * Resolution order per level:
     *   - system:   *_admin_primary table has no scope; always "system-wide".
     *   - province: province_admin_primary.province_id, falling back to profile.province_id.
     *   - diocese:  diocese_admin_primary.diocese_id, falling back to profile.diocese_id.
     *
     * @return array{level: string, name: string}|null
     */
    protected function resolveElevatedAdminScopeLabel($user): ?array
    {
        if ($user === null || !$this->isElevatedAdmin($user)) {
            return null;
        }

        $db = db_connect();
        $userId = (int) $user->id;

        if ($user->inGroup('systemadmin')) {
            return ['level' => 'system', 'name' => lang('Profile.systemAdminScope')];
        }

        if ($user->inGroup('provinceadmin')) {
            $provinceId = 0;
            try {
                $row = model('ProvinceAdminPrimaryModel')->getByUser($userId);
                $provinceId = (int) ($row['province_id'] ?? 0);
            } catch (\Throwable $e) {
                $provinceId = 0;
            }

            if ($provinceId <= 0) {
                $profile = model('ProfileModel')->where('user_id', $userId)->first();
                $provinceId = (int) ($profile['province_id'] ?? 0);
            }

            if ($provinceId > 0) {
                $province = $db->table('province')->select('name')->where('id', $provinceId)->get()->getRowArray();
                if ($province) {
                    return ['level' => 'province', 'name' => (string) $province['name']];
                }
            }

            return ['level' => 'province', 'name' => lang('Profile.provinceAdminScopeUnknown')];
        }

        if ($user->inGroup('dioceseadmin')) {
            $dioceseId = 0;
            try {
                $row = model('DioceseAdminPrimaryModel')->getByUser($userId);
                $dioceseId = (int) ($row['diocese_id'] ?? 0);
            } catch (\Throwable $e) {
                $dioceseId = 0;
            }

            if ($dioceseId <= 0) {
                $profile = model('ProfileModel')->where('user_id', $userId)->first();
                $dioceseId = (int) ($profile['diocese_id'] ?? 0);
            }

            if ($dioceseId > 0) {
                $diocese = $db->table('diocese')->select('name')->where('id', $dioceseId)->get()->getRowArray();
                if ($diocese) {
                    return ['level' => 'diocese', 'name' => (string) $diocese['name']];
                }
            }

            return ['level' => 'diocese', 'name' => lang('Profile.dioceseAdminScopeUnknown')];
        }

        return null;
    }

    /**
     * Get the primary admin user ID for a province.
     */
    protected function getPrimaryProvinceAdminId(int $provinceId): ?int
    {
        $row = model('ProvinceAdminPrimaryModel')->getByProvince($provinceId);
        $userId = (int) ($row['user_id'] ?? 0);

        return $userId > 0 ? $userId : null;
    }

    /**
     * Determine whether the current user is the primary admin for a province.
     */
    protected function isPrimaryProvinceAdmin(int $provinceId): bool
    {
        $primaryId = $this->getPrimaryProvinceAdminId($provinceId);
        $currentId = (int) (auth()->id() ?? 0);

        return $primaryId !== null && $primaryId === $currentId;
    }

    /**
     * Get the primary admin user ID for a parish.
     */
    protected function getPrimaryParishAdminId(int $parishId): ?int
    {
        $row = model('ParishAdminPrimaryModel')->getByParish($parishId);
        $userId = (int) ($row['user_id'] ?? 0);

        return $userId > 0 ? $userId : null;
    }

    /**
     * Determine whether the current user is the primary admin for a parish.
     */
    protected function isPrimaryParishAdmin(int $parishId): bool
    {
        $primaryId = $this->getPrimaryParishAdminId($parishId);
        $currentId = (int) (auth()->id() ?? 0);

        return $primaryId !== null && $primaryId === $currentId;
    }

    /**
     * Home parish is the user's primary active parish affiliation, falling
     * back to the profile parish for accounts without an affiliation yet
     * (e.g. registration pending review).
     */
    protected function getHomeparishId(): ?int
    {
        if ($this->parishId === null) {
            $userId = (int) (auth()->id() ?? 0);
            if ($userId > 0) {
                try {
                    $affiliation = db_connect()->table('parish_affiliation')
                        ->select('parish_id')
                        ->where('linked_user_id', $userId)
                        ->where('deleted_at IS NULL', null, false)
                        ->orderBy('is_primary', 'DESC')
                        ->orderBy('updated_at', 'DESC')
                        ->get()
                        ->getRowArray();
                    $this->parishId = (int) ($affiliation['parish_id'] ?? 0);
                } catch (\Throwable $e) {
                    $this->parishId = 0;
                }
            }

            if (! $this->parishId) {
                $profile = $this->getProfile();
                if ($profile) {
                    $this->parishId = (int) ($profile['parish_id'] ?? 0);
                }
            }
        }

        $homeparishId = (int) ($this->parishId ?? 0);

        return $homeparishId > 0 ? $homeparishId : null;
    }

    /**
     * Operational parish is the active working context (session selection),
     * with fallback to home parish and scoped defaults where required.
     */
    protected function getOperationalParishId(): ?int
    {
        if ($this->isProvinceAdmin() || $this->isDioceseAdmin()) {
            $selectedParish = (int) (session()->get('selected_parish_id') ?? 0);
            if ($selectedParish > 0) {
                return $selectedParish;
            }

            $homeparishId = $this->getHomeparishId();
            if ($homeparishId !== null) {
                return $homeparishId;
            }

            // Diocesan admins may not have a direct profile parish. In that case,
            // pick a default parish from their scoped diocese to keep parish-level
            // forms functional without a manual switch first.
            if ($this->isDioceseAdmin()) {
                $dioceseId = 0;
                try {
                    $primaryRow = model('DioceseAdminPrimaryModel')->getByUser((int) auth()->id());
                    $dioceseId  = (int) ($primaryRow['diocese_id'] ?? 0);
                } catch (\Throwable $e) {
                    $dioceseId = 0;
                }

                if ($dioceseId <= 0) {
                    $profile   = $this->getProfile();
                    $dioceseId = (int) ($profile['diocese_id'] ?? 0);
                }

                if ($dioceseId <= 0 && $homeparishId !== null) {
                    $row = db_connect()->table('parish')
                        ->select('diocese_id')
                        ->where('id', $homeparishId)
                        ->get()
                        ->getRowArray();
                    $dioceseId = (int) ($row['diocese_id'] ?? 0);
                }

                if ($dioceseId > 0) {
                    $fallbackParish = db_connect()->table('parish')
                        ->select('id')
                        ->where('diocese_id', $dioceseId)
                        ->orderBy('id', 'ASC')
                        ->get()
                        ->getRowArray();

                    $fallbackparishId = (int) ($fallbackParish['id'] ?? 0);
                    if ($fallbackparishId > 0) {
                        $this->parishId = $fallbackparishId;
                        session()->set('selected_parish_id', $fallbackparishId);

                        return $fallbackparishId;
                    }
                }
            }

            return null;
        }

        return $this->getHomeparishId();
    }

    /**
     * Set operational parish context for cross-parish roles.
     */
    protected function setOperationalParishId($parishId): void
    {
        $parishIdInt = (int) $parishId;
        if (($this->isProvinceAdmin() || $this->isDioceseAdmin()) && $parishIdInt > 0) {
            session()->set('selected_parish_id', $parishIdInt);
        }
    }

    /**
     * Resolve attribution parish for write operations.
     *
     * Policy options:
     * - operational_default: operational parish first, fallback to home parish
     * - home_default: home parish first, fallback to operational parish
     */
    protected function resolveAttributionParishId(string $policy = self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT): ?int
    {
        $homeParishId = $this->getHomeparishId();
        $operationalParishId = $this->getOperationalparishId();

        if ($policy === self::ATTRIBUTION_POLICY_HOME_DEFAULT) {
            return $homeParishId ?? $operationalParishId;
        }

        return $operationalParishId ?? $homeParishId;
    }

    /**
     * Build normalized parish-context metadata for audit trails.
     */
    protected function buildParishContextMeta(?int $attributionParishId, string $policy): array
    {
        return [
            'parish_context' => [
                'home_parish_id' => $this->getHomeparishId(),
                'operational_parish_id' => $this->getOperationalparishId(),
                'attribution_parish_id' => $attributionParishId,
                'attribution_policy' => $policy,
            ],
        ];
    }

    /**
     * Get user's parish ID or selected parish for province admin
     */
    protected function getparishId()
    {
        // Backward-compatible wrapper: existing callers keep working.
        return $this->getOperationalparishId();
    }

    /**
     * Set parish ID for province/diocesan admins
     */
    protected function setparishId($parishId)
    {
        // Backward-compatible wrapper: existing callers keep working.
        $this->setOperationalParishId($parishId);
    }

    /**
     * Resolve the province ID that the current admin is scoped to.
     *
     * Resolution order:
     *   1. province_admin_primary.province_id (authoritative assignment)
     *   2. diocese_admin_primary.diocese_id → dioceses.province_id
     *   3. profile.province_id  (declared scope for non-primary admins)
     *   4. profile.diocese_id → dioceses.province_id
     *   5. selected/assigned parish → dioceses.province_id
     *
     * Returns 0 when no province can be determined.
     */
    protected function resolveAdminProvinceId(): int
    {
        $userId = (int) (auth()->id() ?? 0);
        if ($userId > 0) {
            try {
                $row = model('ProvinceAdminPrimaryModel')->getByUser($userId);
                if (! empty($row['province_id'])) {
                    return (int) $row['province_id'];
                }

                $row = model('DioceseAdminPrimaryModel')->getByUser($userId);
                if (! empty($row['diocese_id'])) {
                    $diocese = db_connect()->table('diocese')
                        ->select('province_id')
                        ->where('id', (int) $row['diocese_id'])
                        ->get()
                        ->getRowArray();
                    if (! empty($diocese['province_id'])) {
                        return (int) $diocese['province_id'];
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to profile/parish fallbacks.
            }
        }

        $profile = $this->getProfile();

        if (!empty($profile['province_id'])) {
            return (int) $profile['province_id'];
        }

        if (!empty($profile['diocese_id'])) {
            $row = db_connect()->table('diocese')
                ->select('province_id')
                ->where('id', (int) $profile['diocese_id'])
                ->get()
                ->getRowArray();

            if (!empty($row['province_id'])) {
                return (int) $row['province_id'];
            }
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId > 0) {
            $parish = model('ParishModel')->findWithHierarchy($parishId);
            if (!empty($parish['province_id'])) {
                return (int) $parish['province_id'];
            }
        }

        return 0;
    }

    /**
     * Resolve the diocese ID that the current diocesan admin is scoped to.
     *
     * Resolution order:
     *   1. diocese_admin_primary.diocese_id (authoritative assignment)
     *   2. profile.diocese_id (declared scope for non-primary admins)
     *   3. selected/assigned parish → parishes.diocese_id
     *   4. primary parish affiliation → parishes.diocese_id
     *
     * Returns 0 when no diocese can be determined.
     */
    protected function resolveDiocesanScopeId(): int
    {
        $userId = (int) (auth()->id() ?? 0);
        if ($userId > 0) {
            try {
                $row = model('DioceseAdminPrimaryModel')->getByUser($userId);
                if (! empty($row['diocese_id'])) {
                    return (int) $row['diocese_id'];
                }
            } catch (\Throwable $e) {
                // Fall through to profile/parish fallbacks.
            }
        }

        $profile = $this->getProfile();

        if (!empty($profile['diocese_id'])) {
            return (int) $profile['diocese_id'];
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId > 0) {
            $parish = model('ParishModel')->find($parishId);
            if (!empty($parish['diocese_id'])) {
                return (int) $parish['diocese_id'];
            }
        }

        // Fallback for diocesan admins whose profile/parish fields are not yet
        // populated: derive scope from active parish affiliations.
        $db = db_connect();
        $hasPrimaryColumn = false;
        try {
            $hasPrimaryColumn = $db->fieldExists('is_primary', 'parish_affiliation');
        } catch (\Throwable $e) {
            $hasPrimaryColumn = false;
        }

        $affiliationQuery = $db->table('parish_affiliation mca')
            ->select('c.diocese_id')
            ->join('parish c', 'c.id = mca.parish_id', 'inner')
            ->where('mca.linked_user_id', (int) auth()->id())
            ->where('mca.is_member', 1);

        if ($hasPrimaryColumn) {
            $affiliationQuery->orderBy('mca.is_primary', 'DESC');
        }

        $affiliatedParish = $affiliationQuery
            ->orderBy('mca.id', 'ASC')
            ->get()
            ->getRowArray();

        if (!empty($affiliatedParish['diocese_id'])) {
            return (int) $affiliatedParish['diocese_id'];
        }

        return 0;
    }

    /**
     * Check permission with parish scoping
     */
    protected function checkPermission(string $permission): bool
    {
        if (!auth()->loggedIn()) {
            return false;
        }

        if (false) {
            throw new \RuntimeException('checkPermission: user is null (DEBUG)');
        }

        return $this->user->can($permission);
    }

    /**
     * Ensure user has permission or redirect
     */
    protected function requirePermission(string $permission)
    {
        if (!$this->checkPermission($permission)) {
            $this->audit()->logAuthorizationDenial(
                'permission.denied',
                'PERMISSION_DENIED',
                [
                    'permission' => $permission,
                    'parish_id' => $this->getOperationalparishId(),
                ]
            );

            // return redirect()->to('/dashboard')->with('error', lang('App.errorForbidden'));
            return redirect()->to('/')->with('error', lang('App.errorForbidden'));
        }

        return null;
    }

    /**
     * Ensure user has parish access
     */
    protected function requireParish()
    {
        $parishId = (int) ($this->getOperationalparishId() ?? 0);

        if ($parishId <= 0 && !$this->isProvinceAdmin() && !$this->isDioceseAdmin()) {
            $this->audit()->logAuthorizationDenial(
                'scope.denied',
                'PARISH_SCOPE_REQUIRED',
                ['scope' => 'parish']
            );
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        return null;
    }

    protected function audit(): \App\Services\AuditService
    {
        return service('audit');
    }

    protected function getCurrentRegistrationRequest(): ?array
    {
        if (!auth()->loggedIn()) {
            return null;
        }

        try {
            return model('UserRegistrationRequestModel')
                ->where('user_id', (int) auth()->id())
                ->orderBy('id', 'DESC')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function getRegistrationStatus(?array $registrationRequest): string
    {
        return (string) ($registrationRequest['status'] ?? '');
    }

    /**
     * Determine profile readiness and the target URL for profile navigation.
     *
     * @param array<string,mixed>|null $profile
     * @param array<string,mixed>|null $registrationRequest
     * @return array{profile_pending: bool, profile_nav_url: string}
     */
    protected function resolveProfileNavigationContext($profile, ?array $registrationRequest): array
    {
        $hasProfile = is_array($profile) && !empty($profile['id']);
        $status = $this->getRegistrationStatus($registrationRequest);

        $user = auth()->user();
        $isStaffAdmin = $user && (
            $user->inGroup('systemadmin')
            || $user->inGroup('provinceadmin')
            || $user->inGroup('dioceseadmin')
            || $user->inGroup('parishadmin')
        );

        $profilePending = !$isStaffAdmin
            && (($status === 'pending_profile') || !$hasProfile);

        return [
            'profile_pending' => $profilePending,
            'profile_nav_url' => $profilePending ? 'profile/complete' : 'profile',
        ];
    }

    /**
     * @param array<int,mixed> $userIds
     * @return array<int,bool>
     */
    protected function getPendingProfileUserIdMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        try {
            $rows = model('UserRegistrationRequestModel')
                ->select('user_id')
                ->whereIn('user_id', $userIds)
                ->where('status', 'pending_profile')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $map[$uid] = true;
            }
        }

        return $map;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    protected function annotateRowsWithProfilePending(array $rows, string $userIdKey = 'user_id', ?string $profileIdKey = 'profile_id'): array
    {
        $userIds = [];
        foreach ($rows as $row) {
            $userIds[] = (int) ($row[$userIdKey] ?? 0);
        }
        $pendingMap = $this->getPendingProfileUserIdMap($userIds);

        foreach ($rows as &$row) {
            $userId = (int) ($row[$userIdKey] ?? 0);
            $hasProfile = true;

            if ($profileIdKey !== null) {
                $hasProfile = (int) ($row[$profileIdKey] ?? 0) > 0;
            }

            $row['profile_pending_completion'] = (isset($pendingMap[$userId]) || !$hasProfile) ? 1 : 0;
        }
        unset($row);

        return $rows;
    }

    protected function shouldLogoutForRegistrationStatus(?array $registrationRequest): bool
    {
        return in_array($this->getRegistrationStatus($registrationRequest), ['dismissed', 'banned'], true);
    }

    /**
     * Localize common page header titles passed from controllers.
     */
    protected function localizeHeaderTitle($pageTitle)
    {
        if (!is_string($pageTitle) || $pageTitle === '') {
            return $pageTitle;
        }

        $map = [
            'Annual Pledges' => 'App.headerAnnualPledges',
            'Attendance Reports' => 'App.headerAttendanceReports',
            'Budgets' => 'App.navBudgets',
            'Chart of Accounts' => 'App.navChartOfAccounts',
            'Create Account' => 'App.headerCreateAccount',
            'Create Chart Account' => 'App.headerCreateChartAccount',
            'Create Parish' => 'App.headerCreateParish',
            'Create Event' => 'App.headerCreateEvent',
            'Create Follow-up Task' => 'App.headerCreateFollowUpTask',
            'Create Schedule' => 'App.headerCreateSchedule',
            'Create Service' => 'App.headerCreateService',
            'Create Volunteer Team' => 'App.headerCreateVolunteerTeam',
            'Dashboard' => 'App.navDashboard',
            'Donations' => 'App.navDonations',
            'Edit Chart Account' => 'App.headerEditChartAccount',
            'Edit Parish' => 'App.headerEditParish',
            'Edit Event' => 'App.headerEditEvent',
            'Edit Expense' => 'App.headerEditExpense',
            'Edit Visitor' => 'App.headerEditVisitor',
            'Edit Member' => 'App.headerEditMember',
            'Edit My Profile' => 'App.headerEditMyProfile',
            'Edit Schedule' => 'App.headerEditSchedule',
            'Edit Service' => 'App.headerEditService',
            'Edit Song' => 'App.headerEditSong',
            'Event Details' => 'App.headerEventDetails',
            'Events' => 'App.navEvents',
            'Expenses' => 'App.navExpenses',
            'Families' => 'App.navFamilies',
            'Family Details' => 'Families.pageTitleView',
            'Finance Dashboard' => 'App.navFinanceDashboard',
            'Follow-up Tasks' => 'App.headerFollowUpTasks',
            'Good Standing' => 'App.navGoodStanding',
            'Visitor Details' => 'App.headerVisitorDetails',
            'Visitor Detail' => 'App.headerVisitorDetails',
            'Visitors' => 'App.navVisitors',
            'Leadership Positions' => 'App.headerLeadershipPositions',
            'Manage Parish Staff' => 'App.headerManageParishStaff',
            'Mark Attendance' => 'App.headerMarkAttendance',
            'Member Details' => 'App.headerMemberDetails',
            'Members' => 'App.navMembers',
            'My Profile' => 'App.navProfile',
            'Province Dashboard' => 'App.headerProvinceDashboard',
            'Record Donation' => 'App.headerRecordDonation',
            'Record Expense' => 'App.headerRecordExpense',
            'Register Visitor' => 'App.headerRegisterVisitor',
            'Reports' => 'App.navReports',
            'Service Details' => 'App.headerServiceDetails',
            'Service Worship Plan' => 'App.headerServiceWorshipPlan',
            'Services' => 'App.navServices',
            'Song Library' => 'App.headerSongLibrary',
            'Staff Management' => 'App.headerStaffManagement',
            'System Admin Dashboard' => 'App.headerSystemAdminDashboard',
            'System Overview' => 'App.navSystemOverview',
            'Team Details' => 'App.headerTeamDetails',
            'Volunteer Schedules' => 'App.navVolunteerSchedules',
            'Volunteer Teams' => 'App.headerVolunteerTeams',
            'Worship Planning' => 'App.navWorshipPlanning',
        ];

        if (!isset($map[$pageTitle])) {
            return $pageTitle;
        }

        $key = $map[$pageTitle];
        $translated = lang($key);

        return ($translated === $key) ? $pageTitle : $translated;
    }

    /**
     * Resolve the canonical dashboard scope for a user following the parish
     * hierarchy: System → Province → Diocese → Parish → Personal.
     *
     * Returns an array with:
     *   - tier:      system|province|diocese|parish|personal
     *   - scope_id:  int|null (the province/diocese/parish id, null for system/personal)
     *   - role:      the primary group name driving this resolution
     *   - route:     the dashboard route the user should land on
     *
     * Resolution is first-match-wins by tier (highest tier first), so a user
     * holding multiple groups always lands on their highest-tier dashboard.
     *
     * @return array{tier: string, scope_id: int|null, role: string, route: string}
     */
    protected function resolveDashboardScope($user): array
    {
        if ($user === null) {
            return ['tier' => 'personal', 'scope_id' => null, 'role' => 'guest', 'route' => '/login'];
        }

        // Tier 1: System
        if ($user->inGroup('systemadmin')) {
            return ['tier' => 'system', 'scope_id' => null, 'role' => 'systemadmin', 'route' => '/system/dashboard'];
        }

        // Tier 2: Province
        if ($user->inGroup('provinceadmin')) {
            $provinceId = null;
            try {
                $row = model('ProvinceAdminPrimaryModel')->getByUser((int) $user->id);
                $provinceId = (int) ($row['province_id'] ?? 0);
                if ($provinceId <= 0) {
                    $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();
                    $provinceId = (int) ($profile['province_id'] ?? 0);
                }
                $provinceId = $provinceId > 0 ? $provinceId : null;
            } catch (\Throwable $e) {
                $provinceId = null;
            }

            return ['tier' => 'province', 'scope_id' => $provinceId, 'role' => 'provinceadmin', 'route' => '/province/dashboard'];
        }

        // Tier 3: Diocese
        if ($user->inGroup('dioceseadmin')) {
            $dioceseId = null;
            try {
                $row = model('DioceseAdminPrimaryModel')->getByUser((int) $user->id);
                $dioceseId = (int) ($row['diocese_id'] ?? 0);
                if ($dioceseId <= 0) {
                    $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();
                    $dioceseId = (int) ($profile['diocese_id'] ?? 0);
                }
                $dioceseId = $dioceseId > 0 ? $dioceseId : null;
            } catch (\Throwable $e) {
                $dioceseId = null;
            }

            return ['tier' => 'diocese', 'scope_id' => $dioceseId, 'role' => 'dioceseadmin', 'route' => '/diocese/dashboard'];
        }

        // Tier 4: Parish — admin
        if ($user->inGroup('parishadmin')) {
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => 'parishadmin', 'route' => '/parish/dashboard'];
        }

        // Tier 4: Parish — finance officer
        if ($user->inGroup('finance')) {
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => 'finance', 'route' => '/parish/finance'];
        }

        // Tier 4: Parish — vestry secretary
        if ($user->inGroup('vestry_secretary')) {
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => 'vestry_secretary', 'route' => '/parish/secretary'];
        }

        // Tier 4: Parish — vestry governance roles
        if ($user->inGroup('vestry_member', 'warden')) {
            $role = $user->inGroup('warden') ? 'warden' : 'vestry_member';
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => $role, 'route' => '/parish/governance'];
        }

        // Tier 4: Parish — clergy (worship-focused)
        if ($user->inGroup('clergy')) {
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => 'clergy', 'route' => '/parish/worship'];
        }

        // Tier 4: Parish — volunteer coordinator
        if ($user->inGroup('volunteer_coordinator')) {
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => 'volunteer_coordinator', 'route' => '/parish/volunteers'];
        }

        // Tier 4: Parish — followup coordinator
        if ($user->inGroup('followup_coordinator')) {
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => 'followup_coordinator', 'route' => '/parish/followup'];
        }

        // Tier 4: Parish — worship coordinator
        if ($user->inGroup('worship_coordinator')) {
            return ['tier' => 'parish', 'scope_id' => $this->getOperationalParishId() ?: null, 'role' => 'worship_coordinator', 'route' => '/parish/worship'];
        }

        // Tier 5: Personal — followup/worship volunteers and plain members
        if ($user->inGroup('followup_volunteer', 'worship_volunteer', 'member') || $user->can('dashboard.view')) {
            return ['tier' => 'personal', 'scope_id' => null, 'role' => 'member', 'route' => '/me'];
        }

        return ['tier' => 'personal', 'scope_id' => null, 'role' => 'member', 'route' => '/me'];
    }

    /**
     * Determine whether a user can access the personal /me dashboard.
     *
     * Parish-level roles and members always qualify. Elevated admins
     * (system/province/diocese) qualify only if they have a parish_affiliation
     * row, so the nav link and access are conditional on actual affiliation.
     */
    protected function canAccessMyDashboard($user): bool
    {
        if ($user === null) {
            return false;
        }

        // Parish-level and member roles always have /me access.
        if ($user->inGroup(
            'parishadmin',
            'clergy',
            'finance',
            'vestry_secretary',
            'vestry_member',
            'warden',
            'volunteer_coordinator',
            'followup_coordinator',
            'followup_volunteer',
            'worship_coordinator',
            'worship_volunteer',
            'member'
        )) {
            return true;
        }

        // Elevated admins: only if they have a parish_affiliation row.
        if ($this->isElevatedAdmin($user)) {
            return db_connect()->table('parish_affiliation')
                ->where('linked_user_id', (int) $user->id)
                ->countAllResults() > 0;
        }

        return false;
    }

    protected function resolveAuthenticatedLandingPath($user, ?array $registrationRequest = null): string
    {
        if ($this->getRegistrationStatus($registrationRequest) === 'pending_profile') {
            return '/profile/complete';
        }

        return $this->resolveDashboardScope($user)['route'];
    }

    protected function canLandOnDashboard($user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('dashboard.view')) {
            return true;
        }

        return $user->inGroup(
            'systemadmin',
            'provinceadmin',
            'dioceseadmin',
            'parishadmin',
            'clergy',
            'finance',
            'vestry_secretary',
            'vestry_member',
            'warden',
            'volunteer_coordinator',
            'followup_coordinator',
            'followup_volunteer',
            'worship_coordinator',
            'worship_volunteer',
            'member'
        );
    }

    protected function canAccessDashboardPath($user, string $landingPath): bool
    {
        return match ($landingPath) {
            '/province/dashboard'   => $user->can('province.admin') || $user->inGroup('provinceadmin'),
            '/diocese/dashboard'    => $user->can('diocese.admin') || $user->inGroup('dioceseadmin'),
            '/system/dashboard'     => $user->can('system.admin') || $user->can('admin.system') || $user->inGroup('systemadmin'),
            '/parish/dashboard'     => $user->inGroup('parishadmin', 'clergy') || $user->can('dashboard.view'),
            '/parish/finance'       => $user->inGroup('finance') || $user->can('finance.view'),
            '/parish/governance'    => $user->inGroup('vestry_member', 'warden', 'vestry_secretary') || $user->can('governance.vestry.view'),
            '/parish/secretary'     => $user->inGroup('vestry_secretary') || $user->can('report.view'),
            '/parish/worship'       => $user->inGroup('clergy', 'worship_coordinator') || $user->can('service.view'),
            '/parish/volunteers'    => $user->inGroup('volunteer_coordinator') || $user->can('volunteer.view'),
            '/parish/followup'      => $user->inGroup('followup_coordinator') || $user->can('followup.manage'),
            '/me'                   => $this->canAccessMyDashboard($user),
            '/dashboard'            => $this->canLandOnDashboard($user),
            default                 => false,
        };
    }

    /**
     * Get common view data that should be available in all views
     * (profile, parish, page_title, role flags, etc.)
     */
    protected function getCommonViewData($pageTitle = null): array
    {
        $data = [
            'profile' => $this->getProfile(),
            'parish' => [],
            'page_title' => $this->localizeHeaderTitle($pageTitle),
            'is_system_admin' => $this->isSystemAdmin(),
            'is_province_admin' => $this->isProvinceAdmin(),
            'is_diocesan_admin' => $this->isDioceseAdmin(),
            'scope_label' => lang('App.scopeParish'),
            'scope_icon' => 'bi-parish',
            'scope_name' => lang('App.scopeAllParishes'),
            'role_label' => $this->resolveLoggedInRoleLabel($this->user),
        ];

        $profileNavContext = $this->resolveProfileNavigationContext(
            $data['profile'],
            $this->getCurrentRegistrationRequest()
        );
        $data['profile_pending'] = $profileNavContext['profile_pending'];
        $data['profile_nav_url'] = $profileNavContext['profile_nav_url'];

        // Get current parish info
        $parishId = $this->getOperationalparishId();
        if ($parishId) {
            $parishModel = model('ParishModel');
            $data['parish'] = $parishModel->findWithHierarchy((int) $parishId) ?? $parishModel->find($parishId);
        }

        if ($this->isSystemAdmin()) {
            $data['scope_label'] = lang('App.scopeSystem');
            $data['scope_icon'] = 'bi-shield-lock';
            $data['scope_name'] = lang('App.scopeSystemGlobal');
        } elseif ($this->isProvinceAdmin()) {
            $data['scope_label'] = lang('App.scopeProvince');
            $data['scope_icon'] = 'bi-diagram-3';
            $provinceId = !empty($data['profile']['province_id'])
                ? (int) $data['profile']['province_id']
                : $this->resolveAdminProvinceId();

            if ($provinceId > 0) {
                $province = db_connect()->table('province')
                    ->select('name, province_number')
                    ->where('id', $provinceId)
                    ->get()
                    ->getRowArray();

                if (!empty($province['name'])) {
                    $provinceNumber = trim((string) ($province['province_number'] ?? ''));
                    $data['scope_name'] = $provinceNumber !== ''
                        ? (lang('App.scopeProvince') . ' ' . $provinceNumber . ' - ' . (string) $province['name'])
                        : (string) $province['name'];
                } else {
                    $data['scope_name'] = lang('App.scopeProvince');
                }
            } else {
                $data['scope_name'] = lang('App.scopeProvince');
            }
        } elseif ($this->isDioceseAdmin()) {
            $data['scope_label'] = lang('App.scopeDiocese');
            $data['scope_icon'] = 'bi-building-check';

            if (!empty($data['parish']['diocese_name'])) {
                $data['scope_name'] = $data['parish']['diocese_name'];
            } elseif (!empty($data['profile']['diocese_id'])) {
                $diocese = db_connect()->table('diocese')
                    ->select('name')
                    ->where('id', (int) $data['profile']['diocese_id'])
                    ->get()
                    ->getRowArray();

                $data['scope_name'] = $diocese['name'] ?? lang('App.scopeDiocese');
            } else {
                $data['scope_name'] = lang('App.scopeDiocese');
            }
        } elseif (!empty($data['parish']['name'])) {
            $data['scope_name'] = $data['parish']['name'];
        }

        // Province and diocesan admins can switch between parishes via the sidebar selector.
        // Scope the list to their own province / diocese so they cannot accidentally
        // select — and thereby view data for — a parish outside their authority.
        if ($this->isSystemAdmin()) {
            $data['all_parishes'] = [];
        } elseif ($this->isProvinceAdmin()) {
            $scopeProvinceId = $this->resolveAdminProvinceId();
            if ($scopeProvinceId > 0) {
                $cacheKey = 'base_all_parishes_province_' . $scopeProvinceId;
                $cache = \Config\Services::cache();
                $cached = $cache->get($cacheKey);
                if (is_array($cached)) {
                    $data['all_parishes'] = $cached;
                } else {
                    $allParishes = db_connect()->table('parish c')
                        ->select('c.id, c.name')
                        ->join('diocese d', 'd.id = c.diocese_id', 'left')
                        ->where('d.province_id', $scopeProvinceId)
                        ->orderBy('c.name', 'ASC')
                        ->get()
                        ->getResultArray();
                    $cache->save($cacheKey, $allParishes, 3600);
                    $data['all_parishes'] = $allParishes;
                }
            } else {
                $data['all_parishes'] = [];
            }
        } elseif ($this->isDioceseAdmin()) {
            $scopeDioceseId = 0;
            if (!empty($data['profile']['diocese_id'])) {
                $scopeDioceseId = (int) $data['profile']['diocese_id'];
            } elseif (!empty($data['parish']['diocese_id'])) {
                $scopeDioceseId = (int) $data['parish']['diocese_id'];
            }

            if ($scopeDioceseId > 0) {
                $cacheKey = 'base_all_parishes_diocese_' . $scopeDioceseId;
                $cache = \Config\Services::cache();
                $cached = $cache->get($cacheKey);
                if (is_array($cached)) {
                    $data['all_parishes'] = $cached;
                } else {
                    $allParishes = model('ParishModel')
                        ->where('diocese_id', $scopeDioceseId)
                        ->orderBy('name', 'ASC')
                        ->findAll();
                    $cache->save($cacheKey, $allParishes, 3600);
                    $data['all_parishes'] = $allParishes;
                }
            } else {
                $data['all_parishes'] = [];
            }
        }

        return $data;
    }

    /**
     * Return JSON response
     */
    protected function jsonResponse($data, $status = 200)
    {
        if (is_array($data)) {
            $data = $this->normalizeApiPayload($data, (int) $status);
        }

        return $this->response->setJSON($data)->setStatusCode($status);
    }

    /**
     * Return a 403 response for authorization failures.
     * Web requests get a framework 403 page; AJAX/JSON requests get JSON.
     */
    protected function forbiddenResponse(string $message = 'Forbidden')
    {
        $accept = (string) $this->request->getHeaderLine('Accept');

        if ($this->request->isAJAX() || str_contains($accept, 'application/json')) {
            return $this->jsonResponse(['success' => false, 'message' => $message], 403);
        }

        return $this->response
            ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
            ->setBody(view('errors/html/error_403', ['message' => $message]));
    }

    /**
     * Return a generic JSON error response and log the real exception.
     * Use this instead of exposing $e->getMessage() to clients.
     *
     * @param string          $clientMessage Safe message sent to the client.
     * @param int             $status        HTTP status code.
     * @param \Throwable|null $e             Exception to log server-side only.
     */
    protected function safeJsonError(string $clientMessage, int $status = 500, ?\Throwable $e = null)
    {
        if ($e !== null) {
            service('monitoring')->exception('controller.error', $e, [
                'controller' => static::class,
                'response_type' => 'json',
                'status' => $status,
            ]);
        }

        return $this->jsonResponse([
            'success' => false,
            'message' => $clientMessage,
            'error' => [
                'code' => 'GENERIC_ERROR',
                'message' => $clientMessage,
            ],
        ], $status);
    }

    /**
     * Build a standardized API error payload.
     *
     * @param array<string,mixed> $details
     */
    protected function apiErrorPayload(string $message, int $status, string $code = 'REQUEST_FAILED', array $details = []): array
    {
        $payload = [
            'success' => false,
            'status' => 'error',
            'message' => $message,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $payload['errors'] = $details;
            $payload['error']['details'] = $details;
        }

        $payload['meta'] = [
            'http_status' => $status,
            'ts' => date('c'),
        ];

        return $payload;
    }

    /**
     * Normalize API/AJAX JSON payloads to a consistent contract while staying
     * backward-compatible with existing front-end handlers.
     *
     * @param array<string,mixed>|list<mixed> $data
     * @return array<string,mixed>|list<mixed>
     */
    private function normalizeApiPayload(array $data, int $status): array
    {
        if (array_is_list($data)) {
            return $data;
        }

        $hasSuccess = array_key_exists('success', $data);
        $isError = $hasSuccess ? ((bool) $data['success'] === false) : ($status >= 400);
        $message = isset($data['message']) ? (string) $data['message'] : '';

        if ($isError) {
            $error = is_array($data['error'] ?? null) ? $data['error'] : [];
            if (!isset($error['code']) || $error['code'] === '') {
                $error['code'] = strtoupper((string) ($status >= 500 ? 'SERVER_ERROR' : 'REQUEST_FAILED'));
            }
            if (!isset($error['message']) || (string) $error['message'] === '') {
                $error['message'] = $message !== '' ? $message : 'Request failed.';
            }

            $data['success'] = false;
            $data['status'] = 'error';
            $data['message'] = (string) $error['message'];

            if (isset($data['errors']) && is_array($data['errors'])) {
                $error['details'] = $data['errors'];
            }

            $data['error'] = $error;
        } else {
            $data['success'] = $hasSuccess ? (bool) $data['success'] : true;
            $data['status'] = 'success';
        }

        if (!isset($data['meta']) || !is_array($data['meta'])) {
            $data['meta'] = [];
        }
        $data['meta']['http_status'] = $status;
        $data['meta']['ts'] = date('c');

        return $data;
    }

    /**
     * Return a generic redirect-back error and log the real exception.
     * Use this instead of exposing $e->getMessage() in flash messages.
     *
     * @param string          $clientMessage Safe message sent to the client.
     * @param \Throwable|null $e             Exception to log server-side only.
     */
    protected function safeRedirectError(string $clientMessage, ?\Throwable $e = null)
    {
        if ($e !== null) {
            service('monitoring')->exception('controller.error', $e, [
                'controller' => static::class,
                'response_type' => 'redirect',
            ]);
        }

        return redirect()->back()->withInput()->with('error', $clientMessage);
    }

    /**
     * Normalize email input consistently before persistence/lookup.
     */
    protected function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Check whether an email_password identity already exists.
     */
    protected function emailIdentityExists(string $email): bool
    {
        $normalized = $this->normalizeEmail($email);

        return db_connect()
            ->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', $normalized)
            ->limit(1)
            ->get()
            ->getRow() !== null;
    }

    /**
     * Shared handler for creating non-portal standalone members/people.
     * Used by both Members and People controllers.
     * Caller must handle registry sync if needed.
     *
     * @param int    $isMember        1 for members, 0 for non-members/dependents
     * @param string $redirectPath    e.g., 'members', 'people'
     * @param string $auditEventName  e.g., 'member.create_non_portal'
     * @param array  $auditNewValues  Additional fields for audit log 'new_values'
     * @return mixed                  Redirect response on success/error
     */
    protected function storeNonPortalPerson(
        int $isMember,
        string $redirectPath,
        string $auditEventName,
        array $auditNewValues = []
    ) {
        if ($redirect = $this->requirePermission('member.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/' . $redirectPath)
                ->with('error', lang('Members.provinceAdminRestriction'));
        }

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'nickname' => 'permit_empty|max_length[100]',
            'birth_date' => 'permit_empty|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
            'username' => 'permit_empty|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->to('/' . $redirectPath)
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/' . $redirectPath)
                ->with('error', lang('Members.noParishSelected'));
        }

        $payload = [
            'first_name' => (string) $this->request->getPost('first_name'),
            'last_name' => (string) $this->request->getPost('last_name'),
            'nickname' => (string) ($this->request->getPost('nickname') ?? ''),
            'birth_date' => (string) ($this->request->getPost('birth_date') ?? ''),
            'username' => (string) ($this->request->getPost('username') ?? ''),
            'portal_access' => 'none',
            'is_member' => $isMember,
        ];

        $provisioningService = new \App\Services\NonPortalMemberProvisioningService();
        $result = $provisioningService->create($payload, $parishId);
        if (!($result['ok'] ?? false)) {
            $message = (string) ($result['message'] ?? lang('Members.nonPortalCreateFailed'));
            return redirect()->to('/' . $redirectPath)
                ->withInput()
                ->with('error', $message);
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        $auditValues = array_merge([
            'is_member' => $isMember,
            'portal_access' => 'none',
        ], $auditNewValues);

        $this->audit()->log($auditEventName, [
            'parish_id' => $parishId,
            'entity_type' => $isMember === 1 ? 'member' : 'person',
            'entity_id' => (int) ($data['user_id'] ?? 0),
            'new_values' => $auditValues,
        ]);

        return [
            'parishId' => $parishId,
            'userId' => (int) ($data['user_id'] ?? 0),
            'redirectPath' => '/' . $redirectPath,
        ];
    }

    /**
     * Get the current DB-driven password policy.
     */
    protected function getPasswordPolicy(): array
    {
        return service('systemSettings')->getPasswordPolicy();
    }

    /**
     * Validate a password against the DB-driven policy.
     * Returns ['ok' => true] on success, or ['ok' => false, 'errors' => [...]].
     */
    protected function validateAdminPassword(string $password): array
    {
        $policy = $this->getPasswordPolicy();
        $errors = [];
        $minLength = (int) ($policy['min_length'] ?? 12);

        if (strlen($password) < $minLength) {
            $errors[] = lang('System.passwordTooShort', [$minLength]);
        }

        if (($policy['require_uppercase'] ?? true) && !preg_match('/[A-Z]/', $password)) {
            $errors[] = lang('System.passwordRequiresUppercase');
        }

        if (($policy['require_lowercase'] ?? true) && !preg_match('/[a-z]/', $password)) {
            $errors[] = lang('System.passwordRequiresLowercase');
        }

        if (($policy['require_digit'] ?? true) && !preg_match('/\d/', $password)) {
            $errors[] = lang('System.passwordRequiresDigit');
        }

        if ($policy['require_special'] ?? true) {
            $specialChars = $policy['special_chars'] ?? '@$!%*?&#^_-';
            $pattern = '/[' . preg_quote($specialChars, '/') . ']/';
            if (!preg_match($pattern, $password)) {
                $errors[] = lang('System.passwordRequiresSpecial');
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }
}
