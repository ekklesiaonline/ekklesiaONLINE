<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Architecture guard for the membership-status invariants
 * (docs/MEMBERSHIP_STATUS_SIMPLIFICATION_PLAN.md — Phase 4).
 *
 * parish_affiliation owns the parish lifecycle. The status columns
 * (membership_status, is_member) may only be written by
 * the choke points below; every other writer must route through
 * MemberParishAffiliationModel / MembershipPathwayService /
 * MemberAffiliationService so rows can never disagree with themselves.
 *
 * @internal
 */
final class ParishAffiliationWriteScopeTest extends CIUnitTestCase
{
    /**
     * Files permitted to contain status-column writes on
     * parish_affiliation (repo-relative paths).
     *
     * @var array<int, string>
     */
    private const ALLOWED_WRITERS = [
        // Implementation layer the services delegate to.
        'app/Models/MemberParishAffiliationModel.php',
        // Canonical lifecycle transitions (applyTransition).
        'app/Services/MembershipPathwayService.php',
        // Attach/demote/tag orchestration.
        'app/Services/MemberAffiliationService.php',
        // fieldsForStatus() lives here; it is the payload builder.
        'app/Services/MembershipStatusService.php',
    ];

    /**
     * Columns that form the lifecycle state — writes to these outside the
     * allowlist are violations. (is_member is a generated column: writing
     * it is an error on MySQL, so flagging attempts is harmless.)
     *
     * @var array<int, string>
     */
    private const STATUS_COLUMNS = [
        'membership_status',
        'is_member',
    ];

    public function testStatusColumnsAreOnlyWrittenByChokePoints(): void
    {
        $violations = [];

        foreach ($this->appPhpFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($relative === null || in_array($relative, self::ALLOWED_WRITERS, true)) {
                continue;
            }

            $source = (string) file_get_contents($file);
            if ($source === '') {
                continue;
            }

            array_push($violations, ...$this->violationsForFile($relative, $source));
        }

        $this->assertSame(
            [],
            $violations,
            "parish_affiliation status columns may only be written inside the choke points"
            . " (MemberParishAffiliationModel, MembershipPathwayService, MemberAffiliationService):\n - "
            . implode("\n - ", $violations)
        );
    }

    /**
     * @return array<int, string>
     */
    private function violationsForFile(string $relative, string $source): array
    {
        $violations = [];

        // Rule 1: fieldsForStatus() is the status write payload builder —
        // any caller outside a choke point is performing a status write.
        if (strpos($source, 'fieldsForStatus(') !== false) {
            $violations[] = $relative . ' — calls MembershipStatusService::fieldsForStatus()';
        }

        // Rule 2: query-builder ->set() on a status column.
        foreach (self::STATUS_COLUMNS as $column) {
            if (preg_match('/->set\(\s*[\'"]' . $column . '[\'"]/', $source) === 1) {
                $violations[] = $relative . ' — ->set(\'' . $column . '\')';
            }
        }

        // Split the source into statements so audit payloads such as
        // 'new_values' => ['membership_status' => ...] inside an
        // audit()->log(...) call do not count as table writes.
        $statements          = explode(';', $source);
        $hasAffiliationWrite = false;

        foreach ($statements as $statement) {
            $writes = $this->statementWritesAffiliation($statement);
            $hasAffiliationWrite = $hasAffiliationWrite || $writes;

            // Rule 3: one statement that both carries a status array key
            // and writes parish_affiliation.
            if ($writes && $this->hasStatusArrayKey($statement, self::STATUS_COLUMNS)) {
                $violations[] = $relative . ' — writes a status column to parish_affiliation';
                break;
            }
        }

        // Rule 4 (fallback): the file writes parish_affiliation in one
        // statement and builds a status payload key in another, e.g.
        //   $data = ['membership_status' => ...];
        //   $db->table('parish_affiliation')->insert($data);
        // is_member is excluded: audit new_values legitimately carry it.
        if ($hasAffiliationWrite
            && $this->hasStatusArrayKey($source, ['membership_status'])
        ) {
            $violations[] = $relative . ' — status payload key alongside parish_affiliation writes';
        }

        return $violations;
    }

    /**
     * Whether a single statement writes parish_affiliation: a table()
     * call on it plus a write verb, or a write verb on an
     * affiliation-model receiver (MemberParishAffiliationModel,
     * $this->affiliations, $this->memberAffiliations, affiliationModel).
     */
    private function statementWritesAffiliation(string $statement): bool
    {
        $hasWriteVerb = preg_match(
            '/->(insert|update|replace|save|set)\s*\(/',
            $statement
        ) === 1;

        if (! $hasWriteVerb) {
            return false;
        }

        if (preg_match('/table\(\s*[\'"]parish_affiliation[\'"]\s*\)/', $statement) === 1) {
            return true;
        }

        if (preg_match('/MemberParishAffiliationModel/', $statement) === 1) {
            return true;
        }

        return preg_match(
            '/(memberAffiliations|affiliationModel|affiliations)\s*->\s*(insert|update|replace|save)\s*\(/',
            $statement
        ) === 1;
    }

    /**
     * Whether the text builds an array payload carrying a status column
     * key ('membership_status' => ...). Read access
     * ($row['membership_status']) does not match — only `=>` syntax does.
     *
     * @param array<int, string> $columns
     */
    private function hasStatusArrayKey(string $text, array $columns): bool
    {
        foreach ($columns as $column) {
            if (preg_match('/[\'"]' . $column . '[\'"]\s*=>/', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function appPhpFiles(): array
    {
        $roots = [
            APPPATH,
            ROOTPATH . 'MemberPortal',
            ROOTPATH . 'Clergy',
            ROOTPATH . 'Diocese',
            ROOTPATH . 'Province',
            ROOTPATH . 'Parish',
            ROOTPATH . 'Worship',
        ];

        $files = [];
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $entry) {
                if (! $entry->isFile() || $entry->getExtension() !== 'php') {
                    continue;
                }
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Repo-relative path for files inside the scanned roots; null when the
     * file should not be scanned (migrations, seeders, dumps, views, lang).
     */
    private function relativePath(string $file): ?string
    {
        $normalized = str_replace('\\', '/', $file);
        $root       = rtrim(str_replace('\\', '/', ROOTPATH), '/') . '/';

        if (! str_starts_with($normalized, $root)) {
            return null;
        }

        $relative = substr($normalized, strlen($root));

        foreach (['/Migrations/', '/Seeds/', '/Views/', '/Language/'] as $excluded) {
            if (strpos('/' . $relative, $excluded) !== false) {
                return null;
            }
        }
        if (str_starts_with($relative, 'app/Database/')) {
            return null;
        }

        return $relative;
    }
}
