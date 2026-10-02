<?php

declare(strict_types=1);

// Read-only local diagnostic. Never execute migrations, seeds, account writes or disclosures of identities.
$root = dirname(__DIR__, 3);
chdir($root);
require $root . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

$db = db_connect();
if ($db->DBDriver !== 'MySQLi') {
    throw new RuntimeException('Expected the configured live MySQL connection, not a test database.');
}
$db->query('START TRANSACTION READ ONLY');
try {
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $tableNames = $db->listTables();
    $tables = array_fill_keys($tableNames, true);
    $newTables = [
        'deanery', 'deanery_parish_membership', 'clergy_ordination',
        'clergy_canonical_status', 'clergy_transition_candidate', 'clergy_access_grant',
        'permission_role_sync_managed', 'parish_report_revision', 'parish_report_signature',
    ];
    $requiredTables = [
        'person', 'users', 'diocese', 'parish', 'leadership_position',
        'parish_report', 'parish_report_storage', 'auth_permissions_users',
    ];
    $auditedTables = array_merge($requiredTables, $newTables, [
        'auth_groups_users', 'migrations', 'diocese_admin_primary', 'profile', 'user_person',
    ]);
    $present = [];
    foreach ($auditedTables as $table) {
        $present[$table] = isset($tables[$db->prefixTable($table)]);
    }
    $columnRows = $db->table('INFORMATION_SCHEMA.COLUMNS')
        ->select('TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA')
        ->where('TABLE_SCHEMA', (string) $db->database)
        ->whereIn('TABLE_NAME', array_values(array_unique($auditedTables)))
        ->orderBy('TABLE_NAME')->orderBy('ORDINAL_POSITION')->get()->getResultArray();
    $columns = [];
    foreach ($columnRows as $row) {
        $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = [
            'type' => $row['COLUMN_TYPE'], 'nullable' => $row['IS_NULLABLE'], 'extra' => $row['EXTRA'],
        ];
    }
    $criticalColumns = [
        'person' => ['id'], 'users' => ['id', 'active'], 'diocese' => ['id'],
        'parish' => ['id', 'diocese_id', 'congregation_type'],
        'leadership_position' => ['id', 'user_id', 'person_id', 'role_category', 'role_code', 'clergy_order', 'scope_type', 'deanery_id', 'approved_at'],
        'parish_report' => ['id', 'parish_id', 'report_year', 'report_status', 'frozen_snapshot_json', 'finalized_by', 'finalized_at', 'snapshot_sha256'],
        'auth_groups_users' => ['id', 'user_id', 'group'],
        'auth_permissions_users' => ['id', 'user_id', 'permission'],
    ];
    $critical = [];
    foreach ($criticalColumns as $table => $fields) {
        foreach ($fields as $field) {
            $critical[$table . '.' . $field] = $columns[$table][$field] ?? null;
        }
    }
    $newColumns = [
        'leadership_position' => ['deanery_id', 'pastoral_in_charge', 'approved_by_user_id', 'approved_at', 'approval_evidence_reference', 'revoked_at'],
        'parish' => ['congregation_type'],
        'parish_report' => [
            'submitted_by', 'submitted_at', 'snapshot_sha256', 'report_revision',
            'clerk_certified_by', 'clerk_certified_at', 'warden_approved_by', 'warden_approved_at',
            'diocesan_approved_by', 'diocesan_approved_at',
        ],
    ];
    $unexpectedNewColumns = [];
    $missingNewColumns = [];
    foreach ($newColumns as $table => $fields) {
        foreach ($fields as $field) {
            if (isset($columns[$table][$field])) {
                $unexpectedNewColumns[] = $table . '.' . $field;
            } else {
                $missingNewColumns[] = $table . '.' . $field;
            }
        }
    }
    $newTableFields = [
        'deanery' => ['id', 'diocese_id', 'code', 'active'],
        'deanery_parish_membership' => ['id', 'deanery_id', 'parish_id', 'started_at', 'ended_at'],
        'clergy_ordination' => ['id', 'person_id', 'order_code', 'verification_status', 'verified_by_user_id'],
        'clergy_canonical_status' => ['id', 'person_id', 'diocese_id', 'status_code', 'effective_from'],
        'clergy_transition_candidate' => ['id', 'source_type', 'source_id', 'user_id', 'review_status'],
        'clergy_access_grant' => ['id', 'user_id', 'permission', 'scope_type', 'scope_id', 'grant_type'],
        'permission_role_sync_managed' => ['id', 'user_id', 'permission', 'permission_user_id', 'source_type', 'source_key'],
        'parish_report_revision' => ['id', 'report_id', 'revision_number', 'snapshot_sha256'],
        'parish_report_signature' => ['id', 'report_id', 'stage_code', 'actor_user_id', 'snapshot_sha256'],
    ];
    $missingNewTableFields = [];
    foreach ($newTableFields as $table => $fields) {
        if (!$present[$table]) {
            continue;
        }
        foreach ($fields as $field) {
            if (!isset($columns[$table][$field])) {
                $missingNewTableFields[] = $table . '.' . $field;
            }
        }
    }
    $migrationVersions = ['2026_09_30_000001', '2026-10-02-185843'];
    $history = $present['migrations']
        ? $db->table('migrations')->select('version, class, namespace, batch, time')
            ->whereIn('version', $migrationVersions)->orderBy('version')->get()->getResultArray() : [];

    $users = $db->table('users')->select('id, active')->get()->getResultArray();
    $userStatus = [];
    foreach ($users as $user) {
        $userStatus[(int) $user['id']] = (int) $user['active'];
    }
    $groupRows = $db->table('auth_groups_users')->select('id, user_id, `group`')->get()->getResultArray();
    $groupsByUser = [];
    $groupStats = [];
    $uniqueAssignments = [];
    $duplicateMemberships = [];
    $orphanMemberships = [];
    foreach ($groupRows as $row) {
        $userId = (int) $row['user_id'];
        $group = (string) $row['group'];
        if (!array_key_exists($userId, $userStatus)) {
            $orphanMemberships[] = (int) $row['id'];
        }
        $key = $userId . '|' . $group;
        if (isset($uniqueAssignments[$key])) {
            $duplicateMemberships[] = ['user_id' => $userId, 'group' => $group, 'membership_id' => (int) $row['id']];
        }
        $uniqueAssignments[$key] = true;
        $groupsByUser[$userId][$group] = true;
        $groupStats[$group]['membership_rows'] = ($groupStats[$group]['membership_rows'] ?? 0) + 1;
        $groupStats[$group]['active_memberships'] = ($groupStats[$group]['active_memberships'] ?? 0) + ($userStatus[$userId] ?? 0);
    }
    ksort($groupStats);
    $groupUserIds = [];
    foreach ($groupsByUser as $userId => $memberships) {
        foreach (array_keys($memberships) as $group) {
            $groupUserIds[$group][] = (int) $userId;
        }
    }
    ksort($groupUserIds);
    foreach ($groupUserIds as &$userIds) {
        sort($userIds, SORT_NUMERIC);
    }
    unset($userIds);
    $unassignedUsers = array_values(array_diff(array_keys($userStatus), array_keys($groupsByUser)));
    $unassignedStatuses = array_fill_keys($unassignedUsers, 'no_registration_row');
    if ($unassignedUsers !== [] && $db->tableExists('user_registration_requests')) {
        foreach ($db->table('user_registration_requests')->select('user_id, status')
            ->whereIn('user_id', $unassignedUsers)->get()->getResultArray() as $registration) {
            $unassignedStatuses[(int) $registration['user_id']] = (string) $registration['status'];
        }
    }
    $knownGroups = array_keys((array) config('AuthGroups')->groups);
    $unknownGroups = array_values(array_diff(array_keys($groupStats), $knownGroups));
    $multiGroup = [];
    $adminGroups = ['systemadmin', 'provinceadmin', 'dioceseadmin', 'parishadmin'];
    foreach ($groupsByUser as $userId => $memberships) {
        if (count($memberships) > 1) {
            $multiGroup[(int) $userId] = array_keys($memberships);
        }
    }
    $multiAdmin = array_filter($multiGroup, static fn(array $groups): bool => count(array_intersect($groups, $adminGroups)) > 1);
    $adminUsers = [];
    foreach ($groupsByUser as $userId => $memberships) {
        if (array_intersect(array_keys($memberships), array_merge($adminGroups, ['clergy']))) {
            $adminUsers[(int) $userId] = [
                'groups' => array_keys($memberships), 'active' => ($userStatus[$userId] ?? 0) === 1,
            ];
        }
    }
    ksort($adminUsers);

    $directRows = $db->table('auth_permissions_users')->select('id, user_id, permission')->get()->getResultArray();
    $knownPermissions = array_fill_keys(array_keys((array) config('AuthGroups')->permissions), true);
    $interesting = [
        'leadership_position.manage', 'deanery.view', 'deanery.manage', 'deanery.coordinate',
        'pastoral.parish.view', 'governance.report.finalize_clergy', 'governance.diocese.approve',
        'clergy.ordination.verify', 'clergy.appointment.manage', 'governance.report.approve_warden',
        'governance.report.certify_clerk',
    ];
    $interestingSet = array_fill_keys($interesting, true);
    $directImportant = [];
    $unknownPermissions = [];
    $directPair = [];
    $duplicatePermissions = [];
    $orphanPermissions = [];
    foreach ($directRows as $row) {
        $userId = (int) $row['user_id'];
        $permission = (string) $row['permission'];
        if (!array_key_exists($userId, $userStatus)) {
            $orphanPermissions[] = (int) $row['id'];
        }
        if (!isset($knownPermissions[$permission])) {
            $unknownPermissions[$permission] = ($unknownPermissions[$permission] ?? 0) + 1;
        }
        $key = $userId . '|' . $permission;
        if (isset($directPair[$key])) {
            $duplicatePermissions[] = ['user_id' => $userId, 'permission' => $permission, 'permission_id' => (int) $row['id']];
        }
        $directPair[$key] = true;
        if (isset($interestingSet[$permission])) {
            $directImportant[$permission][] = [
                'user_id' => $userId, 'groups' => array_keys($groupsByUser[$userId] ?? []),
                'active' => ($userStatus[$userId] ?? 0) === 1,
            ];
        }
    }
    ksort($directImportant);
    ksort($unknownPermissions);
    $profileByUser = [];
    if ($present['profile']) {
        foreach ($db->table('profile')->select('user_id, diocese_id, person_id')->get()->getResultArray() as $row) {
            $profileByUser[(int) $row['user_id']][] = $row;
        }
    }
    $identityTypesByUser = [];
    if ($unassignedUsers !== [] && $db->tableExists('auth_identities')) {
        foreach ($db->table('auth_identities')->select('user_id, type')
            ->whereIn('user_id', $unassignedUsers)->get()->getResultArray() as $identity) {
            $identityTypesByUser[(int) $identity['user_id']][] = (string) $identity['type'];
        }
    }
    $unassignedDetails = [];
    foreach ($unassignedUsers as $userId) {
        $unassignedDetails[$userId] = [
            'registration_status' => $unassignedStatuses[$userId],
            'identity_types' => $identityTypesByUser[$userId] ?? [],
            'profile_rows' => count($profileByUser[$userId] ?? []),
            'direct_permissions' => array_values(array_map(
                static fn(array $row): string => (string) $row['permission'],
                array_filter($directRows, static fn(array $row): bool => (int) $row['user_id'] === $userId)
            )),
        ];
    }
    $primaryByUser = [];
    if ($present['diocese_admin_primary']) {
        foreach ($db->table('diocese_admin_primary')->select('user_id, diocese_id')->get()->getResultArray() as $row) {
            $primaryByUser[(int) $row['user_id']][] = (int) $row['diocese_id'];
        }
    }
    $dioceseAdmins = [];
    foreach ($groupsByUser as $userId => $memberships) {
        if (!isset($memberships['dioceseadmin'])) {
            continue;
        }
        $p = $profileByUser[$userId] ?? [];
        $profileDioceses = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['diocese_id'] ?? 0), $p))));
        $primaryDioceses = array_values(array_unique($primaryByUser[$userId] ?? []));
        $dioceseAdmins[(int) $userId] = [
            'profile_dioceses' => $profileDioceses, 'primary_dioceses' => $primaryDioceses,
            'profile_rows' => count($p), 'active' => ($userStatus[$userId] ?? 0) === 1,
            'scope_missing' => $profileDioceses === [] && $primaryDioceses === [],
            'scope_conflict' => $profileDioceses !== [] && $primaryDioceses !== []
                && array_diff($profileDioceses, $primaryDioceses) !== [],
        ];
    }
    ksort($dioceseAdmins);
    $legacyPositions = [];
    if ($present['leadership_position']) {
        $legacyPositions = $db->table('leadership_position')
            ->select('id, user_id, person_id, role_code, clergy_order, scope_type, parish_id, diocese_id, province_id, active')
            ->groupStart()->where('role_category', 'clergy')->orWhere('clergy_order !=', '')
            ->groupEnd()->get()->getResultArray();
    }
    $legacyLinkChecks = [];
    foreach ($legacyPositions as $position) {
        $userId = (int) $position['user_id'];
        $positionPersonId = (int) ($position['person_id'] ?? 0);
        $personLinks = $db->table('user_person')->select('person_id, is_primary')
            ->where('user_id', $userId)->get()->getResultArray();
        $primaryLinks = array_values(array_filter(
            $personLinks, static fn(array $link): bool => (int) $link['is_primary'] === 1
        ));
        $parishId = (int) ($position['parish_id'] ?? 0);
        $legacyLinkChecks[(int) $position['id']] = [
            'user_groups' => array_keys($groupsByUser[$userId] ?? []),
            'primary_person_ids' => array_map(static fn(array $r): int => (int) $r['person_id'], $primaryLinks),
            'position_person_exists' => $positionPersonId > 0
                && $db->table('person')->where('id', $positionPersonId)->countAllResults() > 0,
            'primary_person_matches_position' => count($primaryLinks) === 1
                && (int) $primaryLinks[0]['person_id'] === $positionPersonId,
            'parish_exists' => $parishId > 0
                && $db->table('parish')->where('id', $parishId)->countAllResults() > 0,
        ];
    }
    $newTableCounts = [];
    foreach ($newTables as $table) {
        $newTableCounts[$table] = $present[$table] ? $db->table($table)->countAllResults() : null;
    }
    $candidateRows = $present['clergy_transition_candidate']
        ? $db->table('clergy_transition_candidate')
            ->select('id, source_type, source_id, user_id, person_id, proposed_order, proposed_office, scope_type, scope_id, review_status')
            ->get()->getResultArray() : [];
    $ordinationRows = $present['clergy_ordination']
        ? $db->table('clergy_ordination')->select('person_id, order_code, verification_status')->get()->getResultArray() : [];
    $statusRows = $present['clergy_canonical_status']
        ? $db->table('clergy_canonical_status')->select('person_id, diocese_id, status_code')->get()->getResultArray() : [];
    $grantRows = $present['clergy_access_grant']
        ? $db->table('clergy_access_grant')->select('id, user_id, permission, grant_type, scope_type, scope_id, revoked_at')
            ->get()->getResultArray() : [];
    $managedRows = $present['permission_role_sync_managed']
        ? $db->table('permission_role_sync_managed')
            ->select('id, user_id, permission, permission_user_id, source_type')->get()->getResultArray() : [];
    $permissionById = [];
    foreach ($directRows as $row) {
        $permissionById[(int) $row['id']] = $row;
    }
    $brokenManagedReferences = [];
    foreach ($managedRows as $row) {
        $direct = $permissionById[(int) $row['permission_user_id']] ?? null;
        if ($direct === null || (int) $direct['user_id'] !== (int) $row['user_id']
            || (string) $direct['permission'] !== (string) $row['permission']) {
            $brokenManagedReferences[] = (int) $row['id'];
        }
    }
    $leadershipApprovals = $present['leadership_position'] && isset($columns['leadership_position']['approved_at'])
        ? $db->table('leadership_position')->where('approved_at IS NOT NULL', null, false)->countAllResults() : null;
    $deaneryPositions = $present['leadership_position']
        ? $db->table('leadership_position')->where('scope_type', 'deanery')->countAllResults() : null;
    $foreignKeys = $db->table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
        ->select('TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME')
        ->where('TABLE_SCHEMA', (string) $db->database)->whereIn('TABLE_NAME', $newTables)
        ->where('REFERENCED_TABLE_NAME IS NOT NULL', null, false)->get()->getResultArray();
    $deaneryIndex = $db->table('INFORMATION_SCHEMA.STATISTICS')
        ->select('COLUMN_NAME, SEQ_IN_INDEX')
        ->where('TABLE_SCHEMA', (string) $db->database)->where('TABLE_NAME', 'leadership_position')
        ->where('INDEX_NAME', 'idx_leadership_deanery_active')
        ->orderBy('SEQ_IN_INDEX')->get()->getResultArray();
    $audit = [
        'observed_at_utc' => $now,
        'database_driver' => $db->DBDriver,
        'migration_rows' => $present['migrations'] ? $db->table('migrations')->countAllResults() : null,
        'migration_target_history' => $history,
        'tables_present' => $present,
        'critical_columns' => $critical,
        'unexpected_new_columns' => $unexpectedNewColumns,
        'missing_new_columns' => $missingNewColumns,
        'missing_new_table_fields' => $missingNewTableFields,
        'total_users' => count($users),
        'active_users' => count(array_filter($userStatus)),
        'assigned_users' => count($groupsByUser),
        'unassigned_user_statuses' => $unassignedStatuses,
        'unassigned_user_details' => $unassignedDetails,
        'membership_rows' => count($groupRows),
        'group_counts' => $groupStats,
        'role_member_ids' => $groupUserIds,
        'unknown_groups' => $unknownGroups,
        'duplicate_memberships' => $duplicateMemberships,
        'orphan_memberships' => $orphanMemberships,
        'multi_group_users' => $multiGroup,
        'multi_tier_admins' => $multiAdmin,
        'admin_and_clergy_assignments' => $adminUsers,
        'diocese_admin_scopes' => $dioceseAdmins,
        'direct_permission_rows' => count($directRows),
        'direct_important' => $directImportant,
        'unknown_direct_permissions' => $unknownPermissions,
        'duplicate_direct_permissions' => $duplicatePermissions,
        'orphan_direct_permissions' => $orphanPermissions,
        'legacy_clergy_candidate_sources' => [
            'group_rows' => $groupStats['clergy']['membership_rows'] ?? 0,
            'leadership_rows' => count($legacyPositions),
            'legacy_positions' => $legacyPositions,
            'legacy_link_checks' => $legacyLinkChecks,
        ],
        'new_table_row_counts' => $newTableCounts,
        'transition_candidates' => $candidateRows,
        'ordination_rows' => $ordinationRows,
        'canonical_status_rows' => $statusRows,
        'access_grant_rows' => $grantRows,
        'managed_permission_rows' => count($managedRows),
        'broken_managed_permission_refs' => $brokenManagedReferences,
        'approved_leadership_positions' => $leadershipApprovals,
        'deanery_leadership_positions' => $deaneryPositions,
        'new_table_foreign_keys' => $foreignKeys,
        'leadership_deanery_index' => $deaneryIndex,
    ];
    $db->query('ROLLBACK');
    $output = $argv[1] ?? $root . '/.work/clergy-role-plan/live_auth_migration_audit.json';
    file_put_contents($output, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    echo "Read-only audit complete: {$output}\n";
    echo "Users={$audit['total_users']}; assigned={$audit['assigned_users']}; membership_rows={$audit['membership_rows']}; direct_permission_rows={$audit['direct_permission_rows']}\n";
} catch (Throwable $e) {
    $db->query('ROLLBACK');
    throw $e;
}
