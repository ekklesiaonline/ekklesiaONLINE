<?php

namespace App\Commands;

use App\Services\MembershipStatusService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Reports rows that violate the parish_affiliation status invariants:
 *
 *  - membership_status is a canonical lifecycle status,
 *  - is_member equals isOnRegister(membership_status)  (member|inactive)
 *    — structurally enforced once the column is GENERATED; the check
 *    remains for pre-migration databases,
 *  - end_date is only set on terminal statuses (transferred|deceased).
 *    (profile.membership_status was dropped — parish_affiliation is the
 *    only status owner.)
 *
 * Read-only. Exit code is non-zero when drift is found, so it can run in CI.
 */
class AuditAffiliationDrift extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'people:audit-affiliation-drift';
    protected $description = 'Audit parish_affiliation/profile status columns for drift between redundant fields.';
    protected $usage       = 'people:audit-affiliation-drift';
    protected $arguments   = [];
    protected $options     = [];

    private const TERMINAL_STATUSES = ['transferred', 'deceased'];
    private const SAMPLE_LIMIT      = 5;

    public function run(array $params)
    {
        $db = db_connect();
        $issues = 0;

        if (! $db->tableExists('parish_affiliation')) {
            CLI::write('FAIL: parish_affiliation table does not exist.', 'red');
            return EXIT_ERROR;
        }

        CLI::write('Auditing parish_affiliation status columns...', 'cyan');

        $hasIsMember = $db->fieldExists('is_member', 'parish_affiliation');

        $select = 'id, parish_id, person_id, linked_user_id, membership_status, end_date';
        if ($hasIsMember) {
            $select .= ', is_member';
        }

        $rows = $db->table('parish_affiliation')
            ->select($select)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        CLI::write('  Rows scanned: ' . count($rows), 'light_gray');

        $canonical = MembershipStatusService::affiliationStatuses();

        $nonCanonical  = [];
        $isMemberDrift = [];
        $endDateDrift  = [];
        $distribution  = [];

        foreach ($rows as $row) {
            $id     = (int) $row['id'];
            $status = MembershipStatusService::normalizeAffiliationStatus((string) ($row['membership_status'] ?? ''));

            $distribution[$status] = ($distribution[$status] ?? 0) + 1;

            if (! in_array($status, $canonical, true)) {
                $nonCanonical[] = $id;
            }

            if ($hasIsMember) {
                $expectedIsMember = MembershipStatusService::isOnRegister($status) ? 1 : 0;
                if ((int) ($row['is_member'] ?? 0) !== $expectedIsMember) {
                    $isMemberDrift[] = $id;
                }
            }

            if (($row['end_date'] ?? null) !== null && ! in_array($status, self::TERMINAL_STATUSES, true)) {
                $endDateDrift[] = $id;
            }
        }

        $issues += $this->report('membership_status outside canonical vocabulary', $nonCanonical);
        $issues += $this->report('is_member disagrees with isOnRegister(status)', $isMemberDrift);
        $issues += $this->report('end_date set on non-terminal status', $endDateDrift);

        CLI::write('');
        CLI::write('Status distribution (normalized):', 'cyan');
        foreach ($distribution as $status => $count) {
            CLI::write('  ' . str_pad($status, 20) . $count, 'light_gray');
        }

        CLI::write('');
        if ($issues === 0) {
            CLI::write('No drift found. Status columns are internally consistent.', 'green');
            return EXIT_SUCCESS;
        }

        CLI::write('Drift found in ' . $issues . ' invariant group(s). See sample row IDs above.', 'red');
        return EXIT_ERROR;
    }

    /**
     * @param array<int, int> $ids
     */
    private function report(string $label, array $ids): int
    {
        if ($ids === []) {
            CLI::write('  OK: ' . $label, 'green');
            return 0;
        }

        CLI::write('  DRIFT: ' . $label . ' — ' . count($ids) . ' row(s)', 'red');
        CLI::write('    sample ids: ' . implode(', ', array_slice($ids, 0, self::SAMPLE_LIMIT)), 'yellow');
        return 1;
    }
}
