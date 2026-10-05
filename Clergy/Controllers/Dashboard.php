<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * Clergy portal dashboard.
 *
 * Landing page for clergy accounts — especially clergy invited by their
 * diocese who are not yet appointed to a parish. Shows a profile-completion
 * prompt, canonical order/standing summary, and an "awaiting parish
 * appointment" state when no active leadership_position exists.
 */
class Dashboard extends BaseController
{
    public function index()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        if (! $user->inGroup('clergy')) {
            return redirect()->to($this->resolveDashboardScope($user)['route']);
        }

        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first() ?: [];
        $personId = (int) ($profile['person_id'] ?? 0);
        $dioceseId = (int) ($profile['diocese_id'] ?? 0);

        $db = db_connect();

        $positions = [];
        if ($personId > 0 || (int) $user->id > 0) {
            $positions = model('LeadershipPositionModel')->getActivePositionsForUser((int) $user->id);
        }

        $ordinations = $personId > 0
            ? $db->table('clergy_ordination')->where('person_id', $personId)->orderBy('ordained_on', 'ASC')->get()->getResultArray()
            : [];

        $standings = [];
        if ($personId > 0) {
            $standingBuilder = $db->table('clergy_canonical_status')->where('person_id', $personId);
            if ($dioceseId > 0) {
                $standingBuilder->where('diocese_id', $dioceseId);
            }
            $standings = $standingBuilder->orderBy('effective_from', 'DESC')->limit(5)->get()->getResultArray();
        }

        return view('Clergy\Views\Dashboard/index', array_merge(
            $this->getCommonViewData(lang('ClergyPortal.dashboardTitle')),
            [
                'positions' => $positions,
                'ordinations' => $ordinations,
                'standings' => $standings,
                'awaitingAppointment' => count($positions) === 0,
                'profileIncomplete' => $this->profileIncomplete($profile),
            ]
        ));
    }

    private function profileIncomplete(array $profile): bool
    {
        foreach (['first_name', 'last_name', 'phone', 'address', 'city'] as $field) {
            if (trim((string) ($profile[$field] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }
}
