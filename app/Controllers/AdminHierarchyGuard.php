<?php

namespace App\Controllers;

use Config\AuthGroups;

/**
 * Role-hierarchy guard for user-mutating endpoints.
 *
 * Prevents lower-tier admins (parish/diocese/province) from modifying,
 * deactivating, or deleting accounts belonging to higher-tier admins,
 * and optionally blocks self-targeting for destructive actions.
 */
trait AdminHierarchyGuard
{
    /**
     * Higher number = more privileged. Groups not listed default to 0.
     *
     * @var array<string, int>
     */
    private static array $roleHierarchyPriorities = [
        AuthGroups::SYSTEM_ADMIN   => 4,
        AuthGroups::PROVINCE_ADMIN => 3,
        AuthGroups::DIOCESE_ADMIN  => 2,
        AuthGroups::PARISH_ADMIN   => 1,
    ];

    /**
     * Guard a mutation against a target user.
     *
     * @return string|null 'HIERARCHY' if the target outranks the actor,
     *                     'SELF' if $forbidSelf and the target is the actor,
     *                     null when the action is allowed.
     */
    protected function guardTargetUserMutation(int $targetUserId, bool $forbidSelf = false, ?int $actorUserId = null): ?string
    {
        $actorId = $actorUserId ?? $this->getActorUserId();

        if ($forbidSelf && $targetUserId > 0 && $targetUserId === $actorId) {
            $this->logHierarchyBlock($targetUserId, 'SELF', $actorId);
            return 'SELF';
        }

        if ($targetUserId <= 0) {
            return null;
        }

        $actorPriority  = $this->getUserRolePriority($actorId);
        $targetPriority = $this->getUserRolePriority($targetUserId);

        if ($targetPriority > $actorPriority) {
            $this->logHierarchyBlock($targetUserId, 'HIERARCHY', $actorId);
            return 'HIERARCHY';
        }

        return null;
    }

    /**
     * Bulk variant: returns the highest role priority for each user in one query.
     *
     * @param  array<int> $userIds
     * @return array<int, int> user_id => priority
     */
    protected function getUserRolePriorities(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $rows = db_connect()->table('auth_groups_users')
            ->select('user_id, `group`')
            ->whereIn('user_id', $userIds)
            ->get()
            ->getResultArray();

        $priorities = array_fill_keys($userIds, 0);
        foreach ($rows as $row) {
            $uid      = (int) $row['user_id'];
            $priority = self::$roleHierarchyPriorities[(string) $row['group']] ?? 0;
            if ($priority > ($priorities[$uid] ?? 0)) {
                $priorities[$uid] = $priority;
            }
        }

        return $priorities;
    }

    protected function getActorUserId(): int
    {
        return (int) (auth()->id() ?? 0);
    }

    protected function getUserRolePriority(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return $this->getUserRolePriorities([$userId])[$userId] ?? 0;
    }

    /**
     * Translate a guard code into a user-facing error message.
     */
    protected function hierarchyGuardMessage(string $code): string
    {
        return $code === 'SELF'
            ? lang('App.errorCannotDeleteSelf')
            : lang('App.errorCannotModifyHigherAdmin');
    }

    private function logHierarchyBlock(int $targetUserId, string $code, ?int $actorUserId = null): void
    {
        try {
            service('audit')->log('security.hierarchy_block', [
                'user_id'     => $actorUserId ?? (int) (auth()->id() ?? 0),
                'entity_type' => 'user',
                'entity_id'   => $targetUserId,
                'meta'        => [
                    'reason' => $code,
                    'path'   => function_exists('uri_string') ? uri_string() : '',
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'AdminHierarchyGuard: failed to write audit log: {error}', ['error' => $e->getMessage()]);
        }
    }
}
