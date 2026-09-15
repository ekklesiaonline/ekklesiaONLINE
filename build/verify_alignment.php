<?php

/**
 * ekklesiaRD — Schema Alignment Verification
 *
 * Uses system grep for speed across a large codebase.
 * Checks all PHP source files for old table/column references.
 *
 * Usage:
 *   php build/verify_alignment.php
 *   php build/verify_alignment.php --summary   (totals only)
 *   php build/verify_alignment.php --json      (CI-friendly)
 *
 * Exit code: 0 = aligned, 1 = errors found
 */

declare(strict_types=1);
chdir(dirname(__DIR__));

$OLD_TABLES = [
    'parishes',
    'parish_settings',
    'parish_integrations',
    'parish_provider_credentials',
    'parish_role_audit_logs',
    'audit_logs',
    'user_sessions',
    'volunteer_teams',
    'team_members',
    'families',
    'family_members',
    'annual_pledges',
    'guest_followup_log',
    'call_tasks',
    'donations',
    'guests',
    // member_parish_affiliations is now a backward-compat VIEW — SELECT refs are valid
];

$SCAN_DIRS = [
    'app/Controllers',
    'app/Filters',
    'app/Commands',
    'app/Config',
    'app/Models',
    'app/Services',
    'app/Libraries',
    'app/Helpers',
    'app/Views',
    'Pastor',
    'Diocesan',
    'MemberPortal',
    'Treasurer',
    'Volunteer',
    'Worship',
    'Province',
];

$SKIP_PATHS = [
    'app/Database/Migrations/',
    'app/Database/Seeds/',
    'tests/',
    'build/',
    'docs/',
    'vendor/',
];

$MODEL_TABLE_MAP = [
    'app/Models/ParishModel.php'                   => 'parish',
    'app/Models/ParishSettingModel.php'            => 'parish_setting',
    'app/Models/ParishIntegrationModel.php'        => 'parish_integration',
    'app/Models/ParishProviderCredentialModel.php' => 'parish_provider_credential',
    'app/Models/ParishRoleAuditModel.php'          => 'parish_role_audit_log',
    'app/Models/TeamModel.php'                     => 'team',
    'app/Models/FollowupLogModel.php'              => 'followup_log',
    'app/Models/AuditLogModel.php'                 => 'audit_log',
    'app/Models/UserSessionModel.php'              => 'user_session',
    'app/Models/TeamMemberModel.php'               => 'team_member',
    'app/Models/FamilyModel.php'                   => 'family',
    'app/Models/FamilyMemberModel.php'             => 'family_member',
    'app/Models/AnnualPledgeModel.php'             => 'annual_pledge',
    'app/Models/ServiceModel.php'                  => 'service',
    'app/Models/DonationModel.php'                 => 'donation',
    'app/Models/GuestModel.php'                    => 'guest',
    'app/Models/CallTaskModel.php'                 => 'call_task',
];

// ── Helpers ───────────────────────────────────────────────────────────────────

function grep_files(string $pattern, array $dirs, array $skip): array
{
    $existing = array_filter($dirs, 'is_dir');
    if (!$existing) return [];
    $dirArgs = implode(' ', array_map('escapeshellarg', $existing));
    $cmd = "grep -rn --include='*.php' -E " . escapeshellarg($pattern) . " $dirArgs 2>/dev/null";
    $out = [];
    exec($cmd, $out);
    return array_filter($out, function ($l) use ($skip) {
        foreach ($skip as $s) {
            if (str_contains($l, $s)) return false;
        }
        return true;
    });
}

function parse_hits(array $lines): array
{
    $hits = [];
    foreach ($lines as $l) {
        if (preg_match('/^(.+\.php):(\d+):\s*(.*)$/', $l, $m))
            $hits[] = ['file' => $m[1], 'line' => (int)$m[2], 'content' => trim($m[3])];
    }
    return $hits;
}

// ── Checks ────────────────────────────────────────────────────────────────────
$errors = [];
$warnings = [];

// 1. Old table names as quoted strings (filter out '=>' view-data keys and // comments)
$tpat = "'" . implode("'|'", $OLD_TABLES) . "'";
$raw  = grep_files($tpat, $SCAN_DIRS, $SKIP_PATHS);
$raw  = array_filter($raw, function ($l) {
    $c = preg_replace('~^\s*//.+~m', '', $l);           // skip // comment lines
    if (preg_match("/'\w+'\s*=>/", $c)) return false;   // view-data array key like 'parishes' =>
    if (preg_match('/\$\w+\[/', $c)) return false;       // array access like $totals['parishes']
    if (str_contains($c, 'base_url(')) return false;     // URL route paths like base_url('parishes')
    if (str_contains($c, 'uri_string()')) return false;  // sidebar/nav URI comparisons
    if (str_contains($c, "->group(")) return false;      // route group definitions
    if (str_contains($c, "->add(")) return false;        // route add definitions
    if (preg_match("/\\\$routes->/", $c)) return false;  // any route definition
    if (str_contains($c, "'/")) return false;            // URL path strings like '/guests'
    // Allow only real DB/query contexts: ->table(), ->join(), ->from(), getFieldNames, tableExists, columnExists, is_not_unique, $table =
    if (!preg_match('/->table\(|->join\(|->from\(|getFieldNames|tableExists|columnExists|is_not_unique|protected\s+\$table|getColumnsForTable/', $c)) return false;
    return true;
});
$errors['old_table_quoted'] = parse_hits(array_values($raw));

// 2. tableExists() with old names
$errors['tableExists'] = parse_hits(grep_files(
    'tableExists\([\'"](' . implode('|', $OLD_TABLES) . ')[\'"]',
    $SCAN_DIRS,
    $SKIP_PATHS
));

// 3. ->from() / ->join() with old names
$errors['from_join'] = parse_hits(grep_files(
    '(->from|->join)\([\'"](' . implode('|', $OLD_TABLES) . ')',
    $SCAN_DIRS,
    $SKIP_PATHS
));

// 4. Model $table property mismatches
foreach ($MODEL_TABLE_MAP as $file => $expected) {
    if (!file_exists($file)) {
        $errors['model_check'][] = ['file' => $file, 'line' => 0, 'content' => "Missing file"];
        continue;
    }
    $src = file_get_contents($file);
    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $m) && $m[1] !== $expected) {
        $ln = substr_count(substr($src, 0, (int)strpos($src, $m[0])), "\n") + 1;
        $errors['model_check'][] = ['file' => $file, 'line' => $ln, 'content' => "\$table='{$m[1]}' expected='$expected'"];
    }
}

// 5. DB — old tables still present
$dbErrors = [];
if (file_exists('.env')) {
    $env = file_get_contents('.env');
    preg_match('/database\.default\.database\s*=\s*(\S+)/', $env, $dbm);
    preg_match('/database\.default\.username\s*=\s*(\S+)/', $env, $um);
    $dbName = $dbm[1] ?? '';
    $dbUser = $um[1] ?? 'root';
    if ($dbName) {
        $inList = "'" . implode("','", $OLD_TABLES) . "'";
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema='$dbName' AND table_name IN ($inList)";
        $out = [];
        exec("mysql -u " . escapeshellarg($dbUser) . " " . escapeshellarg($dbName) . " -sNe " . escapeshellarg($sql) . " 2>/dev/null", $out);
        $dbErrors = array_filter(array_map('trim', $out));
    }
}

// 6. Phase 7 warnings — service.date / service.time legacy column usage
$svcRaw = grep_files(
    "(\\bs\\.date\\b|\\bs\\.time\\b|service\\[['\"]date['\"]\\]|service\\[['\"]time['\"]\\]|->orderBy\\(['\"]s\\.date['\"]\\)|->orderBy\\(['\"]s\\.time['\"]\\))",
    $SCAN_DIRS,
    $SKIP_PATHS
);
$warnings['service_legacy'] = parse_hits(array_values($svcRaw));

// Also scan ServiceModel for 'date' in allowedFields / validation
if (file_exists('app/Models/ServiceModel.php')) {
    $src = file_get_contents('app/Models/ServiceModel.php');
    $lines = explode("\n", $src);
    foreach ($lines as $i => $lineText) {
        $stripped = preg_replace('~//.*~', '', $lineText);
        if (preg_match("/'date'/", $stripped)) {
            $warnings['service_legacy'][] = ['file' => 'app/Models/ServiceModel.php', 'line' => $i + 1, 'content' => trim($lineText)];
        }
    }
}

// ── Flatten & deduplicate ─────────────────────────────────────────────────────
function dedup(array $hits): array
{
    $seen = [];
    $out = [];
    foreach ($hits as $h) {
        $k = ($h['file'] ?? '') . ':' . ($h['line'] ?? 0);
        if (!isset($seen[$k])) {
            $seen[$k] = 1;
            $out[] = $h;
        }
    }
    return $out;
}
foreach ($errors as &$cat) $cat = dedup($cat ?? []);
foreach ($warnings as &$cat) $cat = dedup($cat ?? []);
unset($cat);

$allErrors   = array_merge(...array_values($errors));
$allWarnings = array_merge(...array_values($warnings));
$totalErrors = count($allErrors) + count($dbErrors);

// ── Output ────────────────────────────────────────────────────────────────────
$jsonMode    = in_array('--json',    $argv ?? [], true);
$summaryMode = in_array('--summary', $argv ?? [], true);

if ($jsonMode) {
    echo json_encode(
        [
            'errors' => $errors,
            'warnings' => $warnings,
            'db_errors' => array_values($dbErrors),
            'summary' => ['errors' => count($allErrors), 'warnings' => count($allWarnings), 'db_errors' => count($dbErrors)]
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";
    exit($totalErrors > 0 ? 1 : 0);
}

$R = "\033[31m";
$Y = "\033[33m";
$G = "\033[32m";
$C = "\033[36m";
$B = "\033[1m";
$Z = "\033[0m";
$D = "\033[2m";

echo "\n{$B}ekklesiaRD — Schema Alignment Verification{$Z}\n" . str_repeat('─', 68) . "\n";
echo ($dbErrors ? "{$R}DB old tables still present: " . implode(', ', $dbErrors) . "{$Z}" : "{$G}DB: All old table names removed ✓{$Z}") . "\n";
echo "Code errors   : {$R}" . count($allErrors)   . "{$Z}\n";
echo "Code warnings : {$Y}" . count($allWarnings)  . "{$Z}  (Phase 7 — service.date/time)\n";
echo str_repeat('─', 68) . "\n";

if (!$summaryMode) {
    $labels = [
        'old_table_quoted' => 'Old table name (quoted string)',
        'tableExists'      => 'tableExists() with old name',
        'from_join'        => 'from()/join() with old name',
        'model_check'      => 'Model $table mismatch',
    ];
    if ($allErrors) {
        echo "\n{$B}{$R}ERRORS — must fix{$Z}\n\n";
        foreach ($errors as $cat => $hits) {
            if (!$hits) continue;
            echo "{$B}[" . ($labels[$cat] ?? $cat) . "]{$Z}\n";
            $byFile = [];
            foreach ($hits as $h) $byFile[$h['file']][] = $h;
            foreach ($byFile as $file => $items) {
                echo "  {$C}$file{$Z}\n";
                foreach ($items as $h) echo "    {$R}✗{$Z} L{$h['line']}: " . mb_substr($h['content'], 0, 100) . "\n";
            }
            echo "\n";
        }
    } else {
        echo "\n{$G}✓ No errors — all old table names eliminated from active source.{$Z}\n\n";
    }

    if ($allWarnings) {
        echo "{$B}{$Y}PHASE 7 BACKLOG — service.date / service.time{$Z}\n";
        echo "{$D}Replace with service_date/service_time then run Phase 7 to drop legacy columns.{$Z}\n\n";
        $byFile = [];
        foreach ($allWarnings as $w) $byFile[$w['file']][] = $w;
        foreach ($byFile as $file => $items) {
            echo "  {$C}$file{$Z}\n";
            foreach ($items as $w) echo "    {$Y}⚠{$Z} L{$w['line']}: " . mb_substr($w['content'], 0, 100) . "\n";
        }
        echo "\n";
    }
}

echo str_repeat('─', 68) . "\n";
echo ($totalErrors === 0
    ? "{$B}{$G}PASS{$Z} — Phases 1–6 fully aligned." . ($allWarnings ? "  {$Y}Phase 7: " . count($allWarnings) . " item(s) pending.{$Z}" : "")
    : "{$B}{$R}FAIL — $totalErrors error(s) found.{$Z}"
) . "\n\n";

exit($totalErrors > 0 ? 1 : 0);
