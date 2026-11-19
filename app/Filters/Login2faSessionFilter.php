<?php

declare(strict_types=1);

namespace App\Filters;

use App\Authentication\ScopedEmail2FA;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * Login2faSessionFilter
 *
 * Remember-me is disabled for users covered by the login email-2FA scope.
 * Shield restores a session from the remember-me cookie without re-running
 * the login action, so an in-scope user could otherwise stay signed in for
 * the whole cookie lifetime without a fresh second factor.
 *
 * On every authenticated request: if the user is in scope
 * (ScopedEmail2FA::appliesTo) but THIS session never completed the email
 * challenge (no SESSION_VERIFIED_USER_ID for the user), their remember-me
 * tokens are purged, the session is logged out, and they are sent to sign
 * in again — which triggers the challenge.
 */
class Login2faSessionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = auth('session');
        if (! $auth->loggedIn()) {
            return null;
        }

        $user = $auth->user();
        if (! $user instanceof User) {
            return null;
        }

        $userId = (int) $user->id;
        if ((int) (session()->get(ScopedEmail2FA::SESSION_VERIFIED_USER_ID) ?? 0) === $userId) {
            return null;
        }

        if (! (new ScopedEmail2FA())->appliesTo($user)) {
            return null;
        }

        self::purgeRememberTokens($userId);
        $auth->logout();

        return redirect()->route('login')->with('error', lang('Auth.need2FA'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Delete every remember-me token for the user (best-effort).
     */
    public static function purgeRememberTokens(int $userId): void
    {
        try {
            db_connect()->table('auth_remember_tokens')->where('user_id', $userId)->delete();
        } catch (\Throwable $e) {
            log_message('warning', 'Remember-me purge failed: {error}', ['error' => $e->getMessage()]);
        }
    }
}
