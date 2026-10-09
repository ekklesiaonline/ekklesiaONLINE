<?php

namespace Diocese\Controllers;

use App\Controllers\BaseController;
use App\Services\AccountCreationService;
use App\Services\ClergyInvitationService;

/**
 * ClergyAccounts — diocesan clergy account management.
 *
 * Clergy are a diocese-domain entity: accounts are created only here, only
 * by diocesan admins, and always through an email invitation — never an
 * admin-set password. The admin's diocese is resolved server-side on every
 * action; no request parameter can move a cleric across dioceses.
 */
class ClergyAccounts extends BaseController
{
    /**
     * Resolve the acting admin's diocese server-side
     * (same pattern as BaseController::resolveDashboardScope()).
     */
    private function adminDioceseId(): int
    {
        $dioceseId = 0;
        try {
            $row = model('DioceseAdminPrimaryModel')->getByUser((int) auth()->id());
            $dioceseId = (int) ($row['diocese_id'] ?? 0);
            if ($dioceseId <= 0) {
                $profile = model('ProfileModel')->where('user_id', (int) auth()->id())->first();
                $dioceseId = (int) ($profile['diocese_id'] ?? 0);
            }
        } catch (\Throwable $e) {
            $dioceseId = 0;
        }

        return $dioceseId;
    }

    /**
     * Common gate: logged-in user with clergy.manage over a resolvable diocese.
     * Returns the admin diocese id, or 0 after issuing the failure redirect.
     */
    private function authorizedDiocese(): int
    {
        if (! auth()->loggedIn()) {
            return 0;
        }
        if ($this->requirePermission('clergy.manage')) {
            return 0;
        }
        return $this->adminDioceseId();
    }

    private function denyOrRedirect()
    {
        return redirect()->to('/diocese/dashboard')
            ->with('error', lang('Clergy.dioceseScopeRequired'));
    }

    /**
     * Load a clergy account scoped to the admin's diocese, or null.
     * The target must hold the clergy group and have profile.diocese_id =
     * the admin's diocese — diocese A never reads or mutates diocese B.
     */
    private function clergyInScope(int $userId, int $dioceseId): ?array
    {
        return db_connect()->table('users u')
            ->select('u.id, u.active, u.username, p.id AS profile_id, p.user_id, p.person_id, p.diocese_id,
                p.first_name, p.middle_name, p.last_name, p.portal_access, p.phone, p.address, p.city, p.state, p.country')
            ->join('profile p', 'p.user_id = u.id', 'inner')
            ->join('auth_groups_users g', "g.user_id = u.id AND g.group = 'clergy'", 'inner')
            ->where('u.id', $userId)
            ->where('p.diocese_id', $dioceseId)
            ->get()->getRowArray();
    }

    public function index()
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        $q = trim((string) $this->request->getGet('q'));

        $base = fn () => db_connect()->table('users u')
            ->select('u.id, u.active, p.first_name, p.last_name, p.portal_access, i.secret AS email, i.invitation_sent_at')
            ->join('profile p', 'p.user_id = u.id', 'inner')
            ->join('auth_groups_users g', "g.user_id = u.id AND g.group = 'clergy'", 'inner')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('p.diocese_id', $dioceseId)
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC');

        // Overview cards always reflect the full diocesan roster, not the
        // filtered view.
        $statsRoster = $this->enrichRoster($base()->get()->getResultArray(), $dioceseId);

        $builder = $base();
        if ($q !== '') {
            $builder->groupStart()
                ->like('p.first_name', $q)
                ->orLike('p.last_name', $q)
                ->orLike('i.secret', $q)
                ->groupEnd();
        }

        $clergy = $builder->get()->getResultArray();
        $roster = $this->enrichRoster($clergy, $dioceseId);

        return view('Diocese\Views\Clergy/index', array_merge(
            $this->getCommonViewData(lang('Clergy.manageTitle')),
            [
                'clergy' => $roster,
                'q'      => $q,
                'stats'  => $this->dashboardStats($statsRoster, $dioceseId),
                'queues'     => $this->workQueues($dioceseId, $statsRoster),
                'dioceseId'  => $dioceseId,
            ]
        ));
    }

    /**
     * Attach office, canonical standing, ordination, and licensed
     * appointment summaries to each roster row (read-only, batched).
     *
     * @param array<int, array<string, mixed>> $clergy
     * @return array<int, array<string, mixed>>
     */
    private function enrichRoster(array $clergy, int $dioceseId): array
    {
        if ($clergy === []) {
            return $clergy;
        }

        $db      = db_connect();
        $userIds = array_map(static fn (array $row): int => (int) $row['id'], $clergy);

        $personIds = [];
        foreach (db_connect()->table('profile')
            ->select('user_id, person_id')->whereIn('user_id', $userIds)->get()->getResultArray() as $p) {
            if ((int) ($p['person_id'] ?? 0) > 0) {
                $personIds[(int) $p['user_id']] = (int) $p['person_id'];
            }
        }

        $now = date('Y-m-d');

        // Only offices inside this diocese and currently in force:
        // approved, started, and not yet ended.
        $positions = [];
        foreach ($db->table('leadership_position lp')
            ->select('lp.user_id, lp.title, lp.scope_type, lp.pastoral_in_charge, c.name AS parish_name, dn.name AS deanery_name')
            ->join('parish c', 'c.id = lp.parish_id', 'left')
            ->join('deanery dn', 'dn.id = lp.deanery_id', 'left')
            ->where('lp.active', 1)
            ->where('lp.approved_at IS NOT NULL', null, false)
            ->where('lp.revoked_at IS NULL', null, false)
            ->groupStart()->where('lp.started_at IS NULL', null, false)->orWhere('lp.started_at <=', $now)->groupEnd()
            ->groupStart()->where('lp.ended_at IS NULL', null, false)->orWhere('lp.ended_at >=', $now)->groupEnd()
            ->whereIn('lp.user_id', $userIds)
            ->groupStart()
                ->groupStart()->where('lp.scope_type', 'diocese')->where('lp.diocese_id', $dioceseId)->groupEnd()
                ->orGroupStart()->where('lp.scope_type', 'parish')->where('c.diocese_id', $dioceseId)->groupEnd()
                ->orGroupStart()->where('lp.scope_type', 'deanery')->where('dn.diocese_id', $dioceseId)->groupEnd()
            ->groupEnd()
            ->orderBy('lp.scope_type', 'ASC')
            ->orderBy('lp.title', 'ASC')
            ->get()->getResultArray() as $pos) {
            $positions[(int) $pos['user_id']][] = $pos;
        }

        $standings = $ordination = $appointments = [];
        $personIdList = array_values($personIds);
        if ($personIdList !== [] && $db->tableExists('clergy_canonical_status')) {
            $rows = $db->table('clergy_canonical_status')
                ->whereIn('person_id', $personIdList)
                ->where('diocese_id', $dioceseId)
                ->orderBy('effective_from', 'DESC')
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $standings[(int) $row['person_id']] ??= $row;
            }
        }
        if ($personIdList !== [] && $db->tableExists('clergy_ordination')) {
            foreach ($db->table('clergy_ordination')
                ->whereIn('person_id', $personIdList)
                ->orderBy('ordained_on', 'DESC')
                ->get()->getResultArray() as $row) {
                $ordination[(int) $row['person_id']] ??= $row;
            }
        }
        if ($personIdList !== [] && $db->tableExists('clergy_appointment')) {
            $rows = $db->table('clergy_appointment ca')
                ->select('ca.person_id, ca.appointment_type, ca.status, ca.licensed_on, c.name AS parish_name')
                ->join('parish c', 'c.id = ca.parish_id', 'inner')
                ->where('c.diocese_id', $dioceseId)
                ->whereIn('ca.person_id', $personIdList)
                ->orderBy('ca.started_on', 'DESC')
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $appointments[(int) $row['person_id']] ??= $row;
            }
        }

        foreach ($clergy as &$row) {
            $uid = (int) $row['id'];
            $pid = $personIds[$uid] ?? 0;
            $row['person_id']    = $pid;
            $row['positions']    = $positions[$uid] ?? [];
            $row['standing']     = $pid > 0 ? ($standings[$pid] ?? null) : null;
            $row['ordination']   = $pid > 0 ? ($ordination[$pid] ?? null) : null;
            $row['appointment']  = $pid > 0 ? ($appointments[$pid] ?? null) : null;
        }
        unset($row);

        return $clergy;
    }

    /**
     * Work queues rendered on the dashboard, each gated by the same
     * permission as its ClergyReview action endpoints. All lists are
     * resolved against the admin's diocese server-side.
     *
     * @param array<int, array<string, mixed>> $statsRoster enriched full roster
     * @return array<string, mixed>
     */
    private function workQueues(int $dioceseId, array $statsRoster): array
    {
        $user = auth()->user();
        $db   = db_connect();
        $now  = date('Y-m-d');

        $queues = [
            'can_appointments' => $user->can('clergy.appointment.manage'),
            'can_ordination'   => $user->can('clergy.ordination.verify'),
            'can_governance'   => $user->can('governance.diocese.approve'),
        ];

        if ($queues['can_appointments'] && $db->tableExists('clergy_appointment_request')) {
            $queues['appointment_requests'] = $db->table('clergy_appointment_request r')
                ->select('r.id, r.proposed_office, r.created_at, p.first_name, p.last_name, pa.name AS parish_name')
                ->join('person p', 'p.id = r.person_id', 'left')
                ->join('parish pa', 'pa.id = r.parish_id', 'left')
                ->where('r.diocese_id', $dioceseId)
                ->where('r.review_status', 'pending')
                ->orderBy('r.id', 'ASC')
                ->get()->getResultArray();

            // Offices recorded but not yet approved, inside this diocese.
            $queues['pending_offices'] = $db->table('leadership_position lp')
                ->select('lp.id, lp.title, lp.role_code, lp.scope_type, lp.started_at,
                    pr.first_name, pr.last_name, c.name AS parish_name, dn.name AS deanery_name')
                ->join('profile pr', 'pr.user_id = lp.user_id', 'left')
                ->join('parish c', 'c.id = lp.parish_id', 'left')
                ->join('deanery dn', 'dn.id = lp.deanery_id', 'left')
                ->where('lp.active', 1)
                ->where('lp.approved_at IS NULL', null, false)
                ->where('lp.revoked_at IS NULL', null, false)
                ->groupStart()
                    ->groupStart()->where('lp.scope_type', 'diocese')->where('lp.diocese_id', $dioceseId)->groupEnd()
                    ->orGroupStart()->where('lp.scope_type', 'parish')->where('c.diocese_id', $dioceseId)->groupEnd()
                    ->orGroupStart()->where('lp.scope_type', 'deanery')->where('dn.diocese_id', $dioceseId)->groupEnd()
                ->groupEnd()
                ->orderBy('lp.id', 'ASC')
                ->get()->getResultArray();
        }

        if ($queues['can_ordination'] && $db->tableExists('clergy_transition_candidate')) {
            // Same jurisdiction predicate as ClergyReview::candidates.
            $queues['candidates'] = $db->table('clergy_transition_candidate tc')
                ->distinct()
                ->select('tc.id, tc.source_type, tc.user_id, tc.person_id, tc.proposed_order, tc.proposed_office, tc.scope_type, tc.scope_id,
                    pr.first_name, pr.last_name, pn.first_name AS person_first_name, pn.last_name AS person_last_name, pa.name AS parish_name')
                ->join('parish p', "tc.scope_type = 'parish' AND p.id = tc.scope_id", 'left')
                ->join('profile pr', 'pr.user_id = tc.user_id', 'left')
                ->join('parish pp', 'pp.id = pr.parish_id', 'left')
                ->join('person pn', 'pn.id = tc.person_id', 'left')
                ->join('parish pa', 'pa.id = tc.scope_id', 'left')
                ->where('tc.review_status', 'pending')
                ->groupStart()
                    ->groupStart()->where('tc.scope_type', 'diocese')->where('tc.scope_id', $dioceseId)->groupEnd()
                    ->orGroupStart()->where('tc.scope_type', 'parish')->where('p.diocese_id', $dioceseId)->groupEnd()
                    ->orGroupStart()->where('tc.scope_type', null)
                        ->groupStart()->where('pr.diocese_id', $dioceseId)->orWhere('pp.diocese_id', $dioceseId)->groupEnd()
                    ->groupEnd()
                ->groupEnd()
                ->orderBy('tc.id', 'ASC')
                ->get()->getResultArray();

            // Clergy on the register whose ordination is missing or unverified.
            $queues['unverified_ordinations'] = [];
            foreach ($statsRoster as $row) {
                $ordination = $row['ordination'] ?? null;
                if ((int) ($row['person_id'] ?? 0) > 0
                    && ($ordination === null || ($ordination['verification_status'] ?? '') !== 'verified')) {
                    $queues['unverified_ordinations'][] = [
                        'person_id'  => (int) $row['person_id'],
                        'name'       => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                        'order_code' => $ordination['order_code'] ?? null,
                    ];
                }
            }
        }

        if ($queues['can_governance'] && $db->tableExists('clergy_access_grant')) {
            $queues['delegations'] = $db->table('clergy_access_grant g')
                ->select('g.id, g.permission, g.ends_at, pr.first_name, pr.last_name, gpr.first_name AS grantor_first_name, gpr.last_name AS grantor_last_name')
                ->join('profile pr', 'pr.user_id = g.user_id', 'inner')
                ->join('profile gpr', 'gpr.user_id = g.granted_by_user_id', 'left')
                ->where('g.grant_type', 'delegation')
                ->where('g.scope_type', 'diocese')
                ->where('g.scope_id', $dioceseId)
                ->where('g.revoked_at IS NULL', null, false)
                ->groupStart()->where('g.ends_at IS NULL', null, false)->orWhere('g.ends_at >=', $now)->groupEnd()
                ->orderBy('g.ends_at', 'ASC')
                ->get()->getResultArray();

            // Delegation targets: active accounts belonging to this diocese.
            $queues['delegation_targets'] = $db->table('users u')
                ->select('u.id, p.first_name, p.last_name')
                ->join('profile p', 'p.user_id = u.id', 'inner')
                ->where('u.active', 1)
                ->where('p.diocese_id', $dioceseId)
                ->orderBy('p.last_name', 'ASC')
                ->orderBy('p.first_name', 'ASC')
                ->get()->getResultArray();
        }

        return $queues;
    }

    /**
     * Overview-card metrics for the diocesan clergy dashboard.
     *
     * @param array<int, array<string, mixed>> $roster enriched roster rows
     * @return array<string, int>
     */
    private function dashboardStats(array $roster, int $dioceseId): array
    {
        $db = db_connect();

        $active = $awaiting = $invited = 0;
        foreach ($roster as $row) {
            if ((int) ($row['active'] ?? 0) === 1) {
                $active++;
            }
            $hasParishOffice = false;
            foreach (($row['positions'] ?? []) as $pos) {
                if (($pos['scope_type'] ?? '') === 'parish') {
                    $hasParishOffice = true;
                    break;
                }
            }
            if (! $hasParishOffice) {
                $awaiting++;
            }
            if (($row['portal_access'] ?? '') === 'invited') {
                $invited++;
            }
        }

        $pendingRequests = 0;
        if ($db->tableExists('clergy_appointment_request')) {
            $pendingRequests = (int) $db->table('clergy_appointment_request')
                ->where('diocese_id', $dioceseId)
                ->where('review_status', 'pending')
                ->countAllResults();
        }

        // Same jurisdiction predicate as ClergyReview::candidates: diocese and
        // parish scopes resolve directly; legacy group candidates resolve
        // through the candidate's profile diocese or profile parish.
        $pendingCandidates = 0;
        if ($db->tableExists('clergy_transition_candidate')) {
            $pendingCandidates = (int) $db->table('clergy_transition_candidate tc')
                ->distinct()
                ->select('tc.id')
                ->join('parish p', "tc.scope_type = 'parish' AND p.id = tc.scope_id", 'left')
                ->join('profile pr', 'pr.user_id = tc.user_id', 'left')
                ->join('parish pp', 'pp.id = pr.parish_id', 'left')
                ->where('tc.review_status', 'pending')
                ->groupStart()
                    ->groupStart()->where('tc.scope_type', 'diocese')->where('tc.scope_id', $dioceseId)->groupEnd()
                    ->orGroupStart()->where('tc.scope_type', 'parish')->where('p.diocese_id', $dioceseId)->groupEnd()
                    ->orGroupStart()->where('tc.scope_type', null)
                        ->groupStart()->where('pr.diocese_id', $dioceseId)->orWhere('pp.diocese_id', $dioceseId)->groupEnd()
                    ->groupEnd()
                ->groupEnd()
                ->countAllResults();
        }

        $expiringGrants = 0;
        if ($db->tableExists('clergy_access_grant')) {
            $expiringGrants = (int) $db->table('clergy_access_grant g')
                ->join('profile p', 'p.user_id = g.user_id', 'inner')
                ->where('p.diocese_id', $dioceseId)
                ->where('g.revoked_at IS NULL', null, false)
                ->where('g.ends_at IS NOT NULL', null, false)
                ->where('g.ends_at <=', date('Y-m-d H:i:s', strtotime('+30 days')))
                ->where('g.ends_at >=', date('Y-m-d H:i:s'))
                ->countAllResults();
        }

        return [
            'total'             => count($roster),
            'active'            => $active,
            'awaiting'          => $awaiting,
            'invited'           => $invited,
            'pending_requests'  => $pendingRequests,
            'pending_candidates' => $pendingCandidates,
            'expiring_grants'   => $expiringGrants,
        ];
    }

    public function create()
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        return view('Diocese\Views\Clergy/create', array_merge(
            $this->getCommonViewData(lang('Clergy.createTitle')),
            ['diocese' => model('DiocesesModel')->find($dioceseId)]
        ));
    }

    public function store()
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        $rules = [
            'email' => 'required|valid_email',
            'first_name' => 'required|min_length[2]|max_length[255]',
            'last_name' => 'required|min_length[2]|max_length[255]',
            'person_id' => 'permit_empty|is_natural_no_zero|is_not_unique[person.id]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = $this->normalizeEmail((string) $this->request->getPost('email'));
        if ($this->emailIdentityExists($email)) {
            return redirect()->back()->withInput()->with('errors', ['email' => lang('Clergy.emailInUse')]);
        }

        // "Link to existing person": only a canonical-registry person of THIS
        // diocese with no linked user_person may be attached.
        $personId = (int) ($this->request->getPost('person_id') ?: 0);
        if ($personId > 0 && ! $this->linkablePerson($personId, $dioceseId)) {
            return redirect()->back()->withInput()->with('errors', [
                'person_id' => lang('Clergy.personNotLinkable'),
            ]);
        }

        try {
            $created = (new AccountCreationService())->createAccount([
                'email' => $email,
                'role' => 'clergy',
                'invited' => true,
                'diocese_id' => $dioceseId,
                'person_id' => $personId > 0 ? $personId : null,
                'create_person' => true,
                'portal_access' => 'invited',
                'profile' => [
                    'first_name' => $this->request->getPost('first_name'),
                    'middle_name' => $this->request->getPost('middle_name'),
                    'last_name' => $this->request->getPost('last_name'),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return $this->safeRedirectError(lang('Clergy.createFailed'), $e);
        }

        try {
            (new ClergyInvitationService())->send((int) $created['user_id'], $email);
            $sent = true;
        } catch (\Throwable $e) {
            $sent = false;
        }

        $this->audit()->log('clergy.account.create', [
            'diocese_id' => $dioceseId,
            'entity_type' => 'user',
            'entity_id' => (int) $created['user_id'],
            'new_values' => [
                'role' => 'clergy',
                'portal_access' => 'invited',
                'linked_person_id' => $personId > 0 ? $personId : ($created['person_id'] ?? null),
                'invitation_sent' => $sent,
            ],
        ]);

        $message = $sent ? lang('Clergy.invitationSent') : lang('Clergy.invitationSendFailed');

        return redirect()->to('/diocese/clergy')->with($sent ? 'success' : 'error', $message);
    }

    /**
     * Diocese-scoped picker: canonical-registry persons (ordination or
     * canonical-status rows in this diocese) without a linked user_person.
     */
    public function personSearch()
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }

        $q = trim((string) $this->request->getGet('q'));

        $persons = array_values(array_filter(
            db_connect()->table('person p')
                ->distinct()
                ->select('p.id, p.first_name, p.last_name')
                ->join('user_person up', 'up.person_id = p.id', 'left')
                ->where('up.person_id', null)
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

        return $this->jsonResponse(['success' => true, 'data' => $persons]);
    }

    public function show(int $id)
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        $clergy = $this->clergyInScope($id, $dioceseId);
        if ($clergy === null) {
            return redirect()->to('/diocese/clergy')->with('error', lang('Clergy.notFound'));
        }

        return view('Diocese\Views\Clergy/detail', array_merge(
            $this->getCommonViewData(lang('Clergy.detailTitle')),
            [
                'clergy' => $clergy,
                'identity' => db_connect()->table('auth_identities')
                    ->where('user_id', $id)->where('type', 'email_password')->get()->getRowArray(),
                'canonical' => $this->canonicalSummary((int) ($clergy['person_id'] ?? 0), $dioceseId),
            ]
        ));
    }

    public function edit(int $id)
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        $clergy = $this->clergyInScope($id, $dioceseId);
        if ($clergy === null) {
            return redirect()->to('/diocese/clergy')->with('error', lang('Clergy.notFound'));
        }

        return view('Diocese\Views\Clergy/edit', array_merge(
            $this->getCommonViewData(lang('Clergy.editTitle')),
            [
                'clergy' => $clergy,
                'canonical' => $this->canonicalSummary((int) ($clergy['person_id'] ?? 0), $dioceseId),
            ]
        ));
    }

    public function update(int $id)
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        $clergy = $this->clergyInScope($id, $dioceseId);
        if ($clergy === null) {
            return redirect()->to('/diocese/clergy')->with('error', lang('Clergy.notFound'));
        }

        // Diocese-owned account admin edits only identity fields; canonical
        // fields (ordination, standing) stay on the diocesan review workflow.
        $rules = [
            'first_name' => 'required|min_length[2]|max_length[255]',
            'middle_name' => 'permit_empty|max_length[255]',
            'last_name' => 'required|min_length[2]|max_length[255]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        model('ProfileModel')->update((int) $clergy['profile_id'], [
            'first_name' => $this->request->getPost('first_name'),
            'middle_name' => $this->request->getPost('middle_name'),
            'last_name' => $this->request->getPost('last_name'),
        ]);

        (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $clergy['profile_id']);

        $this->audit()->log('clergy.account.update', [
            'diocese_id' => $dioceseId,
            'entity_type' => 'user',
            'entity_id' => $id,
        ]);

        return redirect()->to('/diocese/clergy/' . $id)->with('success', lang('App.successUpdated'));
    }

    /**
     * Disable portal access (users.active=0) — never a canonical suspension.
     * Canonical standing changes remain a separate diocesan review action.
     */
    public function deactivate(int $id)
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        $clergy = $this->clergyInScope($id, $dioceseId);
        if ($clergy === null) {
            return redirect()->to('/diocese/clergy')->with('error', lang('Clergy.notFound'));
        }

        db_connect()->table('users')->where('id', $id)->update([
            'active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit()->log('clergy.account.portal_disabled', [
            'diocese_id' => $dioceseId,
            'entity_type' => 'user',
            'entity_id' => $id,
        ]);

        return redirect()->to('/diocese/clergy/' . $id)->with('success', lang('Clergy.portalAccessDisabled'));
    }

    public function resendInvitation(int $id)
    {
        $dioceseId = $this->authorizedDiocese();
        if ($dioceseId <= 0) {
            return $this->denyOrRedirect();
        }

        $clergy = $this->clergyInScope($id, $dioceseId);
        if ($clergy === null) {
            return redirect()->to('/diocese/clergy')->with('error', lang('Clergy.notFound'));
        }

        $identity = db_connect()->table('auth_identities')
            ->where('user_id', $id)->where('type', 'email_password')->get()->getRowArray();
        $email = (string) ($identity['secret'] ?? '');

        try {
            (new ClergyInvitationService())->send($id, $email);
        } catch (\Throwable $e) {
            return redirect()->to('/diocese/clergy/' . $id)->with('error', lang('Clergy.invitationSendFailed'));
        }

        $this->audit()->log('clergy.account.invitation_resent', [
            'diocese_id' => $dioceseId,
            'entity_type' => 'user',
            'entity_id' => $id,
        ]);

        return redirect()->to('/diocese/clergy/' . $id)->with('success', lang('Clergy.invitationSent'));
    }

    /**
     * A person is linkable only when they hold canonical records in THIS
     * diocese (ordination evidence recorded here, or canonical standing here)
     * and have no linked user account.
     */
    private function linkablePerson(int $personId, int $dioceseId): bool
    {
        $db = db_connect();

        if ($db->tableExists('user_person')
            && $db->table('user_person')->where('person_id', $personId)->countAllResults() > 0) {
            return false;
        }

        return self::personInRegistry($personId, $dioceseId);
    }

    /**
     * A person belongs to this diocese's canonical registry when they hold a
     * canonical-status row here, or hold ordination evidence with no standing
     * recorded in any other diocese (newly registered, not yet resident).
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

    /**
     * Read-only ordination + canonical standing summary for the detail view.
     *
     * @return array{ordinations: array, standings: array}
     */
    private function canonicalSummary(int $personId, int $dioceseId): array
    {
        if ($personId <= 0) {
            return ['ordinations' => [], 'standings' => []];
        }

        $db = db_connect();

        return [
            'ordinations' => $db->table('clergy_ordination')
                ->where('person_id', $personId)
                ->orderBy('ordained_on', 'ASC')->get()->getResultArray(),
            'standings' => $db->table('clergy_canonical_status')
                ->where('person_id', $personId)->where('diocese_id', $dioceseId)
                ->orderBy('effective_from', 'DESC')->get()->getResultArray(),
        ];
    }
}
