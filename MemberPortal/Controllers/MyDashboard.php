<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Services\MemberDashboardDataService;

/**
 * Personal dashboard for the logged-in user.
 *
 * Surfaces the user's own membership status, sacraments, leadership
 * positions, family, pledges, giving history, attendance, and onboarding
 * milestones — all sourced from existing per-person services/models.
 */
class MyDashboard extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();

        if (!$this->canAccessMyDashboard($user)) {
            return redirect()->to('/profile')->with('error', lang('Auth.notEnoughPrivilege'));
        }

        // Resolve the user's canonical person_id and parish_id from profile.
        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();

        if (!$profile) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $personId = (int) ($profile['person_id'] ?? 0);
        $parishId = (int) ($profile['parish_id'] ?? 0);

        // If the profile has no person_id, try to auto-repair the link.
        if ($personId <= 0) {
            $personId = $this->ensureProfilePersonLink((int) $profile['id'], (int) $user->id);
        }

        if ($personId <= 0 || $parishId <= 0) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $service = new MemberDashboardDataService();
        $data    = $service->assemble((int) $user->id, $personId, $parishId);

        $viewData = array_merge($this->getCommonViewData(lang('Dashboard.myDashboard')), $data);

        return view('MemberPortal\Views\MyDashboard\index', $viewData);
    }

    /**
     * Attempt to ensure a profile has a person_id link.
     * Returns the resolved person_id (0 if unsuccessful).
     */
    private function ensureProfilePersonLink(int $profileId, int $userId): int
    {
        // Delegate to the same logic used in Clergy\Controllers\People::view
        // by calling the People controller's helper if available.
        // For now, try a direct lookup via user_person mapping.
        $db = db_connect();

        $personRow = $db->table('user_person')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($personRow) {
            $personId = (int) ($personRow['person_id'] ?? 0);
            if ($personId > 0) {
                $db->table('profile')->where('id', $profileId)->update(['person_id' => $personId]);
                return $personId;
            }
        }

        return 0;
    }
}
