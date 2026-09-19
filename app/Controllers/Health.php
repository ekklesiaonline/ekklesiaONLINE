<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Health extends Controller
{
    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'status' => 'healthy',
            'message' => 'Application is healthy.',
            'meta' => [
                'http_status' => 200,
                'ts' => gmdate(DATE_ATOM),
            ],
        ]);
    }

    public function ready(): ResponseInterface
    {
        if (!$this->isSystemAdmin()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'status' => 'error',
                'message' => 'Access denied.',
                'error' => [
                    'code' => 'HEALTH_READINESS_FORBIDDEN',
                    'message' => 'Access denied.',
                    'details' => [],
                ],
                'errors' => [],
                'meta' => [
                    'http_status' => 403,
                    'ts' => gmdate(DATE_ATOM),
                ],
            ]);
        }

        $components = [
            'database' => $this->databaseReady() ? 'ready' : 'unavailable',
            'writable' => $this->writableReady() ? 'ready' : 'unavailable',
        ];
        $ready = !in_array('unavailable', $components, true);

        if (!$ready) {
            log_message('error', 'Deployment readiness check failed.');

            return $this->response->setStatusCode(503)->setJSON([
                'success' => false,
                'status' => 'error',
                'message' => 'Application is not ready.',
                'error' => [
                    'code' => 'HEALTH_READINESS_FAILED',
                    'message' => 'Application is not ready.',
                    'details' => ['components' => $components],
                ],
                'errors' => [],
                'meta' => [
                    'http_status' => 503,
                    'ts' => gmdate(DATE_ATOM),
                ],
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'status' => 'ready',
            'message' => 'Application is ready.',
            'components' => $components,
            'meta' => [
                'http_status' => 200,
                'ts' => gmdate(DATE_ATOM),
            ],
        ]);
    }

    protected function isSystemAdmin(): bool
    {
        if (!function_exists('auth') || !auth()->loggedIn()) {
            return false;
        }

        $user = auth()->user();

        return $user !== null && ($user->can('system.admin') || $user->inGroup('systemadmin'));
    }

    protected function databaseReady(): bool
    {
        try {
            $connection = db_connect();
            if (!$connection instanceof BaseConnection) {
                return false;
            }

            return $connection->query('SELECT 1')->getRow() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    protected function writableReady(): bool
    {
        $directories = ['cache', 'logs', 'session', 'uploads', 'backups'];

        foreach ($directories as $directory) {
            $path = WRITEPATH . $directory;
            if (!is_dir($path) || !is_writable($path)) {
                return false;
            }
        }

        return true;
    }
}
