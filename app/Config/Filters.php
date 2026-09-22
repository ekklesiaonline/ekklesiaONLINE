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
use App\Filters\DiocesanScopeFilter;
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
        'diocesanScope'    => DiocesanScopeFilter::class,
        'parishScope'      => ParishScopeFilter::class,
        'diocesanOperationalBoundary' => DiocesanOperationalBoundaryFilter::class,
        'adminFinanceBoundary' => AdminFinanceBoundaryFilter::class,
        'permission'       => \App\Filters\PermissionFilter::class,
        'session'          => \CodeIgniter\Shield\Filters\SessionAuth::class,
        'loginRateLimit'    => LoginRateLimitFilter::class,
        'apiRateLimit'      => ApiRateLimitFilter::class,
        'requestMonitoring' => RequestMonitoringFilter::class,
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
                    'auth/*',
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
            // 'honeypot',
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
                    'auth/*',
                    'profile/complete',
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
                    'auth/*',
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
                    'auth/*',
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
                    'auth/*',
                    'session/device-verify',
                    'session/device-verify/resend',
                    'locale/set/*',
                    'uploads/*',
                    'health',
                ],
            ],
        ],
        'after' => [
            // 'honeypot',
            'secureheaders',
            'requestMonitoring',
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
        'diocesanScope' => [
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
            ],
        ],
        // Diocesan admins are restricted from parish-operator modules.
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
                'leadership',
                'leadership/*',
                'followup',
                'followup/*',
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
                'pledges',
                'pledges/*',
                'diocesan/finance',
                'diocesan/finance/*',
                'treasurer',
                'treasurer/*',
            ],
        ],
        'loginRateLimit' => [
            'before' => [
                'login',
                'login/magic-link',
                'register',
                'session/device-verify',
                'session/device-verify/resend',
            ],
        ],
        // API abuse protection for high-risk write endpoints.
        'apiRateLimit' => [
            'before' => [
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
                'families/store',
                'families/update/*',
                'families/delete/*',
                'families/addMember',
                'families/removeMember/*',
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
            ],
        ],
    ];
}
