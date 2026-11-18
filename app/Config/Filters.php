<?php

namespace Config;

use App\Filters\LocaleFilter;
use App\Filters\LoginRateLimitFilter;
use App\Filters\ApiRateLimitFilter;
use App\Filters\RequestMonitoringFilter;
use App\Filters\RegistrationGateFilter;
use App\Filters\DeviceVerificationFilter;
use App\Filters\SessionTrackingFilter;
use App\Filters\ProvinceScopeFilter;
use App\Filters\DioceseScopeFilter;
use App\Filters\ParishScopeFilter;
use App\Filters\DiocesanOperationalBoundaryFilter;
use App\Filters\AdminFinanceBoundaryFilter;
use App\Filters\MaintenanceMode;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Shield\Filters\ForcePasswordResetFilter;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'             => CSRF::class,
        'toolbar'          => DebugToolbar::class,
        'honeypot'         => Honeypot::class,
        'invalidchars'     => InvalidChars::class,
        'secureheaders'    => SecureHeaders::class,
        'cors'             => Cors::class,
        'forcehttps'       => ForceHTTPS::class,
        'pagecache'        => PageCache::class,
        'performance'      => PerformanceMetrics::class,
        'localeSet'        => LocaleFilter::class,
        'registrationGate' => RegistrationGateFilter::class,
        'provinceScope'    => ProvinceScopeFilter::class,
        'dioceseScope'     => DioceseScopeFilter::class,
        'parishScope'      => ParishScopeFilter::class,
        'diocesanOperationalBoundary' => DiocesanOperationalBoundaryFilter::class,
        'adminFinanceBoundary' => AdminFinanceBoundaryFilter::class,
        'permission'       => \App\Filters\PermissionFilter::class,
        'clergyScope'      => \App\Filters\ClergyScopeFilter::class,
        'session'          => \CodeIgniter\Shield\Filters\SessionAuth::class,
        'group'            => \CodeIgniter\Shield\Filters\GroupFilter::class,
        'loginRateLimit'    => LoginRateLimitFilter::class,
        'apiRateLimit'      => ApiRateLimitFilter::class,
        'requestMonitoring' => RequestMonitoringFilter::class,
        'csrfTokenHeader'   => \App\Filters\CsrfTokenHeaderFilter::class,
        'sessionTracking'   => SessionTrackingFilter::class,
        'deviceVerification' => DeviceVerificationFilter::class,
        'forcePasswordReset' => ForcePasswordResetFilter::class,
        'maintenanceMode' => MaintenanceMode::class,
    ];

    public function __construct()
    {
        parent::__construct();

        if (ENVIRONMENT === 'testing') {
            // Keep test HTTP flows focused on route/controller behavior.
            // Expensive cross-cutting filters are covered separately.
            $this->required = [
                'before' => [],
                'after' => [],
            ];

            $this->globals = [
                'before' => [
                    'csrf',
                    'localeSet',
                ],
                'after' => [],
            ];
        }
    }

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Force Global Secure Requests
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'pagecache',   // Web Page Caching
            'performance', // Performance Metrics
            'toolbar',     // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            'maintenanceMode' => [
                'except' => [
                    'login',
                    'logout',
                    'auth/a/*',
                    'auth/set-password',
                    'assets/*',
                    'system/settings',
                    'health',
                ],
            ],
            // session/device-verify POST is CSRF-protected: the challenge form
            // is rendered after the session exists, so csrf_field() emits a
            // valid token.
            'csrf',
            'localeSet',
            'honeypot',
            // 'invalidchars',

            // Enforce onboarding status (banned/dismissed/pending_profile) on every
            // authenticated request — before any controller logic runs.
            // Excluded: Shield auth pages and /profile/complete (avoid redirect loop).
            'registrationGate' => [
                'except' => [
                    'login',
                    'register',
                    'logout',
                    'forgot-password',
                    'reset-password',
                    'auth/a/*',
                    'auth/set-password',
                    'profile/complete',
                    'locale/set/*',
                    'uploads/*',
                    'health',
                ],
            ],

            // Track authenticated sessions for concurrent-session limits and
            // new-device detection. Returns null early for unauthenticated requests,
            // so it is safe to run globally. Excluded only for truly public paths.
            'sessionTracking' => [
                'except' => [
                    'login',
                    'register',
                    'logout',
                    'forgot-password',
                    'reset-password',
                    'auth/a/*',
                    'auth/set-password',
                    'profile/complete',
                    'uploads/*',
                    'health',
                    'locale/set/*',
                ],
            ],

            // Enforce password reset immediately for accounts that are flagged
            // for mandatory reset (including magic-link authenticated sessions).
            'forcePasswordReset' => [
                'except' => [
                    'login',
                    'login/magic-link',
                    'login/verify-magic-link',
                    'register',
                    'logout',
                    'forgot-password',
                    'reset-password',
                    'auth/a/*',
                    'auth/set-password',
                    'profile/complete',
                    'uploads/*',
                    'health',
                    'locale/set/*',
                ],
            ],

            // Force challenge completion when a login is detected from a new device.
            // Exclude verification and auth endpoints to avoid redirect loops.
            'deviceVerification' => [
                'except' => [
                    'login',
                    'logout',
                    'auth/a/*',
                    'auth/set-password',
                    'session/device-verify',
                    'session/device-verify/resend',
                    'locale/set/*',
                    'uploads/*',
                    'health',
                ],
            ],
        ],
        'after' => [
            'honeypot',
            'secureheaders',
            'requestMonitoring',
            // Returns the rotated CSRF token on AJAX responses so pages can
            // refresh their meta tag (session tokens regenerate per request).
            'csrfTokenHeader',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profile/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [
        // Enforce strict province boundary checks for province-admin workflows,
        // including shared routes used across modules.
        'provinceScope' => [
            'before' => [
                'province/*',
                'parishes',
                'parishes/*',
                'parish_list',
                'parish_list/*',
                'dashboard/switch/*',
            ],
        ],
        // Enforce strict diocesan boundary checks for diocesan-admin workflows,
        // including shared routes used across modules.
        'dioceseScope' => [
            'before' => [
                'parish_list',
                'parish_list/*',
                'dashboard/switch/*',
                'chart-accounts',
                'chart-accounts/*',
                'liturgical-source',
                'liturgical-source/*',
            ],
        ],
        // Enforce strict parish scope for parish-level workflows.
        // Province/diocesan admin routes are safely skipped by the filter itself.
        'parishScope' => [
            'before' => [
                'members',
                'members/*',
                'services',
                'services/*',
                'attendance',
                'attendance/*',
                'guests',
                'guests/*',
                'visitors',
                'visitors/*',
                'followup',
                'followup/*',
                'groups',
                'groups/*',
                'messages',
                'messages/*',
                'donations',
                'donations/*',
                'expenses',
                'expenses/*',
                'budgets',
                'budgets/*',
                'banking',
                'banking/*',
                'reports',
                'reports/*',
                'volunteers',
                'volunteers/*',
                'families',
                'families/*',
                'leadership',
                'leadership/*',
                'people',
                'people/*',
                'followup-workflow',
                'followup-workflow/*',
                'vestry',
                'vestry/*',
                'event-calendars',
                'event-calendars/*',
                'parochial-report',
                'parochial-report/*',
                'worship-planning',
                'worship-planning/*',
            ],
        ],
        // Diocesan admins are restricted from parish-operator modules.
        // Leadership is shared with diocesan admins; its permission route and
        // controller enforce an explicitly assigned diocese and target scope.
        'diocesanOperationalBoundary' => [
            'before' => [
                'members',
                'members/*',
                'families',
                'families/*',
                'services',
                'services/*',
                'guests',
                'guests/*',
                'visitors',
                'visitors/*',
                'guest-conversions',
                'visitor-conversions',
                'standing',
                'standing/*',
                'followup',
                'followup/*',
                'people',
                'people/*',
                'followup-workflow',
                'followup-workflow/*',
                'vestry',
                'vestry/*',
                'groups',
                'groups/*',
                'messages',
                'messages/*',
                'volunteers',
                'volunteers/*',
                'attendance',
                'attendance/*',
                'parochial-report',
                'parochial-report/*',
                'worship-planning',
                'worship-planning/*',
            ],
        ],
        // Province and diocesan admins are restricted from finance modules.
        'adminFinanceBoundary' => [
            'before' => [
                'reports',
                'reports/*',
                'donations',
                'donations/*',
                'expenses',
                'expenses/*',
                'budgets',
                'budgets/*',
                'banking',
                'banking/*',
                'funds',
                'funds/*',
                'pledges',
                'pledges/*',
                'treasurer',
                'treasurer/*',
                'journal-entries',
                'journal-entries/*',
                'accounting-periods',
                'accounting-periods/*',
                'finance/chart-of-accounts',
                'finance/chart-of-accounts/*',
                // Donation/pledge mutations also live under the unified People
                // directory; same finance tenant separation applies.
                'people/donation',
                'people/donation/*',
                'people/pledge',
                'people/pledge/*',
            ],
        ],
        'loginRateLimit' => [
            'before' => [
                'login',
                'login/magic-link',
                'register',
                'auth/set-password',
                'auth/a/*',
                'session/device-verify',
                'session/device-verify/resend',
                'connect/*',
            ],
        ],
        // API abuse protection for high-risk write endpoints.
        'apiRateLimit' => [
            'before' => [
                'connect',
                'connect/*',
                'members/store',
                'members/update/*',
                'members/attach-parish',
                'members/detach-parish',
                'members/set-home-parish',
                'members/registrations/*',
                'members/delete/*',
                'guests/store',
                'guests/update/*',
                'guests/delete/*',
                'visitors/store',
                'visitors/update/*',
                'visitors/delete/*',
                'followup/store',
                'followup/update-status/*',
                'followup/delete/*',
                'events/store',
                'events/update/*',
                'events/delete/*',
                'events/register/*',
                'events/registrations/*/status',
                'services/store',
                'services/update/*',
                'services/delete/*',
                'attendance/mark',
                'attendance/bulk-mark',
                'donations/store',
                'donations/delete/*',
                'expenses/store',
                'expenses/update/*',
                'expenses/delete/*',
                'budgets/save',
                'volunteers/add-member',
                'volunteers/remove-member/*',
                'volunteers/store-team',
                'volunteers/schedules/store',
                'volunteers/schedules/update/*',
                'volunteers/schedules/cancel/*',
                'volunteers/schedules/confirm/*',
                // Legacy alias mutation routes — kept in parity with canonical paths
                'volunteers/storeTeam',
                'volunteers/store-schedule',
                'volunteers/update-schedule/*',
                'volunteers/cancel-schedule/*',
                'volunteers/confirm-schedule/*',
                'families/store',
                'families/update/*',
                'families/delete/*',
                'families/addMember',
                'families/removeMember/*',
                'families/addMemberNonPortal',
                'families/enablePortal/*',
                'system/admins/store-system',
                'system/admins/store-province',
                'system/admins/store-diocese',
                'system/admins/update/*',
                'system/admins/delete/*',
                'system/admins/reset-password/*',
                'system/admins/transfer-primary/*',
                'dashboard/switch/*',
                'pledges/store',
                'pledges/update/*',
                'pledges/delete/*',
                'me/pledge',
                'funds/setup',
                'funds/store',
                'funds/update/*',
                'funds/lock/*',
                'funds/unlock/*',
                'funds/activate/*',
                'funds/deactivate/*',
                'banking/store',
                'banking/update/*',
                'banking/toggle-active/*',
                'banking/*/transactions',
                'banking/import',
                'banking/*/import',
                'banking/*/reconciliations',
                'banking/reconciliations/*/items',
                'banking/reconciliations/*/close',
                'accounting-periods/generate',
                'accounting-periods/store',
                'accounting-periods/close/*',
                'accounting-periods/reopen/*',
                'finance/chart-of-accounts/store',
                'finance/chart-of-accounts/update/*',
                'finance/chart-of-accounts/toggle/*',
                'donations/batch-statements',
                'budgets/clone',
                'leadership/store',
                'leadership/update/*',
                'leadership/delete/*',
                'standing/refresh',
                'profile/update',
                'profile/delete-picture',
                'profile/complete',
                'profile/join-parish',
                'visitors/promote/*',
                'visitors/revert-conversion/*',
                'visitors/promote-attender/*',
                'visitors/demote/*',
                // Clergy: bulk, destructive, and outbound-send endpoints.
                'members/bulk-delete',
                'members/dedup/*',
                'members/store-non-portal',
                'members/revoke-portal-access/*',
                'people/enable-portal/*',
                'people/toggle-member-tag/*',
                'people/bulk-toggle-member-tag',
                'people/store-non-portal',
                'people/save-membership-details/*',
                'people/leadership/*',
                'people/donation/*',
                'people/pledge/*',
                'people/sacrament/*',
                'people/family/*',
                'people/export',
                'people/bulk-delete',
                'people/merge/execute',
                'visitors/import/process',
                'visitors/import/rotate-token',
                'visitors/notes/add/*',
                'visitors/followup-trigger/*',
                'visitors/assign-sequence/*',
                'visitors/followup-log/*',
                'guests/import/process',
                'guests/followup-trigger/*',
                'guests/assign-sequence/*',
                'guests/followup-log/*',
                'messages/store',
                'messages/send/*',
                'messages/cancel/*',
                'messages/templates/save',
                'messages/templates/save/*',
                'messages/templates/delete/*',
                'groups/store',
                'groups/update/*',
                'groups/delete/*',
                'groups/members/*',
                'event-calendars/store',
                'followup-workflow/sequences/save',
                'followup-workflow/sequences/save/*',
                'followup-workflow/sequences/toggle/*',
                'followup-workflow/sequences/set-default/*',
                'followup-workflow/sequences/delete/*',
                'followup-workflow/sequences/clone/*',
                'followup-workflow/actions/save',
                'followup-workflow/actions/save/*',
                'followup-workflow/actions/delete/*',
                'followup-workflow/templates/save',
                'followup-workflow/templates/save/*',
                'followup-workflow/templates/delete/*',
                'followup-workflow/manual-trigger/*',
                'followup-workflow/tasks/complete/*',
                'followup-workflow/call-tasks/complete/*',
                'vestry/store',
                'vestry/meeting/*/motion',
                'vestry/motion/*/vote',
                'vestry/motion/*/close',
                // Worship: report writes and outbound report sends.
                'parochial-report/save',
                'parochial-report/email-pdf',
                // Legacy camelCase alias — bypassed both the URI list and
                // the bulk-mark path limit.
                'attendance/bulkMark',
                'worship-planning/service/*/save',
                'worship-planning/songs/store',
                'worship-planning/songs/update/*',
                'worship-planning/songs/deactivate/*',
                'worship-planning/songs/reactivate/*',
                'worship-planning/songs/delete/*',
                // Parish: diocesan-queue writes and outbound test sends.
                'parish/clergy/request',
                'parish/email-settings/test',
                'diocese/email-settings/test',
                'province/email-settings/test',
                // Diocese: clergy account writes, invitation resends, and the
                // ordinary/delegation review queue.
                'diocese/clergy/store',
                'diocese/clergy/*/edit',
                'diocese/clergy/*/deactivate',
                'diocese/clergy/*/resend-invitation',
                'diocese/clergy-review/*',
                // Diocese: admin account mutations (outbound credential mail).
                'diocese/admins/*',
                'diocese/parish-admins/*',
                // Diocese: chart of accounts, deaneries, congregations.
                'chart-accounts/store',
                'chart-accounts/update/*',
                'chart-accounts/toggle/*',
                'diocese/deaneries/store',
                'diocese/deaneries/assign/*',
                'diocese/deaneries/end/*',
                'diocese/congregations/*/type',
                // Diocese: follow-up workflow + email-settings save.
                'diocese/followup-workflow/sequences/*',
                'diocese/followup-workflow/actions/*',
                'diocese/followup-workflow/templates/*',
                'diocese/email-settings',
                // Diocese-owned parish writes (canonical + staff).
                'parish_list/store',
                'parish_list/update/*',
                'parish_list/delete/*',
                'parish_list/bulk-delete',
                'parish_list/*/staff/add',
                'parish_list/*/staff/promote',
                'parish_list/*/staff/remove-role',
                'parish_list/*/staff/demote',
                // Diocese module writes served under the system group.
                'system/liturgical-source/store',
                'system/liturgical-source/update/*',
                'system/liturgical-source/delete/*',
            ],
        ],
    ];
}
