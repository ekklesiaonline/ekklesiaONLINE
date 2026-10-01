<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\AuthGroups;

/**
 * Enforces parish scope for parish-level workflows.
 *
 * Province and diocesan admins are excluded because their workflows can be
 * valid without a selected parish context.
 */
class ParishScopeFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $incoming = service('request');

        if (!auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();
        if (!$user) {
            return null;
        }

        if ($user->inGroup(AuthGroups::PROVINCE_ADMIN) || $user->inGroup(AuthGroups::DIOCESE_ADMIN)) {
            return null;
        }

        $selectedParishId = (int) (session()->get('selected_parish_id') ?? 0);
        if ($selectedParishId > 0 && model('ParishModel')->find($selectedParishId)) {
            return null;
        }

        session()->remove('selected_parish_id');

        $profile = model('ProfileModel')->where('user_id', (int) auth()->id())->first();
        $parishId = (int) ($profile['parish_id'] ?? 0);

        if ($parishId > 0) {
            session()->set('selected_parish_id', $parishId);

            return null;
        }

        service('audit')->logAuthorizationDenial(
            'scope.denied',
            'PARISH_SCOPE_REQUIRED',
            ['filter' => self::class, 'scope' => 'parish']
        );

        if ($incoming->isAJAX() || str_contains((string) $incoming->getHeaderLine('Accept'), 'application/json')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'No parish scope is assigned to your account.',
                    'error' => [
                        'code' => 'PARISH_SCOPE_REQUIRED',
                        'message' => 'No parish scope is assigned to your account.',
                    ],
                    'meta' => [
                        'http_status' => 403,
                        'ts' => date('c'),
                    ],
                ]);
        }

        return redirect()->to('/dashboard')->with('error', 'No parish scope is assigned to your account.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
