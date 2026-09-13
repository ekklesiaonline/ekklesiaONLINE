<?php

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\HTTP\IncomingRequest;

class AuditService
{
    private AuditLogModel $auditLogModel;
    private MonitoringService $monitoring;

    public function __construct(?AuditLogModel $auditLogModel = null, ?MonitoringService $monitoring = null)
    {
        $this->auditLogModel = $auditLogModel ?? model(AuditLogModel::class);
        $this->monitoring = $monitoring ?? service('monitoring');
    }

    public function log(string $action, array $payload = []): void
    {
        $request = service('request');
        $isHttpRequest = $request instanceof IncomingRequest;

        $userId = $this->resolveInt($payload, 'user_id');
        $parishId = $this->resolveInt($payload, 'parish_id');
        $entityType = $this->resolveString($payload, 'entity_type', 80);
        $entityId = $this->resolveInt($payload, 'entity_id');

        $oldValues = $this->sanitizeAuditValues($payload['old_values'] ?? null);
        $newValues = $this->sanitizeAuditValues($payload['new_values'] ?? null);
        $meta = is_array($payload['meta'] ?? null) ? $this->monitoring->sanitizeContext($payload['meta']) : [];

        if ($isHttpRequest) {
            if ($userId === null && function_exists('auth') && auth()->loggedIn()) {
                $userId = (int) auth()->id();
            }
        }

        $record = [
            'user_id' => $userId,
            'parish_id' => $parishId,
            'action' => substr($action, 0, 120),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->toJson($oldValues),
            'new_values' => $this->toJson($newValues),
            'ip_address' => $isHttpRequest ? (string) $request->getIPAddress() : null,
            'user_agent' => $isHttpRequest ? substr((string) $request->getUserAgent(), 0, 255) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $writeDb = filter_var((string) (env('monitoring.auditDbEnabled') ?? 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($writeDb !== false && $this->hasAuditTable()) {
            try {
                db_connect()->table('audit_log')->insert($record);
            } catch (\Throwable $e) {
                $this->monitoring->exception('audit.db_write_failed', $e, [
                    'action' => $record['action'],
                    'entity_type' => $record['entity_type'],
                    'entity_id' => $record['entity_id'],
                ]);
            }
        }

        $writeFileLog = filter_var((string) (env('monitoring.auditLogEnabled') ?? 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $monitorPayload = [
            'action' => $record['action'],
            'entity_type' => $record['entity_type'],
            'entity_id' => $record['entity_id'],
            'user_id' => $record['user_id'],
            'parish_id' => $record['parish_id'],
            'ip' => $record['ip_address'],
            'meta' => $meta,
        ];

        if ($writeFileLog !== false) {
            $this->monitoring->log('audit.log', $monitorPayload, 'info');
        }
    }

    private function sanitizeAuditValues(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $sanitized = $this->monitoring->sanitizeContext(['payload' => $value]);

        return $sanitized['payload'] ?? null;
    }

    private function hasAuditTable(): bool
    {
        try {
            return db_connect()->tableExists('audit_log');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function toJson($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private function resolveInt(array $payload, string $key): ?int
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
            return null;
        }

        return (int) $payload[$key];
    }

    private function resolveString(array $payload, string $key, int $maxLength): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        return substr((string) $payload[$key], 0, $maxLength);
    }
}
