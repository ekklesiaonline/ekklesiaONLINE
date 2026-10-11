<?php

declare(strict_types=1);

/**
 * Backward compatibility class aliases: Treasurer\Controllers\* -> Finance\Controllers\*
 */
if (!class_exists('Treasurer\Controllers\Banking', false)) {
    class_alias(\Finance\Controllers\Banking::class, 'Treasurer\Controllers\Banking');
    class_alias(\Finance\Controllers\BankingImport::class, 'Treasurer\Controllers\BankingImport');
    class_alias(\Finance\Controllers\BankingReconciliation::class, 'Treasurer\Controllers\BankingReconciliation');
    class_alias(\Finance\Controllers\Budgets::class, 'Treasurer\Controllers\Budgets');
    class_alias(\Finance\Controllers\Dashboard::class, 'Treasurer\Controllers\Dashboard');
    class_alias(\Finance\Controllers\Donation::class, 'Treasurer\Controllers\Donation');
    class_alias(\Finance\Controllers\DonationExports::class, 'Treasurer\Controllers\DonationExports');
    class_alias(\Finance\Controllers\DonationStatements::class, 'Treasurer\Controllers\DonationStatements');
    class_alias(\Finance\Controllers\Donations::class, 'Treasurer\Controllers\Donations');
    class_alias(\Finance\Controllers\Expense::class, 'Treasurer\Controllers\Expense');
    class_alias(\Finance\Controllers\ExpenseExports::class, 'Treasurer\Controllers\ExpenseExports');
    class_alias(\Finance\Controllers\Expenses::class, 'Treasurer\Controllers\Expenses');
    class_alias(\Finance\Controllers\FundExports::class, 'Treasurer\Controllers\FundExports');
    class_alias(\Finance\Controllers\FundFlags::class, 'Treasurer\Controllers\FundFlags');
    class_alias(\Finance\Controllers\Funds::class, 'Treasurer\Controllers\Funds');
    class_alias(\Finance\Controllers\JournalEntries::class, 'Treasurer\Controllers\JournalEntries');
    class_alias(\Finance\Controllers\ReportExports::class, 'Treasurer\Controllers\ReportExports');
    class_alias(\Finance\Controllers\Reports::class, 'Treasurer\Controllers\Reports');
    if (!class_exists('App\Services\TreasurerReportsDataService', false)) {
        class_alias(\App\Services\FinanceReportsDataService::class, 'App\Services\TreasurerReportsDataService');
    }
}
