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

        $builder = db_connect()->table('users u')
            ->select('u.id, u.active, p.first_name, p.last_name, p.portal_access, i.secret AS email, i.invitation_sent_at')
            ->join('profile p', 'p.user_id = u.id', 'inner')
            ->join('auth_groups_users g', "g.user_id = u.id AND g.group = 'clergy'", 'inner')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('p.diocese_id', $dioceseId)
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC');

        if ($q !== '') {
            $builder->groupStart()
                ->like('p.first_name', $q)
                ->orLike('p.last_name', $q)
                ->orLike('i.secret', $q)
                ->groupEnd();
        }

        $clergy = $builder->get()->getResultArray();

        return view('Diocese\Views\Clergy/index', array_merge(
            $this->getCommonViewData(lang('Clergy.manageTitle')),
            ['clergy' => $clergy, 'q' => $q]
        ));
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
