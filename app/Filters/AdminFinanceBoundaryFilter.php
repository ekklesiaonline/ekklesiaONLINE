<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocks province/diocesan admins from finance workflows.
 */
class AdminFinanceBoundaryFilter implements FilterInterface
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

        $isProvinceOrDiocesan = $user->inGroup('provinceadmin') || $user->inGroup('dioceseadmin')
            || $user->can('province.admin')
            || $user->can('diocese.admin');
        if (!$isProvinceOrDiocesan) {
            return null;
        }

        if ($incoming->isAJAX() || str_contains((string) $incoming->getHeaderLine('Accept'), 'application/json')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'status' => 'error',
                    'message' => lang('App.errorUnauthorized'),
                    'error' => [
                        'code' => 'ADMIN_FINANCE_ACCESS_DENIED',
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
