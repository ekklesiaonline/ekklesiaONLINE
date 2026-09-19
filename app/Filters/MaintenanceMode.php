<?php

namespace App\Filters;

use App\Services\SystemSettingService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class MaintenanceMode implements FilterInterface
{
    private array $allowedPaths = [
        'login',
        'logout',
        'auth/',
        'assets/',
        'system/settings',
        'health',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            $path = ltrim((string) $request->getUri()->getPath(), '/');

            foreach ($this->allowedPaths as $allowed) {
                if ($path === $allowed || str_starts_with($path, $allowed)) {
                    return;
                }
            }

            $settings = service('systemSettings');
            if (!$settings->isMaintenanceMode()) {
                return;
            }

            if (function_exists('auth') && auth()->loggedIn()) {
                $user = auth()->user();
                if ($user !== null && $user->can('system.admin')) {
                    return;
                }
            }

            $message = $settings->getMaintenanceMessage();

            if ($request->isAJAX()) {
                return service('response')->setStatusCode(503)->setJSON([
                    'error' => 'Maintenance mode',
                    'message' => $message,
                ]);
            }

            return service('response')->setStatusCode(503)->setBody(
                view('errors/maintenance', ['message' => $message])
            );
        } catch (\Throwable $e) {
            // Never block the application because of a settings read failure.
            return;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
