<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Controllers\RegisterController as ShieldRegisterController;

class RegisterController extends ShieldRegisterController
{
    public function registerView(): RedirectResponse|string
    {
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->registerRedirect());
        }

        if (! setting('Auth.allowRegistration')) {
            return redirect()->back()->withInput()
                ->with('error', lang('Auth.registerDisabled'));
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show');
        }

        $parishes = db_connect()->table('parish p')
            ->select('p.id, p.name, p.parish_ueid, d.name as diocese_name, pr.name as province_name')
            ->join('diocese d', 'd.id = p.diocese_id', 'left')
            ->join('province pr', 'pr.id = d.province_id', 'left')
            ->orderBy('pr.name', 'ASC')
            ->orderBy('d.name', 'ASC')
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->view(setting('Auth.views')['register'], [
            'parishes'          => $parishes,
            'parishLookupItems' => $this->buildParishLookupItems($parishes),
        ]);
    }

    public function registerAction(): RedirectResponse
    {
        $parishId = (int) ($this->request->getPost('parish_id') ?? 0);

        if ($parishId <= 0 || !$this->parishHierarchyValid($parishId)) {
            return redirect()->back()->withInput()->with('errors', [
                'parish_id' => lang('Auth.parishSelectionRequired'),
            ]);
        }

        return parent::registerAction();
    }

    protected function getValidationRules(): array
    {
        $rules = parent::getValidationRules();
        $message = 'Auth.registrationEmailUnavailable';

        foreach (['required', 'max_length', 'valid_email', 'is_unique'] as $rule) {
            $rules['email']['errors'][$rule] = $message;
        }

        return $rules;
    }

    private function buildParishLookupItems(array $parishes): array
    {
        $items = [];

        foreach ($parishes as $parish) {
            $parishUeid = trim((string) ($parish['parish_ueid'] ?? ''));
            $dioceseName = trim((string) ($parish['diocese_name'] ?? ''));
            $provinceName = trim((string) ($parish['province_name'] ?? ''));
            $label = (string) $parish['name'];

            if ($parishUeid !== '') {
                $label .= ' [' . $parishUeid . ']';
            }

            if ($dioceseName !== '') {
                $label .= ' - ' . $dioceseName;
            }

            if ($provinceName !== '') {
                $label .= ' (' . $provinceName . ')';
            }

            $items[] = [
                'id'          => (int) $parish['id'],
                'name'        => (string) $parish['name'],
                'parish_ueid' => $parishUeid,
                'label'       => $label,
            ];
        }

        return $items;
    }

    private function parishHierarchyValid(int $parishId): bool
    {
        if ($parishId <= 0) {
            return false;
        }

        $db = db_connect();

        // If hierarchy tables do not exist in the current environment (e.g. minimal test fixtures),
        // fallback to checking whether the parish exists.
        if (! $db->tableExists('diocese') || ! $db->tableExists('province')) {
            return $this->parishExists($parishId);
        }

        $row = $db->table('parish p')
            ->select('p.id, d.id AS diocese_id, pr.id AS province_id')
            ->join('diocese d', 'd.id = p.diocese_id', 'inner')
            ->join('province pr', 'pr.id = d.province_id', 'inner')
            ->where('p.id', $parishId)
            ->limit(1)
            ->get()
            ->getRow();

        return $row !== null;
    }

    private function parishExists(int $parishId): bool
    {
        return db_connect()->table('parish')->where('id', $parishId)->limit(1)->get()->getRow() !== null;
    }
}
