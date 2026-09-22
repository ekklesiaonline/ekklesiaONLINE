<?php

namespace App\Controllers;

use App\Libraries\DataEncryption;

class DioceseParishAdmins extends BaseController
{
    public function index()
    {
        $scope = $this->requirePrimaryDiocese();
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        $db = db_connect();
        $search = trim((string) ($this->request->getGet('q') ?? ''));
        $status = (string) ($this->request->getGet('status') ?? 'all');
        if (!in_array($status, ['all', 'active', 'disabled', 'pending'], true)) {
            $status = 'all';
        }

        $builder = $db->table('auth_groups_users agu')
            ->select('agu.user_id, u.active, ai.secret AS email, ai.force_reset, p.first_name, p.last_name, p.phone, p.parish_id, c.name AS parish_name, pap.user_id AS primary_user_id')
            ->join('users u', 'u.id = agu.user_id')
            ->join('profile p', 'p.user_id = agu.user_id', 'left')
            ->join('parish c', 'c.id = p.parish_id', 'inner')
            ->join('auth_identities ai', 'ai.user_id = agu.user_id AND ai.type = "email_password"', 'left')
            ->join('parish_admin_primary pap', 'pap.user_id = agu.user_id', 'left')
            ->where('agu.group', 'parishadmin')
            ->where('c.diocese_id', $scope['diocese_id'])
            ->when($search !== '', static fn ($query) => $query->groupStart()->like('p.first_name', $search)->orLike('p.last_name', $search)->orLike('ai.secret', $search)->groupEnd())
            ->when($status === 'active', static fn ($query) => $query->where('u.active', 1))
            ->when($status === 'disabled', static fn ($query) => $query->where('u.active', 0))
            ->when($status === 'pending', static fn ($query) => $query->where('u.active', 1)->where('ai.force_reset', 1))
            ->orderBy('c.name', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC');

        $admins = $builder->get()->getResultArray();
        $admins = array_map(static function (array $admin): array {
            $admin['phone'] = DataEncryption::decrypt((string) ($admin['phone'] ?? '')) ?? '';
            return $admin;
        }, $admins);

        $parishes = $db->table('parish')
            ->select('id, name')
            ->where('diocese_id', $scope['diocese_id'])
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return view('Diocesan\Views\parish_admins', array_merge(
            $this->getCommonViewData(lang('System.sectionParishAdmins')),
            [
                'admins' => $admins,
                'parishes' => $parishes,
                'search' => $search,
                'status' => $status,
                'diocese_id' => $scope['diocese_id'],
            ]
        ));
    }

    public function store()
    {
        $scope = $this->requirePrimaryDiocese();
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (!$this->validate([
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name'  => 'required|min_length[2]|max_length[100]',
            'email'      => 'required|valid_email',
            'parish_id'  => 'required|integer',
            'phone'      => 'permit_empty|max_length[50]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = filter_var(trim((string) $this->request->getPost('email')), FILTER_SANITIZE_EMAIL);
        if (!$email) {
            return redirect()->back()->withInput()->with('error', lang('System.invalidEmailAddress'));
        }

        $parishId = (int) $this->request->getPost('parish_id');
        $parish = db_connect()->table('parish')->select('id, name, diocese_id')->where('id', $parishId)->get()->getRowArray();
        if (!$parish || (int) $parish['diocese_id'] !== $scope['diocese_id']) {
            return redirect()->back()->withInput()->with('error', lang('App.errorInvalidInput'));
        }

        $db = db_connect();
        if ($db->table('auth_identities')->where('type', 'email_password')->where('secret', $email)->limit(1)->get()->getRow() !== null) {
            return redirect()->back()->withInput()->with('error', lang('System.adminUserCreateFailed'));
        }

        $db->transStart();
        try {
            $users = auth()->getProvider();
            $user = new \CodeIgniter\Shield\Entities\User([
                'username' => $email,
                'email'    => $email,
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
                'group' => 'parishadmin',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('auth_identities')->where('user_id', (int) $created->id)->where('type', 'email_password')->update([
                'force_reset' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $existingPrimary = $db->table('parish_admin_primary')
                ->where('parish_id', $parishId)
                ->get()
                ->getRow();

            model('ProfileModel')->insert([
                'user_id'         => (int) $created->id,
                'parish_id'       => $parishId,
                'diocese_id'      => (int) $parish['diocese_id'],
                'first_name'      => trim((string) $this->request->getPost('first_name')),
                'last_name'       => trim((string) $this->request->getPost('last_name')),
                'phone'           => preg_replace('/[^0-9+\s-]/', '', (string) ($this->request->getPost('phone') ?? '')),
            ]);

            if (! $existingPrimary && ! model('ParishAdminPrimaryModel')->setPrimary($parishId, (int) $created->id, (int) auth()->id())) {
                throw new \RuntimeException('Failed to set parish primary admin');
            }

            $db->transComplete();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Transaction failed');
            }

            (new \App\Services\AdminInvitationService())->send($email, (int) $created->id);

            $this->audit()->log('diocese.parish_admin.created', [
                'user_id'     => (int) auth()->id(),
                'entity_type' => 'admin_user',
                'entity_id'   => (int) $created->id,
                'new_values'  => ['role_group' => 'parishadmin', 'parish_id' => $parishId, 'diocese_id' => $scope['diocese_id']],
            ]);

            return redirect()->to(base_url('diocese/parish-admins'))->with('success', lang('System.adminUserCreated'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->safeRedirectError(lang('System.adminUserCreateFailed'), $e);
        }
    }

    public function update(int $userId)
    {
        $scope = $this->requirePrimaryDiocese();
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/parish-admins'))->with('error', lang('App.errorForbidden'));
        }

        if (!$this->validate([
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name'  => 'required|min_length[2]|max_length[100]',
            'parish_id'  => 'required|integer',
            'phone'      => 'permit_empty|max_length[50]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parishId = (int) $this->request->getPost('parish_id');
        $parish = db_connect()->table('parish')->select('id, diocese_id')->where('id', $parishId)->get()->getRowArray();
        if (!$parish || (int) $parish['diocese_id'] !== $scope['diocese_id']) {
            return redirect()->back()->withInput()->with('error', lang('App.errorInvalidInput'));
        }

        model('ProfileModel')->where('user_id', $userId)->update(null, [
            'parish_id'   => $parishId,
            'diocese_id'  => (int) $parish['diocese_id'],
            'first_name'  => trim((string) $this->request->getPost('first_name')),
            'last_name'   => trim((string) $this->request->getPost('last_name')),
            'phone'       => preg_replace('/[^0-9+\s-]/', '', (string) ($this->request->getPost('phone') ?? '')),
        ]);

        $this->audit()->log('diocese.parish_admin.updated', [
            'user_id'     => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'   => $userId,
        ]);

        return redirect()->to(base_url('diocese/parish-admins'))->with('success', lang('System.adminUserUpdated'));
    }

    public function resetPassword(int $userId)
    {
        $scope = $this->requirePrimaryDiocese();
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id']) || (int) auth()->id() === $userId) {
            return redirect()->to(base_url('diocese/parish-admins'))->with('error', lang('App.errorForbidden'));
        }

        $minPasswordLength = service('systemSettings')->get('password_policy.min_length', 12);
        if (!$this->validate([
            'new_password'         => 'required|min_length[' . $minPasswordLength . ']',
            'new_password_confirm' => 'required|matches[new_password]',
        ])) {
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
            'secret2'     => service('passwords')->hash($password),
            'force_reset' => 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        if ($identity !== null) {
            (new \App\Services\SecurityNotificationService())
                ->sendPasswordChangedNotice($userId, (string) $identity['secret']);
        }

        $this->audit()->log('diocese.parish_admin.password_reset', [
            'user_id'     => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'   => $userId,
        ]);

        return redirect()->to(base_url('diocese/parish-admins'))->with('success', lang('System.passwordResetSuccess'));
    }

    public function delete(int $userId)
    {
        $scope = $this->requirePrimaryDiocese();
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id']) || (int) auth()->id() === $userId) {
            return redirect()->to(base_url('diocese/parish-admins'))->with('error', lang('App.errorForbidden'));
        }

        $reason = trim((string) $this->request->getPost('delete_reason'));
        if (strlen($reason) < 10) {
            return redirect()->to(base_url('diocese/parish-admins'))->with('error', lang('System.adminDeleteReasonRequired'));
        }

        $db = db_connect();
        $db->transStart();

        $db->table('parish_admin_primary')->where('user_id', $userId)->delete();
        $db->table('auth_permissions_users')->where('user_id', $userId)->delete();
        $db->table('auth_groups_users')->where('user_id', $userId)->delete();
        $db->table('auth_identities')->where('user_id', $userId)->delete();
        $db->table('profile')->where('user_id', $userId)->delete();
        $db->table('users')->where('id', $userId)->delete();

        $db->transComplete();

        $this->audit()->log('diocese.parish_admin.deleted', [
            'user_id'     => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'   => $userId,
            'meta'        => ['reason' => $reason],
        ]);

        return redirect()->to(base_url('diocese/parish-admins'))->with('success', lang('System.adminUserDeleted'));
    }

    public function deactivate(int $userId)
    {
        $scope = $this->requirePrimaryDiocese();
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id']) || (int) auth()->id() === $userId) {
            return redirect()->to(base_url('diocese/parish-admins'))->with('error', lang('App.errorForbidden'));
        }

        db_connect()->table('users')->where('id', $userId)->update(['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->audit()->log('diocese.parish_admin.deactivated', [
            'user_id'     => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'   => $userId,
        ]);

        return redirect()->to(base_url('diocese/parish-admins'))->with('success', lang('System.adminUserDeactivated'));
    }

    public function reactivate(int $userId)
    {
        $scope = $this->requirePrimaryDiocese();
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/parish-admins'))->with('error', lang('App.errorForbidden'));
        }

        db_connect()->table('users')->where('id', $userId)->update(['active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->audit()->log('diocese.parish_admin.reactivated', [
            'user_id'     => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'   => $userId,
        ]);

        return redirect()->to(base_url('diocese/parish-admins'))->with('success', lang('System.adminUserReactivated'));
    }

    /**
     * Require the current user to be a primary diocesan admin and return their diocese scope.
     *
     * @return array<string,mixed>|\CodeIgniter\HTTP\ResponseInterface
     */
    private function requirePrimaryDiocese(): array|\CodeIgniter\HTTP\ResponseInterface
    {
        $user = auth()->user();
        if (!$user || ! $user->inGroup('dioceseadmin') || ! $user->can('diocese.primary')) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $dioceseId = $this->resolveDiocesanScopeId();
        if ($dioceseId <= 0) {
            return $this->forbiddenResponse(lang('Diocesan.accountDiocesanScopeFailed'));
        }

        return ['user_id' => (int) auth()->id(), 'diocese_id' => $dioceseId];
    }

    private function targetIsInDiocese(int $userId, int $dioceseId): bool
    {
        return db_connect()->table('auth_groups_users agu')
            ->join('profile p', 'p.user_id = agu.user_id', 'inner')
            ->join('parish c', 'c.id = p.parish_id', 'inner')
            ->where('agu.user_id', $userId)
            ->where('agu.group', 'parishadmin')
            ->where('c.diocese_id', $dioceseId)
            ->limit(1)
            ->get()
            ->getRow() !== null;
    }

    protected function validateAdminPassword(string $password): array
    {
        $minLength = (int) service('systemSettings')->get('password_policy.min_length', 12);

        $ok = strlen($password) >= $minLength
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);

        return ['ok' => $ok, 'errors' => $ok ? [] : [lang('System.passwordComplexity')]];
    }
}
