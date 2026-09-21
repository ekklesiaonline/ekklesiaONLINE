<?php

namespace App\Controllers;

use App\Libraries\DataEncryption;

class DioceseAdminUsers extends BaseController
{
    private const DIOCESE_ADMIN_PERMISSIONS = [
        'diocese.primary',
        'diocese.admin.create',
        'diocese.admin.update',
        'diocese.admin.delete',
        'diocese.admin.reset_password',
    ];

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
            ->select('agu.user_id, u.active, ai.secret AS email, ai.force_reset, p.first_name, p.last_name, p.phone, p.diocese_id, d.name AS diocese_name')
            ->join('users u', 'u.id = agu.user_id')
            ->join('profile p', 'p.user_id = agu.user_id', 'left')
            ->join('diocese d', 'd.id = p.diocese_id', 'inner')
            ->join('auth_identities ai', 'ai.user_id = agu.user_id AND ai.type = "email_password"', 'left')
            ->where('agu.group', 'dioceseadmin')
            ->where('p.diocese_id', $scope['diocese_id'])
            ->when($search !== '', static fn ($query) => $query->groupStart()->like('p.first_name', $search)->orLike('p.last_name', $search)->orLike('ai.secret', $search)->groupEnd())
            ->when($status === 'active', static fn ($query) => $query->where('u.active', 1))
            ->when($status === 'disabled', static fn ($query) => $query->where('u.active', 0))
            ->when($status === 'pending', static fn ($query) => $query->where('u.active', 1)->where('ai.force_reset', 1))
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC');

        $admins = $builder->get()->getResultArray();
        $admins = array_map(static function (array $admin): array {
            $admin['phone'] = DataEncryption::decrypt((string) ($admin['phone'] ?? '')) ?? '';
            return $admin;
        }, $admins);

        $primaryModel = model('DioceseAdminPrimaryModel');
        $primary = $primaryModel->getByDiocese($scope['diocese_id']);

        return view('Diocesan\Views\admin_users', array_merge(
            $this->getCommonViewData(lang('System.sectionDioceseAdmins')),
            [
                'admins' => $admins,
                'search' => $search,
                'status' => $status,
                'diocese_id' => $scope['diocese_id'],
                'primary_user_id' => (int) ($primary['user_id'] ?? 0),
            ]
        ));
    }

    public function store()
    {
        $scope = $this->requirePrimaryDiocese('diocese.admin.create');
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (!$this->validate([
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name'  => 'required|min_length[2]|max_length[100]',
            'email'      => 'required|valid_email',
            'phone'      => 'permit_empty|max_length[50]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = filter_var(trim((string) $this->request->getPost('email')), FILTER_SANITIZE_EMAIL);
        if (!$email) {
            return redirect()->back()->withInput()->with('error', lang('System.invalidEmailAddress'));
        }

        $db = db_connect();
        if ($db->table('auth_identities')->where('type', 'email_password')->where('secret', $email)->limit(1)->get()->getRow() !== null) {
            return redirect()->back()->withInput()->with('error', lang('System.adminUserCreateFailed'));
        }

        try {
            $createdId = (new \App\Services\AdminCreationService())->createAdmin(
                'dioceseadmin',
                $email,
                trim((string) $this->request->getPost('first_name')),
                trim((string) $this->request->getPost('last_name')),
                preg_replace('/[^0-9+\s-]/', '', (string) ($this->request->getPost('phone') ?? '')),
                null,
                $scope['diocese_id']
            );

            $primaryModel = model('DioceseAdminPrimaryModel');
            if ($primaryModel->getByDiocese($scope['diocese_id']) === null && ! $primaryModel->setPrimary($scope['diocese_id'], $createdId, (int) auth()->id())) {
                throw new \RuntimeException('Failed to set diocese primary admin');
            }

            $this->audit()->log('diocese.admin_user.created', [
                'user_id'    => (int) auth()->id(),
                'entity_type' => 'admin_user',
                'entity_id'  => $createdId,
                'new_values' => ['role_group' => 'dioceseadmin', 'diocese_id' => $scope['diocese_id']],
            ]);

            return redirect()->to(base_url('diocese/admins'))->with('success', lang('System.adminUserCreated'));
        } catch (\Throwable $e) {
            return $this->safeRedirectError(lang('System.adminUserCreateFailed'), $e);
        }
    }

    public function update(int $userId)
    {
        $scope = $this->requirePrimaryDiocese('diocese.admin.update');
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('App.errorForbidden'));
        }

        if (!$this->validate([
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name'  => 'required|min_length[2]|max_length[100]',
            'phone'      => 'permit_empty|max_length[50]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        model('ProfileModel')->where('user_id', $userId)->update(null, [
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'last_name'  => trim((string) $this->request->getPost('last_name')),
            'phone'      => preg_replace('/[^0-9+\s-]/', '', (string) ($this->request->getPost('phone') ?? '')),
        ]);

        $this->audit()->log('diocese.admin_user.updated', [
            'user_id'    => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'  => $userId,
        ]);

        return redirect()->to(base_url('diocese/admins'))->with('success', lang('System.adminUserUpdated'));
    }

    public function resetPassword(int $userId)
    {
        $scope = $this->requirePrimaryDiocese('diocese.admin.reset_password');
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id']) || (int) auth()->id() === $userId) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('App.errorForbidden'));
        }

        if ($this->isPrimaryDioceseUser($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('Diocesan.cannotResetPrimaryDioceseAdminPassword'));
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
            'secret2'    => service('passwords')->hash($password),
            'force_reset' => 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        if ($identity !== null) {
            (new \App\Services\SecurityNotificationService())
                ->sendPasswordChangedNotice($userId, (string) $identity['secret']);
        }

        $this->audit()->log('diocese.admin_user.password_reset', [
            'user_id'    => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'  => $userId,
        ]);

        return redirect()->to(base_url('diocese/admins'))->with('success', lang('System.passwordResetSuccess'));
    }

    public function delete(int $userId)
    {
        $scope = $this->requirePrimaryDiocese('diocese.admin.delete');
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id']) || (int) auth()->id() === $userId) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('App.errorForbidden'));
        }

        if ($this->isPrimaryDioceseUser($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('Diocesan.cannotDeletePrimaryDioceseAdmin'));
        }

        $reason = trim((string) $this->request->getPost('delete_reason'));
        if (strlen($reason) < 10) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('System.adminDeleteReasonRequired'));
        }

        $db = db_connect();
        $db->transStart();

        $db->table('auth_permissions_users')->where('user_id', $userId)->delete();
        $db->table('auth_groups_users')->where('user_id', $userId)->delete();
        $db->table('auth_identities')->where('user_id', $userId)->delete();
        $db->table('profile')->where('user_id', $userId)->delete();
        $db->table('users')->where('id', $userId)->delete();

        $db->transComplete();

        $this->audit()->log('diocese.admin_user.deleted', [
            'user_id'    => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'  => $userId,
            'meta'       => ['reason' => $reason],
        ]);

        return redirect()->to(base_url('diocese/admins'))->with('success', lang('System.adminUserDeleted'));
    }

    public function deactivate(int $userId)
    {
        $scope = $this->requirePrimaryDiocese('diocese.admin.update');
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('App.errorForbidden'));
        }

        if ((int) auth()->id() === $userId) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('Diocesan.cannotDeactivateSelf'));
        }

        if ($this->isPrimaryDioceseUser($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('Diocesan.cannotDeactivatePrimaryDioceseAdmin'));
        }

        db_connect()->table('users')->where('id', $userId)->update(['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->audit()->log('diocese.admin_user.deactivated', [
            'user_id'    => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'  => $userId,
        ]);

        return redirect()->to(base_url('diocese/admins'))->with('success', lang('System.adminUserDeactivated'));
    }

    public function reactivate(int $userId)
    {
        $scope = $this->requirePrimaryDiocese('diocese.admin.update');
        if ($scope instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $scope;
        }

        if (! $this->targetIsInDiocese($userId, $scope['diocese_id'])) {
            return redirect()->to(base_url('diocese/admins'))->with('error', lang('App.errorForbidden'));
        }

        db_connect()->table('users')->where('id', $userId)->update(['active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->audit()->log('diocese.admin_user.reactivated', [
            'user_id'    => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'  => $userId,
        ]);

        return redirect()->to(base_url('diocese/admins'))->with('success', lang('System.adminUserReactivated'));
    }

    /**
     * Require the current user to be a primary diocesan admin and return their diocese scope.
     *
     * @return array<string,mixed>|\CodeIgniter\HTTP\ResponseInterface
     */
    private function requirePrimaryDiocese(?string $permission = null): array|\CodeIgniter\HTTP\ResponseInterface
    {
        $user = auth()->user();
        if (!$user || ! $user->inGroup('dioceseadmin') || ! $user->can('diocese.primary')) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        if ($permission !== null && ! $user->can($permission)) {
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
            ->where('agu.user_id', $userId)
            ->where('agu.group', 'dioceseadmin')
            ->where('p.diocese_id', $dioceseId)
            ->limit(1)
            ->get()
            ->getRow() !== null;
    }

    private function isPrimaryDioceseUser(int $userId, int $dioceseId): bool
    {
        $primary = model('DioceseAdminPrimaryModel')->getByDiocese($dioceseId);
        return $primary && (int) $primary['user_id'] === $userId;
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
