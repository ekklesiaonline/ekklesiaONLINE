<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditChartAccountCategories extends BaseCommand
{
    protected $group = 'Finance';
    protected $name = 'finance:audit-coa-categories';
    protected $description = 'Reports chart accounts and transactions without a valid hierarchical category.';
    protected $usage = 'finance:audit-coa-categories';

    public function run(array $params)
    {
        $db = db_connect();
        if (!$db->tableExists('chart_account_category')) {
            CLI::error('The chart account category hierarchy has not been migrated.');
            return EXIT_ERROR;
        }

        $total = 0;
        foreach (['chart_of_accounts', 'donation', 'expense'] as $table) {
            if (!$db->tableExists($table) || !$db->fieldExists('category_id', $table)) {
                continue;
            }
            $count = $db->table($table . ' t')
                ->join('chart_account_category c', 'c.id = t.category_id', 'left')
                ->groupStart()->where('t.category_id', null)->orWhere('c.id', null)->groupEnd()
                ->countAllResults();
            $total += $count;
            CLI::write($table . ': ' . $count . ' unresolved row(s)', $count === 0 ? 'green' : 'yellow');
        }

        CLI::write('Total unresolved: ' . $total, $total === 0 ? 'green' : 'yellow');
        return $total === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
