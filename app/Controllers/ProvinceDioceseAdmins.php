<?php

namespace App\Controllers;

use App\Libraries\DataEncryption;

class ProvinceDioceseAdmins extends BaseController
{
    public function index()
    {
        if ($response = $this->authorize()) {
            return $response;
        }

        $db = db_connect();
        $provinceId = $this->actorProvinceId();
        $dioceses = $db->table('diocese d')
            ->select('d.id, d.name, d.province_id, p.province_number')
            ->join('province p', 'p.id = d.province_id', 'inner')
            ->when($provinceId !== null, static fn ($query) => $query->where('d.province_id', $provinceId))
            ->orderBy('d.name', 'ASC')
            ->get()
            ->getResultArray();

        $search = trim((string) ($this->request->getGet('q') ?? ''));
        $status = (string) ($this->request->getGet('status') ?? 'all');
        if (!in_array($status, ['all', 'active', 'disabled', 'pending'], true)) {
            $status = 'all';
        }

        $admins = $db->table('auth_groups_users agu')
            ->select('agu.user_id, u.active, ai.secret AS email, ai.force_reset, p.first_name, p.last_name, p.phone, p.diocese_id, d.name AS diocese_name')
            ->join('users u', 'u.id = agu.user_id')
            ->join('profile p', 'p.user_id = agu.user_id', 'left')
            ->join('diocese d', 'd.id = p.diocese_id', 'inner')
            ->join('auth_identities ai', 'ai.user_id = agu.user_id AND ai.type = "email_password"', 'left')
            ->where('agu.group', 'dioceseadmin')
            ->when($provinceId !== null, static fn ($query) => $query->where('d.province_id', $provinceId))
            ->when($search !== '', static fn ($query) => $query->groupStart()->like('p.first_name', $search)->orLike('p.last_name', $search)->orLike('ai.secret', $search)->groupEnd())
            ->when($status === 'active', static fn ($query) => $query->where('u.active', 1))
            ->when($status === 'disabled', static fn ($query) => $query->where('u.active', 0))
            ->when($status === 'pending', static fn ($query) => $query->where('u.active', 1)->where('ai.force_reset', 1))
            ->orderBy('d.name', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->get()
            ->getResultArray();

        $perPage = 20;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $totalAdmins = count($admins);
        $totalPages = max(1, (int) ceil($totalAdmins / $perPage));
        $page = min($page, $totalPages);
        $admins = array_slice($admins, ($page - 1) * $perPage, $perPage);
        $admins = array_map(static function (array $admin): array {
            $admin['phone'] = DataEncryption::decrypt((string) ($admin['phone'] ?? '')) ?? '';
            return $admin;
        }, $admins);

        return view('Province/diocese_admins', array_merge(
            $this->getCommonViewData(lang('System.sectionDioceseAdmins')),
            ['admins' => $admins, 'dioceses' => $dioceses, 'search' => $search, 'status' => $status, 'page' => $page, 'total_pages' => $totalPages, 'total_admins' => $totalAdmins]
        ));
    }

    public function store()
    {
        if ($response = $this->authorize()) {
            return $response;
        }

        if (!$this->validate([
            'diocese_id' => 'required|integer',
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email',
            'phone' => 'permit_empty|max_length[50]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $dioceseId = (int) $this->request->getPost('diocese_id');
        if (!$this->dioceseIsInActorScope($dioceseId)) {
            return $this->scopeDenied();
        }

        $email = filter_var(trim((string) $this->request->getPost('email')), FILTER_SANITIZE_EMAIL);
        if (!$email || $db->table('auth_identities')->where('type', 'email_password')->where('secret', $email)->limit(1)->get()->getRow() !== null) {
            return redirect()->back()->withInput()->with('error', lang('System.adminUserCreateFailed'));
        }

        $db->transStart();
        try {
            $users = auth()->getProvider();
            $user = new \CodeIgniter\Shield\Entities\User([
                'username' => $email,
                'email' => $email,
                'password' => bin2hex(random_bytes(32)),
            ]);
            $users->save($user);
            $created = $users->findById($users->getInsertID());
            if (!$created) {
                throw new \RuntimeException('User creation failed');
            }
            $created->activate();

            $db->table('auth_groups_users')->insert([
                'user_id' => (int) $created->id,
                'group' => 'dioceseadmin',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('auth_identities')->where('user_id', (int) $created->id)->where('type', 'email_password')->update([
                'force_reset' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            model('ProfileModel')->insert([
                'user_id' => (int) $created->id,
                'parish_id' => null,
                'province_id' => null,
                'diocese_id' => $dioceseId,
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'last_name' => trim((string) $this->request->getPost('last_name')),
                'phone' => preg_replace('/[^0-9+\s-]/', '', (string) $this->request->getPost('phone')),
                // profile.membership_status is an ENUM; 'active' is not a valid
                // value (MySQL silently stores '' for it). 'member' is correct.
                'membership_status' => 'member',
            ]);

            $primaryModel = model('DioceseAdminPrimaryModel');
            if ($primaryModel->getByDiocese($dioceseId) === null && ! $primaryModel->setPrimary($dioceseId, (int) $created->id, (int) auth()->id())) {
                throw new \RuntimeException('Failed to set diocese primary admin');
            }

            $db->transComplete();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Transaction failed');
            }

            (new \App\Services\AdminInvitationService())->send($email, (int) $created->id);
            $this->audit()->log('province.diocese_admin.created', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'admin_user',
                'entity_id' => (int) $created->id,
                'new_values' => ['role_group' => 'dioceseadmin', 'diocese_id' => $dioceseId],
            ]);
            return redirect()->to(base_url('province/diocese-admins'))->with('success', lang('System.adminUserCreated'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->safeRedirectError(lang('System.adminUserCreateFailed'), $e);
        }
    }

    public function update(int $userId)
    {
        if ($response = $this->authorize()) {
            return $response;
        }

        if (!$this->validate([
            'diocese_id' => 'required|integer',
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'phone' => 'permit_empty|max_length[50]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dioceseId = (int) $this->request->getPost('diocese_id');
        if (!$this->targetIsInActorScope($userId) || !$this->dioceseIsInActorScope($dioceseId)) {
            return $this->scopeDenied();
        }

        model('ProfileModel')->where('user_id', $userId)->update(null, [
            'diocese_id' => $dioceseId,
            'province_id' => null,
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'last_name' => trim((string) $this->request->getPost('last_name')),
            'phone' => preg_replace('/[^0-9+\s-]/', '', (string) $this->request->getPost('phone')),
        ]);
        $this->audit()->log('province.diocese_admin.updated', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id' => $userId,
            'new_values' => ['diocese_id' => $dioceseId],
        ]);

        return redirect()->to(base_url('province/diocese-admins'))->with('success', lang('System.adminUserUpdated'));
    }

    public function resetPassword(int $userId)
    {
        if ($response = $this->authorize(true)) {
            return $response;
        }
        if (!$this->targetIsInActorScope($userId) || (int) auth()->id() === $userId) {
            return $this->scopeDenied();
        }

        $minPasswordLength = service('systemSettings')->get('password_policy.min_length', 12);
        if (!$this->validate(['new_password' => 'required|min_length[' . $minPasswordLength . ']', 'new_password_confirm' => 'required|matches[new_password]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $password = (string) $this->request->getPost('new_password');
        $validation = $this->validateAdminPassword($password);
        if (!$validation['ok']) {
            return redirect()->back()->withInput()->with('errors', ['new_password' => implode(' ', $validation['errors'])]);
        }

        $identity = db_connect()->table('auth_identities')
            ->select('secret')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        db_connect()->table('auth_identities')->where('user_id', $userId)->where('type', 'email_password')->update([
            'secret2' => service('passwords')->hash($password),
            'force_reset' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($identity !== null) {
            (new \App\Services\SecurityNotificationService())
                ->sendPasswordChangedNotice($userId, (string) $identity['secret']);
        }

        $this->audit()->log('province.diocese_admin.password_reset', ['entity_type' => 'admin_user', 'entity_id' => $userId]);
        return redirect()->to(base_url('province/diocese-admins'))->with('success', lang('System.passwordResetSuccess'));
    }

    public function resendInvitation(int $userId)
    {
        if ($response = $this->authorize()) {
            return $response;
        }
        if (!$this->targetIsInActorScope($userId)) {
            return $this->scopeDenied();
        }

        $identity = db_connect()->table('auth_identities')->select('secret, force_reset')->where('user_id', $userId)->where('type', 'email_password')->get()->getRowArray();
        if (!$identity || (int) ($identity['force_reset'] ?? 0) !== 1) {
            return redirect()->to(base_url('province/diocese-admins'))->with('error', lang('System.adminInvitationNotPending'));
        }

        try {
            (new \App\Services\AdminInvitationService())->send((string) $identity['secret'], $userId);
            $this->audit()->log('province.diocese_admin.invitation_resent', ['entity_type' => 'admin_user', 'entity_id' => $userId]);
            return redirect()->to(base_url('province/diocese-admins'))->with('success', lang('System.adminInvitationResent'));
        } catch (\Throwable $e) {
            return $this->safeRedirectError(lang('System.adminInvitationSendFailed'), $e);
        }
    }

    public function deactivate(int $userId)
    {
        if ($response = $this->authorize(true)) {
            return $response;
        }
        if (!$this->targetIsInActorScope($userId) || (int) auth()->id() === $userId) {
            return $this->scopeDenied();
        }

        db_connect()->table('users')->where('id', $userId)->update(['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->audit()->log('province.diocese_admin.deactivated', ['entity_type' => 'admin_user', 'entity_id' => $userId]);
        return redirect()->to(base_url('province/diocese-admins'))->with('success', lang('System.adminUserDeactivated'));
    }

    public function reactivate(int $userId)
    {
        if ($response = $this->authorize(true)) {
            return $response;
        }
        if (!$this->targetIsInActorScope($userId) || (int) auth()->id() === $userId) {
            return $this->scopeDenied();
        }

        db_connect()->table('users')->where('id', $userId)->update(['active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->audit()->log('province.diocese_admin.reactivated', ['entity_type' => 'admin_user', 'entity_id' => $userId]);
        return redirect()->to(base_url('province/diocese-admins'))->with('success', lang('System.adminUserReactivated'));
    }

    private function authorize(bool $requirePrimary = false)
    {
        $user = auth()->user();
        $provinceId = $this->actorProvinceId();
        if (!$user || !$user->inGroup('provinceadmin') || !$user->can('province.diocese_admin.manage') || $provinceId === null) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        if ($requirePrimary && !$this->isPrimaryProvinceAdmin($provinceId)) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        return null;
    }

    private function actorProvinceId(): ?int
    {
        $profile = model('ProfileModel')->where('user_id', (int) auth()->id())->first();
        $provinceId = (int) ($profile['province_id'] ?? 0);
        return $provinceId > 0 ? $provinceId : null;
    }

    private function dioceseIsInActorScope(int $dioceseId): bool
    {
        $provinceId = $this->actorProvinceId();
        $query = db_connect()->table('diocese')->where('id', $dioceseId);
        if ($provinceId !== null) {
            $query->where('province_id', $provinceId);
        }
        return $query->limit(1)->get()->getRow() !== null;
    }

    private function targetIsInActorScope(int $userId): bool
    {
        $provinceId = $this->actorProvinceId();
        $query = db_connect()->table('auth_groups_users agu')
            ->join('profile p', 'p.user_id = agu.user_id', 'inner')
            ->join('diocese d', 'd.id = p.diocese_id', 'inner')
            ->where('agu.user_id', $userId)
            ->where('agu.group', 'dioceseadmin');
        if ($provinceId !== null) {
            $query->where('d.province_id', $provinceId);
        }
        return $query->limit(1)->get()->getRow() !== null;
    }

    private function scopeDenied()
    {
        return redirect()->to(base_url('province/diocese-admins'))->with('error', lang('App.errorForbidden'));
    }
}
