<?php

namespace App\Services;

use App\Libraries\SchemaResolver;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * AuditLogQueryService
 *
 * Scope-aware read access to the audit_log table: scoped parish
 * resolution, role-dependent action filtering, statistics, filter
 * option lists, and actor autocomplete. The controller keeps
 * authentication, request parsing, and response formatting.
 *
 * $role is one of: system|province|diocese|parish.
 */
class AuditLogQueryService
{
    private BaseConnection $db;
    private SchemaResolver $schema;

    public function __construct(?BaseConnection $db = null, ?SchemaResolver $schema = null)
    {
        $this->db = $db ?? db_connect();
        $this->schema = $schema ?? new SchemaResolver($this->db);
    }

    public function tableExists(): bool
    {
        try {
            return $this->db->tableExists('audit_log');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resolved profile table name, or the 'profile' fallback used by
     * legacy deployments where the table cannot be inspected.
     */
    public function profileTable(): string
    {
        return $this->schema->resolveExistingProfileTable() ?? 'profile';
    }

    /**
     * Base audit_log builder with parish and profile joins applied.
     */
    public function baseBuilder(): BaseBuilder
    {
        $profileTable = $this->profileTable();

        return $this->db->table('audit_log')
            ->select('audit_log.*, c.name AS parish_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left');
    }

    /**
     * Parish IDs visible to the current scope. An empty result means
     * unrestricted (system) or no scope resolvable; callers must check
     * the role to distinguish the two.
     *
     * @return list<int>
     */
    public function scopedParishIds(string $role, int $provinceId, int $dioceseId, ?int $operationalParishId): array
    {
        if ($role === 'system') {
            return [];
        }

        if ($role === 'province') {
            if ($provinceId <= 0) {
                return [];
            }

            $rows = $this->db->table('parish c')
                ->select('c.id')
                ->join('diocese d', 'd.id = c.diocese_id', 'left')
                ->where('d.province_id', $provinceId)
                ->get()
                ->getResultArray();

            return array_map(static fn(array $row): int => (int) $row['id'], $rows);
        }

        if ($role === 'diocese') {
            if ($dioceseId <= 0) {
                return [];
            }

            $rows = $this->db->table('parish')
                ->select('id')
                ->where('diocese_id', $dioceseId)
                ->get()
                ->getResultArray();

            return array_map(static fn(array $row): int => (int) $row['id'], $rows);
        }

        return [(int) ($operationalParishId ?? 0)];
    }

    /**
     * Apply the role-dependent scope filter to an audit_log builder.
     *
     * System admins default to system-level/security events and may
     * drill into one parish via $drillParishId. Province and diocesan
     * admins see a curated set of significant actions across their
     * scoped parishes. Parish admins see everything for their parish.
     *
     * @param list<int> $scopeParishIds
     */
    public function applyScopeFilter(BaseBuilder $builder, string $role, array $scopeParishIds, int $drillParishId): void
    {
        if ($role === 'system') {
            if ($drillParishId > 0) {
                $builder->where('audit_log.parish_id', $drillParishId);
                return;
            }

            $builder->groupStart()
                ->where('audit_log.parish_id IS NULL')
                ->orLike('audit_log.action', 'system.', 'after')
                ->orWhereIn('audit_log.action', [
                    'permission.denied',
                    'scope.denied',
                    'member.hard_delete',
                    'member.merge',
                    'parish.bulk_delete',
                ])
                ->groupEnd();
            return;
        }

        $cleanScope = array_values(array_filter(array_unique(array_map('intval', $scopeParishIds)), static fn(int $id): bool => $id > 0));

        if ($cleanScope === []) {
            $builder->where('1 = 0');
            return;
        }

        $builder->whereIn('audit_log.parish_id', $cleanScope);

        if ($role === 'province') {
            $builder->groupStart()
                ->like('audit_log.action', 'member.', 'after')
                ->orLike('audit_log.action', 'parish.', 'after')
                ->orLike('audit_log.action', 'parochial_report.', 'after')
                ->orLike('audit_log.action', 'portal_access.', 'after')
                ->orLike('audit_log.action', 'non_portal_member.', 'after')
                ->orWhereIn('audit_log.action', [
                    'permission.denied',
                    'scope.denied',
                    'donation.create',
                    'donation.delete',
                    'expense.create',
                    'expense.delete',
                ])
                ->groupEnd();
            return;
        }

        if ($role === 'diocese') {
            $builder->groupStart()
                ->like('audit_log.action', 'member.', 'after')
                ->orLike('audit_log.action', 'parish.', 'after')
                ->orLike('audit_log.action', 'donation.', 'after')
                ->orLike('audit_log.action', 'expense.', 'after')
                ->orLike('audit_log.action', 'annual_pledge.', 'after')
                ->orLike('audit_log.action', 'family.', 'after')
                ->orLike('audit_log.action', 'family_member.', 'after')
                ->orLike('audit_log.action', 'parochial_report.', 'after')
                ->orLike('audit_log.action', 'portal_access.', 'after')
                ->orLike('audit_log.action', 'non_portal_member.', 'after')
                ->orLike('audit_log.action', 'people.', 'after')
                ->orWhereIn('audit_log.action', [
                    'permission.denied',
                    'scope.denied',
                    'service.create',
                    'service.update',
                    'service.delete',
                ])
                ->groupEnd();
            return;
        }

        // Parish admins see all logs for their parish — no action filter.
    }

    /**
     * Apply the user-supplied optional filters (parish drill-down for
     * non-system roles, action, entity type, actor name, quick filter,
     * and date bounds) to an audit_log builder.
     *
     * @param array<string, mixed> $filters
     */
    public function applyOptionalFilters(BaseBuilder $builder, array $filters, bool $isSystemAdmin): void
    {
        if (! $isSystemAdmin && (int) ($filters['parish_id'] ?? 0) > 0) {
            $builder->where('audit_log.parish_id', (int) $filters['parish_id']);
        }

        if ((string) ($filters['action'] ?? '') !== '') {
            $builder->where('audit_log.action', (string) $filters['action']);
        }

        if ((string) ($filters['entity_type'] ?? '') !== '') {
            $builder->where('audit_log.entity_type', (string) $filters['entity_type']);
        }

        if ((string) ($filters['actor_name'] ?? '') !== '') {
            $profileTable = $this->schema->resolveExistingProfileTable();
            if ($profileTable !== null) {
                $builder->groupStart()
                    ->like($profileTable . '.first_name', (string) $filters['actor_name'])
                    ->orLike($profileTable . '.last_name', (string) $filters['actor_name'])
                    ->groupEnd();
            }
        }

        if ((string) ($filters['quick_filter'] ?? '') !== '') {
            $this->applyQuickFilter($builder, (string) $filters['quick_filter']);
        }

        if (! empty($filters['start_date'])) {
            $builder->where('DATE(audit_log.created_at) >=', (string) $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $builder->where('DATE(audit_log.created_at) <=', (string) $filters['end_date']);
        }
    }

    public function applyQuickFilter(BaseBuilder $builder, string $quickFilter): void
    {
        switch ($quickFilter) {
            case 'system_ops':
                $builder->like('audit_log.action', 'system.', 'after');
                break;
            case 'security_events':
                $builder->whereIn('audit_log.action', ['permission.denied', 'scope.denied']);
                break;
            case 'data_changes':
                $builder->groupStart()
                    ->like('audit_log.action', '.created', 'before')
                    ->orLike('audit_log.action', '.updated', 'before')
                    ->orLike('audit_log.action', '.deleted', 'before')
                    ->groupEnd();
                break;
        }
    }

    /**
     * @return array<string, string>
     */
    public function timeRangeConditions(string $timeRange): array
    {
        switch ($timeRange) {
            case '24h':
                return ['audit_log.created_at >=' => date('Y-m-d H:i:s', strtotime('-24 hours'))];
            case '7d':
                return ['audit_log.created_at >=' => date('Y-m-d H:i:s', strtotime('-7 days'))];
            case '30d':
                return ['audit_log.created_at >=' => date('Y-m-d H:i:s', strtotime('-30 days'))];
            default:
                return [];
        }
    }

    /**
     * Raw statistics rows for the dashboard endpoint. The controller
     * maps them to display format (names, localized action labels).
     *
     * @param list<int> $scopeParishIds
     * @return array{today_events: int, security_events: int, most_active_users: list<array<string, mixed>>, top_operations: list<array<string, mixed>>}
     */
    public function statistics(string $role, array $scopeParishIds, int $drillParishId, string $timeRange): array
    {
        $timeConditions = $this->timeRangeConditions($timeRange);

        $todayBuilder = $this->db->table('audit_log');
        $this->applyScopeFilter($todayBuilder, $role, $scopeParishIds, $drillParishId);
        $todayBuilder->where('DATE(audit_log.created_at)', date('Y-m-d'));
        $todayEvents = $todayBuilder->countAllResults();

        $securityBuilder = $this->db->table('audit_log');
        $this->applyScopeFilter($securityBuilder, $role, $scopeParishIds, $drillParishId);
        $securityBuilder->whereIn('audit_log.action', ['permission.denied', 'scope.denied']);
        if ($timeConditions !== []) {
            $securityBuilder->where($timeConditions);
        }
        $securityEvents = $securityBuilder->countAllResults();

        $profileTable = $this->profileTable();
        $usersBuilder = $this->db->table('audit_log')
            ->select($profileTable . '.first_name, ' . $profileTable . '.last_name, COUNT(audit_log.id) as event_count')
            ->join($profileTable, $profileTable . '.user_id = audit_log.user_id', 'inner')
            ->groupBy($profileTable . '.user_id')
            ->orderBy('event_count', 'DESC')
            ->limit(5);
        $this->applyScopeFilter($usersBuilder, $role, $scopeParishIds, $drillParishId);
        if ($timeConditions !== []) {
            $usersBuilder->where($timeConditions);
        }
        $mostActiveUsers = $usersBuilder->get()->getResultArray();

        $opsBuilder = $this->db->table('audit_log')
            ->select('action, COUNT(id) as event_count')
            ->groupBy('action')
            ->orderBy('event_count', 'DESC')
            ->limit(10);
        $this->applyScopeFilter($opsBuilder, $role, $scopeParishIds, $drillParishId);
        if ($timeConditions !== []) {
            $opsBuilder->where($timeConditions);
        }
        $topOperations = $opsBuilder->get()->getResultArray();

        return [
            'today_events' => $todayEvents,
            'security_events' => $securityEvents,
            'most_active_users' => $mostActiveUsers,
            'top_operations' => $topOperations,
        ];
    }

    /**
     * @param list<int> $scopeParishIds
     * @return list<string>
     */
    public function availableActions(string $role, array $scopeParishIds, int $drillParishId): array
    {
        $builder = $this->db->table('audit_log')
            ->select('action')
            ->groupBy('action')
            ->orderBy('action', 'ASC');

        $this->applyScopeFilter($builder, $role, $scopeParishIds, $drillParishId);

        $rows = $builder->get()->getResultArray();

        return array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['action'] ?? ''), $rows), static fn(string $value): bool => $value !== ''));
    }

    /**
     * @param list<int> $scopeParishIds
     * @return list<string>
     */
    public function availableEntityTypes(string $role, array $scopeParishIds, int $drillParishId): array
    {
        $builder = $this->db->table('audit_log')
            ->select('entity_type')
            ->where('entity_type IS NOT NULL')
            ->where('entity_type !=', '')
            ->groupBy('entity_type')
            ->orderBy('entity_type', 'ASC');

        $this->applyScopeFilter($builder, $role, $scopeParishIds, $drillParishId);

        $rows = $builder->get()->getResultArray();

        return array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['entity_type'] ?? ''), $rows), static fn(string $value): bool => $value !== ''));
    }

    /**
     * Parishes available to the filter dropdown. System admins get the
     * full list; other roles are limited to their scope.
     *
     * @param list<int> $scopeParishIds
     * @return list<array<string, mixed>>
     */
    public function scopedParishes(bool $isSystemAdmin, array $scopeParishIds): array
    {
        if ($isSystemAdmin) {
            return $this->db->table('parish')
                ->select('id, name')
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        }

        $cleanScope = array_values(array_filter(array_unique(array_map('intval', $scopeParishIds)), static fn(int $id): bool => $id > 0));
        if ($cleanScope === []) {
            return [];
        }

        return $this->db->table('parish')
            ->select('id, name')
            ->whereIn('id', $cleanScope)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Distinct actor name suggestions matching a search query.
     *
     * @param list<int> $scopeParishIds
     * @return list<array<string, mixed>>
     */
    public function actorSuggestions(string $query, string $role, array $scopeParishIds, int $drillParishId): array
    {
        $profileTable = $this->profileTable();

        $builder = $this->db->table('audit_log')
            ->select('DISTINCT ' . $profileTable . '.user_id, ' . $profileTable . '.first_name, ' . $profileTable . '.last_name')
            ->join($profileTable, $profileTable . '.user_id = audit_log.user_id', 'inner')
            ->groupStart()
                ->like($profileTable . '.first_name', $query)
                ->orLike($profileTable . '.last_name', $query)
            ->groupEnd()
            ->groupBy($profileTable . '.user_id')
            ->limit(10);

        $this->applyScopeFilter($builder, $role, $scopeParishIds, $drillParishId);

        return $builder->get()->getResultArray();
    }
}
