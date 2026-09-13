<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;

/**
 * DEPRECATED — redirects to the new parish-scoped finance dashboard.
 *
 * The old Treasurer dashboard was diocese-scoped. It now redirects to
 * /parish/finance, which is scoped to the treasurer's own parish.
 */
class Dashboard extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        log_message('info', '[DEPRECATED] Treasurer dashboard redirecting user {user} to /parish/finance', [
            'user' => (int) auth()->id(),
        ]);

        return redirect()->to('/parish/finance');
    }
}
