<?php

namespace App\Services;

use CodeIgniter\Database\QueryInterface;
use Throwable;

class MonitoringService
{
    /**
     * Keys that should never be written to structured logs in plaintext.
     * Partial matches are intentional so variants like token_value also match.
     *
     * @var list<string>
     */
    private array $sensitiveKeyFragments = [
        'password',
        'secret',
        'token',
        'credential',
        'email',
        'phone',
        'address',
        'notes',
        'amount',
        'gift',
        'pledge',
    ];

    public function log(string $event, array $payload = [], string $level = 'info'): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $record = array_merge([
            'ts' => date('c'),
            'event' => $event,
        ], $this->sanitizeContext($payload));

        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        log_message($level, 'MONITOR ' . $json);

        if ($this->isFileLogEnabled()) {
            $monitorFile = WRITEPATH . 'logs/monitoring-' . date('Y-m-d') . '.log';
            @file_put_contents($monitorFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    public function exception(string $event, Throwable $e, array $payload = [], string $level = 'error'): void
    {
        $payload['exception'] = [
            'class' => $e::class,
            'message' => $this->truncate((string) $e->getMessage(), 500),
            'code' => $e->getCode(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ];

        $this->log($event, $payload, $level);
    }

    public function logSlowQuery(QueryInterface $query): void
    {
        $thresholdMs = (float) (env('monitoring.slowQueryMs') ?: 100);
        if ($thresholdMs <= 0) {
            return;
        }

        $durationMs = round(((float) $query->getDuration(6)) * 1000, 2);
        if ($durationMs < $thresholdMs) {
            return;
        }

        $sql = method_exists($query, 'getOriginalQuery') ? $query->getOriginalQuery() : $query->getQuery();
        $sanitizedSql = $this->sanitizeSql((string) $sql);

        $this->log('db.query.slow', [
            'duration_ms' => $durationMs,
            'threshold_ms' => $thresholdMs,
            'is_write' => $query->isWriteType(),
            'sql_hash' => sha1($sanitizedSql),
            'sql_preview' => $this->truncate($sanitizedSql, 500),
            'error_code' => $query->hasError() ? $query->getErrorCode() : null,
            'error_message' => $query->hasError() ? $this->truncate($query->getErrorMessage(), 255) : null,
        ], 'warning');
    }

    public function sanitizeContext(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue($value, is_string($key) ? $key : null);
        }

        return $sanitized;
    }

    public function sanitizeSql(string $sql): string
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", "'?", $sql) ?? $sql;
        $sql = preg_replace('/"(?:\\"|[^"])*"/', '"?"', $sql) ?? $sql;
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql) ?? $sql;
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return trim($sql);
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeValue($childValue, is_string($childKey) ? $childKey : null);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return '[object ' . $value::class . ']';
        }

        if (is_string($value)) {
            return $this->truncate($value, 500);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach ($this->sensitiveKeyFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 3) . '...';
    }

    private function isEnabled(): bool
    {
        $enabled = filter_var((string) (env('monitoring.enabled') ?? 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $enabled !== false;
    }

    private function isFileLogEnabled(): bool
    {
        $enabled = filter_var((string) (env('monitoring.fileLogEnabled') ?? 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $enabled !== false;
    }
}
