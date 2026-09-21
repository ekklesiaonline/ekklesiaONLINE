<?php

namespace App\Filters;

use App\Services\DeviceVerificationPolicy;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Enforce new-device verification challenge for authenticated users.
 */
class DeviceVerificationFilter implements FilterInterface
{
    /**
     * Local-only safety valve for smoke tests. Delegates to the shared
     * DeviceVerificationPolicy so the bypass decision is identical to the
     * one SessionTrackingFilter makes before challenge/email creation.
     */
    private function shouldBypassForTesting(RequestInterface $request): bool
    {
        return (new DeviceVerificationPolicy())->shouldBypass();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        if (!auth()->loggedIn()) {
            return null;
        }

        if ($this->shouldBypassForTesting($request)) {
            session()->remove([
                'device_verification_required',
                'device_verification_code_hash',
                'device_verification_expires_at',
                'device_verification_attempts',
                'device_verification_sent_at',
            ]);
            session()->set('device_verified_at', time());
            return null;
        }

        if (!session()->get('device_verification_required')) {
            return null;
        }

        // For API/AJAX requests, return 403 JSON so frontend can respond gracefully.
        if (strtolower((string) $request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
                ->setJSON([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'New device verification required.',
                    'error' => [
                        'code' => 'DEVICE_VERIFICATION_REQUIRED',
                        'message' => 'New device verification required.',
                    ],
                    'redirect' => base_url('session/device-verify'),
                    'meta' => [
                        'http_status' => ResponseInterface::HTTP_FORBIDDEN,
                        'ts' => date('c'),
                    ],
                ]);
        }

        // Remember the requested page so verification can return the user to it.
        if (strtolower($request->getMethod()) === 'get') {
            $path = '/' . ltrim((string) $request->getUri()->getPath(), '/');
            $query = (string) $request->getUri()->getQuery();
            session()->set('device_verify_return_url', $query !== '' ? $path . '?' . $query : $path);
        }

        return redirect()->to(base_url('session/device-verify'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
