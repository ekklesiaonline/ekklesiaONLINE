<?php

namespace App\Commands;

use App\Services\LeadershipPositionService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Lists leadership positions whose holder no longer meets the eligibility
 * requirements (portal access for everyone; baptism + good standing for
 * lay positions). Read-only — remediation is manual.
 */
class AuditLeadershipEligibility extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'leadership:audit-eligibility';
    protected $description = 'Audits leadership positions against appointee eligibility requirements.';
    protected $usage       = 'leadership:audit-eligibility [--parish <id>] [--all]';

    protected $options = [
        '--parish' => 'Limit the audit to positions scoped to this parish id.',
        '--all'    => 'Include inactive positions (default: active only).',
    ];

    public function run(array $params)
    {
        $parishFilter = (int) (CLI::getOption('parish') ?? 0);
        $includeInactive = CLI::getOption('all') !== null;

        $db = db_connect();
        if (! $db->tableExists('leadership_position')) {
            CLI::error('leadership_position table not found.');
            return;
        }

        $builder = $db->table('leadership_position lp')
            ->select('lp.id, lp.user_id, lp.person_id, lp.scope_type, lp.parish_id, lp.diocese_id, lp.province_id, lp.title, lp.role_category, lp.active')
            ->orderBy('lp.parish_id', 'ASC')
            ->orderBy('lp.id', 'ASC');

        if (! $includeInactive) {
            $builder->where('lp.active', 1);
        }
        if ($parishFilter > 0) {
            $builder->where('lp.parish_id', $parishFilter);
        }

        $positions = $builder->get()->getResultArray();
        if ($positions === []) {
            CLI::write('No leadership positions found for the given filters.', 'green');
            return;
        }

        $service = new LeadershipPositionService();
        $violations = [];

        foreach ($positions as $position) {
            $userId   = (int) ($position['user_id'] ?? 0);
            $personId = (int) ($position['person_id'] ?? 0);
            if ($personId <= 0) {
                $personId = (int) ($service->resolvePersonIdForUser($userId) ?? 0);
            }

            $eligParishId = $service->resolveEligibilityParishId(
                $personId,
                (int) ($position['parish_id'] ?? 0) > 0 ? (int) $position['parish_id'] : null,
                $userId
            );

            $eligibility = $service->eligibilityFor($userId, $personId, $eligParishId, $position);
            if ($eligibility['eligible'] ?? false) {
                continue;
            }

            $violations[] = [
                (int) $position['id'],
                $userId,
                $personId > 0 ? $personId : '-',
                (int) ($position['parish_id'] ?? 0) ?: '-',
                (string) ($position['scope_type'] ?? ''),
                (string) ($position['role_category'] ?? '') ?: 'lay_staff',
                (string) ($position['title'] ?? ''),
                implode(', ', $eligibility['failed'] ?? []),
            ];
        }

        CLI::write('Positions scanned: ' . count($positions));
        if ($violations === []) {
            CLI::write('All scanned positions pass the eligibility checks.', 'green');
            return;
        }

        CLI::write('Positions failing eligibility: ' . count($violations), 'red');
        CLI::table($violations, ['ID', 'User', 'Person', 'Parish', 'Scope', 'Category', 'Title', 'Failed checks']);
    }
}
