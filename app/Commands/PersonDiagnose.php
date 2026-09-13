<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PersonDiagnose extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'person:diagnose';
    protected $description = 'Diagnose canonical person identity schema and data health.';
    protected $usage       = 'person:diagnose';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        $db = db_connect();
        $issues = 0;

        // 1. Check person table exists.
        CLI::write('1. Checking person table...', 'cyan');
        if (! $db->tableExists('person')) {
            CLI::write('   FAIL: person table does not exist.', 'red');
            CLI::write('   Fix: Run `php spark migrate`', 'yellow');
            $issues++;
        } else {
            CLI::write('   OK: person table exists.', 'green');

            // Check expected columns.
            $personColumns = array_map('strtolower', $db->getFieldNames('person'));
            $expected = ['id', 'first_name', 'last_name', 'email', 'phone', 'birthdate', 'deleted_at', 'created_at', 'updated_at', 'source', 'is_active'];
            $missing = array_diff($expected, $personColumns);
            if ($missing !== []) {
                CLI::write('   WARN: Missing columns on person: ' . implode(', ', $missing), 'yellow');
                $issues++;
            } else {
                CLI::write('   OK: All expected columns present.', 'green');
            }

            // Count person rows.
            $personCount = $db->table('person')->countAllResults();
            CLI::write('   Person rows: ' . $personCount, 'light_gray');
        }

        // 2. Check profile.person_id column.
        CLI::write('');
        CLI::write('2. Checking profile.person_id column...', 'cyan');
        if (! $db->tableExists('profile')) {
            CLI::write('   FAIL: profile table does not exist.', 'red');
            $issues++;
        } elseif (! $db->fieldExists('person_id', 'profile')) {
            CLI::write('   FAIL: profile.person_id column does NOT exist.', 'red');
            CLI::write('   This is why "Repair link" silently fails for all profiles.', 'red');
            CLI::write('   Fix: Run `php spark migrate`', 'yellow');
            $issues++;
        } else {
            CLI::write('   OK: profile.person_id column exists.', 'green');

            // Count profiles with missing person_id.
            $unlinked = $db->table('profile')
                ->groupStart()
                    ->where('person_id', null)
                    ->orWhere('person_id', 0)
                ->groupEnd()
                ->where('deleted_at', null)
                ->countAllResults();

            CLI::write('   Profiles with missing person_id: ' . $unlinked, $unlinked > 0 ? 'yellow' : 'green');
            if ($unlinked > 0) {
                CLI::write('   Fix: Run `php spark person:link-profiles`', 'yellow');
            }
        }

        // 3. Check user_person table.
        CLI::write('');
        CLI::write('3. Checking user_person table...', 'cyan');
        if (! $db->tableExists('user_person')) {
            CLI::write('   WARN: user_person table does not exist.', 'yellow');
            CLI::write('   Portal profiles cannot be linked to canonical persons without it.', 'yellow');
            CLI::write('   Fix: Run `php spark migrate`', 'yellow');
            $issues++;
        } else {
            CLI::write('   OK: user_person table exists.', 'green');
            $mappingCount = $db->table('user_person')->countAllResults();
            CLI::write('   user_person rows: ' . $mappingCount, 'light_gray');
        }

        // 4. Check for duplicate person rows (same name, from failed repairs).
        CLI::write('');
        CLI::write('4. Checking for duplicate person rows...', 'cyan');
        if ($db->tableExists('person')) {
            $duplicates = $db->query(<<<'SQL'
                SELECT first_name, last_name, COUNT(*) as cnt
                FROM person
                WHERE deleted_at IS NULL
                GROUP BY first_name, last_name
                HAVING COUNT(*) > 1
                ORDER BY cnt DESC
                LIMIT 10
                SQL
            )->getResultArray();

            if ($duplicates === []) {
                CLI::write('   OK: No duplicate person rows found.', 'green');
            } else {
                $totalDupes = 0;
                foreach ($duplicates as $dup) {
                    $totalDupes += (int) $dup['cnt'];
                    CLI::write('   DUPLICATE: "' . $dup['first_name'] . ' ' . $dup['last_name'] . '" — ' . $dup['cnt'] . ' rows', 'red');
                }
                CLI::write('   Found ' . count($duplicates) . ' name group(s) with duplicates (' . $totalDupes . ' total rows).', 'yellow');
                CLI::write('   Fix: Use the Person Merge tool at /person-merge to consolidate duplicates.', 'yellow');
                $issues++;
            }
        }

        // 5. Check parish_affiliation.person_id.
        CLI::write('');
        CLI::write('5. Checking parish_affiliation.person_id...', 'cyan');
        if ($db->tableExists('parish_affiliation')) {
            if (! $db->fieldExists('person_id', 'parish_affiliation')) {
                CLI::write('   WARN: parish_affiliation.person_id column does not exist.', 'yellow');
                CLI::write('   Fix: Run `php spark migrate`', 'yellow');
                $issues++;
            } else {
                $affUnlinked = $db->table('parish_affiliation')
                    ->groupStart()
                        ->where('person_id', null)
                        ->orWhere('person_id', 0)
                    ->groupEnd()
                    ->where('deleted_at', null)
                    ->countAllResults();
                CLI::write('   Affiliations with missing person_id: ' . $affUnlinked, $affUnlinked > 0 ? 'yellow' : 'green');
            }
        } else {
            CLI::write('   SKIP: parish_affiliation table does not exist.', 'light_gray');
        }

        // Summary.
        CLI::write('');
        CLI::write('Summary:', 'cyan');
        if ($issues === 0) {
            CLI::write('  No issues found. Canonical person identity system is healthy.', 'green');
        } else {
            CLI::write('  Found ' . $issues . ' issue(s). See fixes above.', 'red');
        }

        return $issues > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
