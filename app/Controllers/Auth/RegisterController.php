<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Controllers\RegisterController as ShieldRegisterController;

class RegisterController extends ShieldRegisterController
{
    public function registerAction(): RedirectResponse
    {
        $parishId = (int) ($this->request->getPost('parish_id') ?? $this->request->getPost('parish_id') ?? 0);

        // Backward compatibility for older forms posting parish_id.
        if ($parishId > 0 && $this->request->getPost('parish_id') === null) {
            $post = (array) $this->request->getPost();
            $post['parish_id'] = $parishId;
            $this->request->setGlobal('post', $post);
        }

        if ($parishId <= 0 || !$this->parishExists($parishId)) {
            return redirect()->back()->withInput()->with('errors', [
                'parish_id' => lang('Auth.parishSelectionRequired'),
            ]);
        }

        return parent::registerAction();
    }

    private function parishExists(int $parishId): bool
    {
        return db_connect()->table('parish')->where('id', $parishId)->limit(1)->get()->getRow() !== null;
    }
}
