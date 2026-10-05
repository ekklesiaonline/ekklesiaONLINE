<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;

/**
 * Parish read-only clergy visibility + appointment (hire) requests.
 *
 * Parishes never create clergy accounts and never write diocese-owned tables
 * (clergy_ordination, clergy_canonical_status, approved leadership_position
 * rows for clergy offices). This controller exposes only:
 *  - a read-only list of clergy appointed to the operational parish;
 *  - a request form that inserts a pending clergy_appointment_request for
 *    the diocesan review queue — nothing else is written.
 */
class Clergy extends BaseController
{
    private const PROPOSED_OFFICES = ['rector', 'priest_in_charge', 'interim_rector', 'vicar'];

    private function operationalParish(): ?array
    {
        if (! auth()->loggedIn()) {
            return null;
        }
        if ($this->requirePermission('member.view')) {
            return null;
        }
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return null;
        }

        return model('ParishModel')->find($parishId) ?: null;
    }

    public function index()
    {
        $parish = $this->operationalParish();
        if ($parish === null) {
            return redirect()->to('/parish/dashboard')->with('error', lang('App.accessDenied'));
        }

        $parishId = (int) $parish['id'];
        $db = db_connect();

        $clergy = $db->table('leadership_position lp')
            ->select('lp.id AS position_id, lp.title, lp.role_code, lp.clergy_order, lp.started_at,
                p.first_name, p.last_name, p.phone, i.secret AS email, per.id AS person_id')
            ->join('profile p', 'p.user_id = lp.user_id', 'left')
            ->join('person per', 'per.id = lp.person_id', 'left')
            ->join('users u', 'u.id = lp.user_id', 'left')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('lp.scope_type', 'parish')
            ->where('lp.parish_id', $parishId)
            ->where('lp.active', 1)
            ->where('lp.role_category', 'clergy')
            ->where('lp.revoked_at', null)
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->get()->getResultArray();

        $requests = [];
        if ($db->tableExists('clergy_appointment_request')) {
            $requests = $db->table('clergy_appointment_request r')
                ->select('r.id, r.review_status, r.proposed_office, r.created_at, r.reviewed_at,
                    per.first_name, per.last_name')
                ->join('person per', 'per.id = r.person_id', 'left')
                ->where('r.parish_id', $parishId)
                ->orderBy('r.created_at', 'DESC')
                ->limit(25)->get()->getResultArray();
        }

        return view('Parish\Views\Clergy/index', array_merge(
            $this->getCommonViewData(lang('ParishClergy.pageTitle')),
            [
                'clergy' => $clergy,
                'requests' => $requests,
                'parish' => $parish,
                'officeOptions' => self::PROPOSED_OFFICES,
            ]
        ));
    }

    /**
     * Diocese-scoped person picker for the request form: persons in the
     * operational parish's diocese canonical registry with a linked account.
     */
    public function personSearch()
    {
        $parish = $this->operationalParish();
        if ($parish === null) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }

        $dioceseId = (int) ($parish['diocese_id'] ?? 0);
        $q = trim((string) $this->request->getGet('q'));

        $persons = array_values(array_filter(
            db_connect()->table('person p')
                ->distinct()
                ->select('p.id, p.first_name, p.last_name')
                ->join('user_person up', 'up.person_id = p.id', 'inner')
                ->groupStart()
                    ->like('p.first_name', $q)
                    ->orLike('p.last_name', $q)
                ->groupEnd()
                ->orderBy('p.last_name', 'ASC')
                ->orderBy('p.first_name', 'ASC')
                ->limit(50)
                ->get()->getResultArray(),
            static fn (array $person): bool => self::personInRegistry((int) $person['id'], $dioceseId)
        ));

        return $this->jsonResponse(['success' => true, 'data' => array_slice($persons, 0, 25)]);
    }

    /**
     * Submit a hire request: inserts review_status='pending' only. No
     * leadership_position activation, no writes to ordination/standing tables.
     */
    public function submitRequest()
    {
        $parish = $this->operationalParish();
        if ($parish === null) {
            return redirect()->to('/parish/dashboard')->with('error', lang('App.accessDenied'));
        }

        $dioceseId = (int) ($parish['diocese_id'] ?? 0);
        if ($dioceseId <= 0 || ! db_connect()->tableExists('clergy_appointment_request')) {
            return redirect()->to('/parish/clergy')->with('error', lang('ParishClergy.requestUnavailable'));
        }

        if (! $this->validate([
            'person_id' => 'required|is_natural_no_zero|is_not_unique[person.id]',
            'proposed_office' => 'required|in_list[' . implode(',', self::PROPOSED_OFFICES) . ']',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $personId = (int) $this->request->getPost('person_id');
        if (! self::personInRegistry($personId, $dioceseId)) {
            return redirect()->back()->withInput()->with('errors', [
                'person_id' => lang('ParishClergy.personOutsideDiocese'),
            ]);
        }

        $db = db_connect();
        $pending = $db->table('clergy_appointment_request')
            ->where('person_id', $personId)
            ->where('parish_id', (int) $parish['id'])
            ->where('review_status', 'pending')
            ->countAllResults();
        if ($pending > 0) {
            return redirect()->to('/parish/clergy')->with('error', lang('ParishClergy.requestAlreadyPending'));
        }

        $db->table('clergy_appointment_request')->insert([
            'person_id' => $personId,
            'parish_id' => (int) $parish['id'],
            'diocese_id' => $dioceseId,
            'proposed_office' => (string) $this->request->getPost('proposed_office'),
            'review_status' => 'pending',
            'requested_by_user_id' => (int) auth()->id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit()->log('clergy.appointment_request.submit', [
            'entity_type' => 'clergy_appointment_request',
            'entity_id' => (int) $db->insertID(),
            'parish_id' => (int) $parish['id'],
            'diocese_id' => $dioceseId,
        ]);

        return redirect()->to('/parish/clergy')->with('success', lang('ParishClergy.requestSubmitted'));
    }

    /**
     * Same registry rule as the diocesan linker: a canonical-status row in
     * this diocese, or ordination evidence with no standing elsewhere.
     */
    private static function personInRegistry(int $personId, int $dioceseId): bool
    {
        $db = db_connect();

        if ($db->table('clergy_canonical_status')
            ->where('person_id', $personId)->where('diocese_id', $dioceseId)->countAllResults() > 0) {
            return true;
        }

        if ($db->table('clergy_ordination')->where('person_id', $personId)->countAllResults() === 0) {
            return false;
        }

        return $db->table('clergy_canonical_status')
            ->where('person_id', $personId)->where('diocese_id !=', $dioceseId)->countAllResults() === 0;
    }
}
