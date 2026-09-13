<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RegistrationGateFilter
 *
 * Enforces onboarding status rules for every session-protected request.
 * Running at the middleware layer means a banned, dismissed, or profile-incomplete
 * user is blocked from ALL app routes — not only from / and /dashboard.
 *
 * Excluded routes (configured in Config/Filters.php $globals except list):
 *   - Shield auth routes   (login, register, logout, auth/*, …)
 *   - profile/complete     (to avoid a redirect loop for pending_profile users)
 *   - uploads/*            (served directly; no onboarding relevance)
 *
 * Status handling:
 *   - No registration record  → pass through (seeded/admin users)
 *   - dismissed | banned      → logout and redirect to /login
 *   - pending_profile         → redirect to /profile/complete
 *   - pending_review          → pass through (controllers render their own view)
 *   - approved                → pass through
 */
class RegistrationGateFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        
        // Unauthenticated requests are handled by the session filter; nothing to gate here.
        if (!auth()->loggedIn()) {
            return null;
        }

        // Deactivated accounts should not be able to reach any authenticated page.
        $user = auth()->user();
        if ($user !== null && $user->active === false) {
            $userId = (int) auth()->id();
            auth()->logout();
            session()->destroy();

            service('audit')->log('auth.blocked.inactive', [
                'user_id'     => $userId,
                'entity_type' => 'user',
                'entity_id'   => $userId,
                'meta'        => ['reason' => 'inactive', 'path' => uri_string()],
            ]);

            return redirect()->to('/login')->with('error', lang('Auth.accountInactive'));
        }

        // If the onboarding table doesn't exist yet, seeded/admin users should still pass through.
        try {
            if (! db_connect()->tableExists('user_registration_request')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        try {
            $registrationRequest = model('UserRegistrationRequestModel')
                ->where('user_id', (int) auth()->id())
                ->first();
        } catch (\Throwable $e) {
            // If the onboarding table does not exist yet, seeded/admin users
            // should still be allowed through rather than showing a blank page.
            log_message('error', 'RegistrationGateFilter could not query user_registration_request: ' . $e->getMessage());

            return null;
        }

        // Users without an onboarding record (e.g. database-seeded admins) pass through.
        if ($registrationRequest === null) {
            return null;
        }

        $status = (string) ($registrationRequest['status'] ?? '');

        if (in_array($status, ['dismissed', 'banned'], true)) {
            $userId = (int) auth()->id();
            auth()->logout();
            session()->destroy();

            service('audit')->log('auth.blocked.onboarding', [
                'user_id'     => $userId,
                'entity_type' => 'user',
                'entity_id'   => $userId,
                'meta'        => ['status' => $status, 'path' => uri_string()],
            ]);

            return redirect()->to('/login')->with('error', lang('Auth.accountBanned'));
        }

        $isStaffAdmin = $user->inGroup('systemadmin')
            || $user->inGroup('provinceadmin')
            || $user->inGroup('dioceseadmin')
            || $user->inGroup('parishadmin');

        if ($status === 'pending_profile' && !$isStaffAdmin) {
            return redirect()->to('/profile/complete');
        }

        // pending_review and approved pass through — controllers handle their own view logic.
        // Staff admin accounts are also allowed through even when their registration request
        // is pending_profile, because they are not member-onboarding users.
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
