<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RequestMonitoringFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $statusCode = (int) $response->getStatusCode();

        $slowThresholdMs = (int) (env('monitoring.slowRequestMs') ?: 800);
        if ($slowThresholdMs <= 0) {
            $slowThresholdMs = 800;
        }

        $isSlow = $durationMs >= $slowThresholdMs;
        $isServerError = $statusCode >= 500;
        if (!$isSlow && !$isServerError) {
            return null;
        }

        $isAjax = false;
        $userAgent = '';
        if ($request instanceof IncomingRequest) {
            $isAjax = $request->isAJAX();
            $userAgent = (string) $request->getUserAgent();
        }

        $payload = [
            'method' => strtoupper((string) $request->getMethod()),
            'path' => '/' . trim((string) $request->getUri()->getPath(), '/'),
            'status' => $statusCode,
            'duration_ms' => $durationMs,
            'ip' => (string) $request->getIPAddress(),
            'user_id' => (function_exists('auth') && auth()->loggedIn()) ? (int) auth()->id() : null,
            'is_ajax' => $isAjax,
            'ua' => substr($userAgent, 0, 255),
        ];

        service('monitoring')->log('request.monitor', $payload, $isServerError ? 'error' : 'warning');

        return null;
    }
}
