<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;

/**
 * Module-owned public entry/controller surface.
 */
class PublicHome extends BaseController
{
    public function index()
    {
        // The RegistrationGateFilter runs before this controller and handles
        // banned/dismissed/pending_profile users. For authenticated users that
        // pass the gate, resolve their role-specific landing path.
        if (auth()->loggedIn()) {
            $user = auth()->user();
            $registrationRequest = $this->getCurrentRegistrationRequest();

            // Users awaiting admin review should see a dedicated hold page, not a dashboard.
            // Elevated admins are not subject to parish-level review lockout.
            if ($this->getRegistrationStatus($registrationRequest) === 'pending_review' && !$this->canBypassPendingReviewGate($user)) {
                $userId = (int) auth()->id();
                service('audit')->log('auth.pending_review.landing', [
                    'user_id'     => $userId,
                    'entity_type' => 'user',
                    'entity_id'   => $userId,
                    'meta'        => ['path' => uri_string()],
                ]);

                return view('MemberPortal\Views\Auth\pending_approval');
            }

            // Avoid redirect loops when an authenticated account cannot access
            // /dashboard (permission/group mismatch or stale authorization state).
            // In that case route to profile context instead of bouncing to /dashboard.
            if ($user !== null && !$this->canLandOnDashboard($user)) {
                $profileNavContext = $this->resolveProfileNavigationContext(
                    $this->getProfile(),
                    $registrationRequest
                );
                return redirect()->to(base_url((string) $profileNavContext['profile_nav_url']));
            }
            $landingpage = $this->resolveDashboardRedirectPath($user, $registrationRequest);
            return redirect()->to($landingpage);
        }

        return view('home/index');
    }

    protected function resolveDashboardRedirectPath($user, ?array $registrationRequest = null): string
    {
        if ($user === null) {
            return '/dashboard';
        }

        $landingPath = $this->resolveAuthenticatedLandingPath($user, $registrationRequest);

        // Profile completion is a gated non-dashboard destination that must be reachable
        // without an extra permission check because it is itself the access control gate.
        if ($landingPath === '/profile/complete') {
            return $landingPath;
        }

        // Every other landing path must pass the access check for that path.
        if ($this->canAccessDashboardPath($user, $landingPath)) {
            return $landingPath;
        }

        // Final fallback to the generic member dashboard if the user is allowed there.
        if ($this->canLandOnDashboard($user)) {
            return '/dashboard';
        }

        $profileNavContext = $this->resolveProfileNavigationContext(
            $this->getProfile(),
            $registrationRequest
        );

        return base_url((string) $profileNavContext['profile_nav_url']);
    }
}
