<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\MemberAccessService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * SetPasswordController
 *
 * Handles the force-reset "set your password" flow.
 * Reached after a successful magic-link login when force_reset = 1.
 * Excluded from the ForcePasswordResetFilter to break the redirect loop.
 */
class SetPasswordController extends BaseController
{
    public function show(): RedirectResponse|string
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('login');
        }

        // If no reset is required, send them to their normal home.
        if (! auth()->user()->requiresPasswordReset()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return view('auth/set_password', [
            'passwordPolicy' => $this->getPasswordPolicy(),
        ]);
    }

    public function save(): RedirectResponse
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('login');
        }

        $user = auth()->user();

        $rules = [
            'new_password'         => ['label' => 'Password',        'rules' => 'required'],
            'new_password_confirm' => ['label' => 'Confirm Password', 'rules' => 'required|matches[new_password]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $newPassword = (string) $this->request->getPost('new_password');

        $passwordValidation = $this->validateAdminPassword($newPassword);
        if (! $passwordValidation['ok']) {
            return redirect()->back()->withInput()->with('errors', ['new_password' => $passwordValidation['errors']]);
        }

        // Hash the new password and clear force_reset in one update.
        db_connect()->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->update([
                'secret2'     => service('passwords')->hash($newPassword),
                'force_reset' => 0,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

        // Transition portal_access invited → enabled now that reset is cleared.
        (new MemberAccessService())->activatePortalAccessAfterSuccessfulLogin((int) $user->id);

        // Security: a password change invalidates every trusted device so a
        // stolen device cookie cannot survive credential rotation.
        try {
            (new \App\Services\TrustedDeviceService())->revokeAll((int) $user->id);
        } catch (\Throwable $e) {
            log_message('warning', 'Trusted-device revocation after password reset failed: {error}', ['error' => $e->getMessage()]);
        }

        try {
            service('audit')->log('auth.password_reset.completed', [
                'entity_type' => 'user',
                'entity_id' => (int) $user->id,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to write set-password audit log: {error}', ['error' => $e->getMessage()]);
        }

        (new \App\Services\SecurityNotificationService())
            ->sendPasswordChangedNotice((int) $user->id, (string) ($user->email ?? ''));

        return redirect()->to(config('Auth')->loginRedirect())
            ->with('message', lang('Auth.setPasswordSuccess', [], 'en'));
    }
}
