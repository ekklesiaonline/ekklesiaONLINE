<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocks diocesan admins from Parish-operator workflows.
 */
class DiocesanOperationalBoundaryFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $incoming = service('request');

        if (!auth()->loggedIn()) {
            return null;
        }

        $user = auth()->user();
        if (!$user || (! $user->inGroup('dioceseadmin') && ! $user->can('diocese.admin'))) {
            return null;
        }

        service('audit')->logAuthorizationDenial(
            'scope.denied',
            'DIOCESAN_OPERATIONAL_ACCESS_DENIED',
            ['filter' => self::class, 'scope' => 'diocesan_operational']
        );

        if ($incoming->isAJAX() || str_contains((string) $incoming->getHeaderLine('Accept'), 'application/json')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'status' => 'error',
                    'message' => lang('App.errorUnauthorized'),
                    'error' => [
                        'code' => 'DIOCESAN_OPERATIONAL_ACCESS_DENIED',
                        'message' => lang('App.errorUnauthorized'),
                    ],
                    'meta' => [
                        'http_status' => 403,
                        'ts' => date('c'),
                    ],
                ]);
        }

        return redirect()->to('/dashboard')->with('error', lang('App.errorUnauthorized'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
