<?php

namespace App\Commands;

use App\Services\ParishPeopleRegistryService;
use App\Services\NonPortalMemberProvisioningService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One-time repair command.
 *
 * Detects parish_people rows whose user_id no longer exists in the users table,
 * re-creates each as a non-portal member, updates the profile/MCA to the exact
 * status stored in the snapshot, removes the stale parish_people row, and
 * finally re-runs parish-people:sync so the snapshot is rebuilt cleanly from MCA.
 *
 * Usage:
 *   php spark repair:orphaned-parish-people
 *   php spark repair:orphaned-parish-people --parish 1443   # scope to one parish
 *   php spark repair:orphaned-parish-people --dry-run        # preview only
 */
class RepairOrphanedParishPeople extends BaseCommand
{
    protected $group   = 'Repair';
    protected $name    = 'repair:orphaned-parish-people';
    protected $description = 'Re-creates non-portal user records for orphaned parish_people rows (user no longer exists in users/profile).';

    protected $options = [
        '--parish'  => 'Limit repair to a single parish_id.',
        '--dry-run' => 'Preview what would be created without making any changes.',
    ];

    public function run(array $params): void
    {
        $db        = db_connect();
        $dryRun    = array_key_exists('dry-run', $params)
            || in_array('--dry-run', $params, true)
            || CLI::getOption('dry-run') !== null;
        $parishArg = CLI::getOption('parish');
        $parishId  = $parishArg !== null ? (int) $parishArg : null;

        CLI::write('=== Orphaned parish_people repair ===', 'yellow');
        if ($dryRun) {
            CLI::write('[DRY-RUN] No changes will be written.', 'light_gray');
        }

        // ── 1. Find all orphaned parish_people rows ─────────────────────────
        $builder = $db->table('parish_people cp')
            ->select('cp.user_id, cp.first_name, cp.last_name, cp.phone, cp.email, cp.membership_status, cp.is_member, cp.parish_id')
            ->join('users u', 'u.id = cp.user_id', 'left')
            ->where('u.id IS NULL');

        if ($parishId !== null) {
            $builder->where('cp.parish_id', $parishId);
        }

        $orphans = $builder->orderBy('cp.parish_id')->orderBy('cp.last_name')->orderBy('cp.first_name')->get()->getResultArray();

        if (empty($orphans)) {
            CLI::write('No orphaned parish_people rows found. Nothing to do.', 'green');
            return;
        }

        CLI::write(sprintf('Found %d orphaned row(s) to repair.', count($orphans)), 'white');
        CLI::newLine();

        $provisioner = new NonPortalMemberProvisioningService($db);
        $registry    = new ParishPeopleRegistryService();

        $created   = 0;
        $failed    = 0;
        $seenParishes = [];

        foreach ($orphans as $orphan) {
            $oldUserId = (int) $orphan['user_id'];
            $cid       = (int) $orphan['parish_id'];
            $firstName = (string) ($orphan['first_name'] ?? '');
            $lastName  = (string) ($orphan['last_name'] ?? '');
            $status    = (string) ($orphan['membership_status'] ?? 'regular_attender');
            $isMember  = (int) ($orphan['is_member'] ?? 0);

            CLI::write(sprintf(
                '  [parish %d] old uid=%-4d  %-20s %-25s  status=%-20s  is_member=%d',
                $cid,
                $oldUserId,
                $firstName,
                $lastName,
                $status,
                $isMember
            ));

            if ($dryRun) {
                $created++;
                $seenParishes[$cid] = true;
                continue;
            }

            // ── 2. Re-create via provisioner ────────────────────────────────
            // Pass is_member so the provisioner sets the MCA flag correctly.
            // We will patch membership_status separately because the provisioner
            // defaults it to 'active' for adults regardless of the passed value.
            $result = $provisioner->create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'is_member'  => $isMember,
                'is_child'   => false,
            ], $cid);

            if (!($result['ok'] ?? false)) {
                $code    = $result['code'] ?? 'UNKNOWN';
                $message = $result['message'] ?? 'Provisioner returned failure.';
                CLI::write("    ✗ FAILED [{$code}] {$message}", 'red');
                $failed++;
                continue;
            }

            $newUserId = (int) ($result['data']['user_id'] ?? 0);

            // ── 3. Patch membership_status on profile and MCA ────────────────
            // Validate against the allowed ENUM set; fall back to regular_attender.
            $validStatuses = ['active', 'inactive', 'visitor', 'regular_attender'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'regular_attender';
            }

            $db->table('profile')->where('user_id', $newUserId)->update([
                'membership_status' => $status,
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

            $paStatusMap = ['active' => 'member', 'visitor' => 'visitor', 'regular_attender' => 'regular_attender', 'inactive' => 'inactive'];
            $paStatus = $paStatusMap[$status] ?? 'visitor';

            $db->table('parish_affiliation')
                ->where('linked_user_id', $newUserId)
                ->where('parish_id', $cid)
                ->where('is_member', 1)
                ->update([
                    'membership_status' => $paStatus,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);

            // ── 4. Remove the stale parish_people row for the old user_id ───
            // The fresh parish_people row will be rebuilt by syncParish below.
            $db->table('parish_people')
                ->where('user_id', $oldUserId)
                ->where('parish_id', $cid)
                ->delete();

            CLI::write(sprintf('    ✓ Re-created as user_id=%d', $newUserId), 'green');
            $created++;
            $seenParishes[$cid] = true;
        }

        CLI::newLine();
        CLI::write(sprintf('Done: %d created, %d failed.', $created, $failed), $failed > 0 ? 'red' : 'green');

        if ($dryRun || $created === 0) {
            return;
        }

        // ── 5. Re-sync parish_people for each affected parish ────────────────
        CLI::newLine();
        CLI::write('Re-syncing parish_people snapshot…', 'yellow');
        foreach (array_keys($seenParishes) as $affectedParishId) {
            CLI::write(sprintf('  Syncing parish_id=%d…', $affectedParishId));
            try {
                $registry->syncParish((int) $affectedParishId);
                CLI::write('  ✓ Done.', 'green');
            } catch (\Throwable $e) {
                CLI::write(sprintf('  ✗ Sync failed: %s', $e->getMessage()), 'red');
            }
        }

        CLI::newLine();
        CLI::write('Repair complete. Run `php spark parish-people:sync` at any time to verify.', 'green');
    }
}
