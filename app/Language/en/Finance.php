<?php

/**
 * Finance module strings (Donations, Expenses, Budgets) — English
 */
return [
    // -------------------------------------------------------------------------
    // Section headings
    // -------------------------------------------------------------------------
    'donationsTitle'        => 'Donations',
    'noDonationsFound'      => 'No donations recorded.',
    'deleteDonationConfirm' => 'Delete donation of {0}?',
    'deleteDonationConfirmNoAmount' => 'Delete this donation?',
    'expensesTitle'         => 'Expenses',
    'budgetsTitle'          => 'Budgets',
    'financeDashboard'      => 'Finance Dashboard',
    'chartOfAccounts'       => 'Chart of Accounts',

    // -------------------------------------------------------------------------
    // Donations — table columns
    // -------------------------------------------------------------------------
    'colDate'               => 'Date',
    'colDonor'              => 'Donor',
    'colAmount'             => 'Amount',
    'colCategory'           => 'Category',
    'colPaymentMethod'      => 'Payment method',
    'colNotes'              => 'Notes',
    'colReceiptNumber'      => 'Receipt number',

    // -------------------------------------------------------------------------
    // Donation categories
    // -------------------------------------------------------------------------
    'categoryTithe'         => 'Tithe',
    'categoryOffering'      => 'Offering',
    'categoryMission'       => 'Mission',
    'categoryBuilding'      => 'Building Fund',
    'categoryOther'         => 'Other',

    // -------------------------------------------------------------------------
    // Donations — form fields
    // -------------------------------------------------------------------------
    'donor'                 => 'Donor',
    'anonymous'             => 'Anonymous',
    'amount'                => 'Amount',
    'date'                  => 'Date',
    'category'              => 'Category',
    'paymentMethod'         => 'Payment method',
    'notes'                 => 'Notes',

    // -------------------------------------------------------------------------
    // Expenses — table columns
    // -------------------------------------------------------------------------
    'colDescription'        => 'Description',
    'colApprovedBy'         => 'Approved by',

    // -------------------------------------------------------------------------
    // Expenses — form fields
    // -------------------------------------------------------------------------
    'description'           => 'Description',
    'expenseCategory'       => 'Category',
    'approvedBy'            => 'Approved by',

    // -------------------------------------------------------------------------
    // Budgets
    // -------------------------------------------------------------------------
    'budgetPeriod'          => 'Period',
    'budgetAmount'          => 'Budgeted',
    'actualAmount'          => 'Actual',
    'variance'              => 'Variance',
    'overBudget'            => 'Over budget',
    'underBudget'           => 'Under budget',

    // -------------------------------------------------------------------------
    // Summary widgets
    // -------------------------------------------------------------------------
    'totalIncome'           => 'Total Income',
    'totalExpenses'         => 'Total Expenses',
    'netBalance'            => 'Net Balance',
    'thisMonth'             => 'This month',
    'thisYear'              => 'This year',

    // -------------------------------------------------------------------------
    // Flash / toast messages
    // -------------------------------------------------------------------------
    'donationCreateSuccess' => 'Donation recorded successfully.',
    'donationUpdateSuccess' => 'Donation updated successfully.',
    'donationDeleteSuccess' => 'Donation deleted.',
    'expenseCreateSuccess'  => 'Expense recorded successfully.',
    'expenseUpdateSuccess'  => 'Expense updated successfully.',
    'expenseDeleteSuccess'  => 'Expense deleted.',
    'budgetSaveSuccess'     => 'Budget saved successfully.',
    'deleteConfirm'         => 'Are you sure you want to delete this record?',

    // -------------------------------------------------------------------------
    // Empty states
    // -------------------------------------------------------------------------
    'noDonations'           => 'No donations recorded yet.',
    'noExpenses'            => 'No expenses recorded yet.',
    'noBudgets'             => 'No budget entries found.',

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------
    'incomeVsExpenses'      => 'Income vs. Expenses',
    'donorStatement'        => 'Donor Statement',
    'exportPdf'             => 'Export PDF',
    'exportExcel'           => 'Export Excel',

    // -------------------------------------------------------------------------
    // Extended form / table labels
    // -------------------------------------------------------------------------
    'selectFund'            => 'Select Fund',
    'allFunds'              => 'All Funds',
    'allCategories'         => 'All Categories',
    'catUtilities'          => 'Utilities',
    'catMaintenance'        => 'Maintenance',
    'catSalaries'           => 'Salaries',
    'catMinistry'           => 'Ministry',
    'catSupplies'           => 'Supplies',
    'colFund'               => 'Fund',
    'colFundType'           => 'Fund Type',
    'recordDonation'        => 'Record Donation',
    'recordExpense'         => 'Record Expense',
    'contributionStatement' => 'Contribution Statement',
    'donorOptional'         => 'Donor (Optional)',
    'fundLabel'             => 'Fund',
    'generatePdf'           => 'Generate PDF',
    'chartOfAccountField'   => 'Chart of Account',
    'selectAccount'         => 'Select Account',
    'selectMember'          => 'Select Member',
    'startDate'             => 'Start Date',
    'endDate'               => 'End Date',
    'clearAll'              => 'Clear all',
    'exportCsv'             => 'Export CSV',
    'selectMethod'          => 'Select Method',
    'methodCash'            => 'Cash',
    'methodCheck'           => 'Check',
    'methodCreditCard'      => 'Credit Card',
    'methodBankTransfer'    => 'Bank Transfer',
    'methodOnline'          => 'Online',
    'memberLabel'           => 'Member',
    'memberPrefix'          => 'Member #',
    'unassigned'            => 'Unassigned',
    'notFound'              => 'No donations found',
    // CoA dropdown optgroup section labels
    'sectionUnrestrictedIncome'   => 'Unrestricted Income (4000s)',
    'sectionRestrictedIncome'     => 'Restricted and Designated Income (5000s)',
    'sectionFundTransfers'        => 'Fund Transfers (5400s)',
    'sectionGrantTracking'        => 'Grant Tracking (5500s)',
    'sectionOtherIncome'          => 'Other Income',
    'sectionOutreachExpenses'     => 'Outreach Expenses (6000s)',
    'sectionMinistryExpenses'     => 'Ministry and Program Expenses (7000s)',
    'sectionPayrollOverhead'      => 'Payroll Overhead (7500s)',
    'sectionGovernanceExpenses'   => 'Governance Expenses (7600s)',
    'sectionOperationsFacilities' => 'Operations and Facilities (8000s)',
    'sectionOtherExpenses'        => 'Other Expenses',
    'sectionOther'                => 'Other',
    // Restricted gift / capital expense labels
    'isRestricted'                => 'Restricted Gift',
    'restrictedGiftHelp'          => 'Check if this donation is restricted to a specific purpose.',
    'restrictionPurpose'          => 'Restriction Purpose',
    'restrictionPurposePlaceholder' => 'e.g. Building renovation, Mission trip',
    'isCapital'                   => 'Capital Expenditure',
    'capitalExpenseHelp'          => 'Check if this is a capital (non-operating) expense for Parochial Report.',
    // Seeded default fund labels
    'fundNameUnrestrictedOperating' => 'Unrestricted Operating Fund',
    'fundNameBuildingFacility'      => 'Building and Facility Fund',
    'fundNameMissionOutreach'       => 'Mission and Outreach Fund',
    'fundNameYouthFormation'        => 'Youth and Formation Fund',
    'fundNameEndowment'             => 'Endowment Fund',
    'fundNameDemoGeneral'           => 'Demo General Fund',
    'fundNameGeneral'               => 'General Fund',
    'fundNameBuilding'              => 'Building Fund',
    'fundNameMission'               => 'Mission Fund',
    'fundNameYouth'                 => 'Youth Fund',
    // Seeded expense text aliases
    'expenseDescDemoUtility'        => 'Demo utility expense',
    'expenseNoteDemoModule'         => 'Demo module expense',

    // -------------------------------------------------------------------------
    // Banking — accounts and reconciliation
    // -------------------------------------------------------------------------
    'bankingTitle'             => 'Bank Accounts',
    'bankAccounts'             => 'Bank accounts',
    'bankAccountCreate'        => 'Add bank account',
    'bankAccountName'          => 'Account name',
    'bankInstitution'          => 'Institution',
    'bankAccountNumber'        => 'Account number',
    'bankRoutingLast4'         => 'Routing (last 4)',
    'bankCurrency'             => 'Currency',
    'bankOpeningBalance'       => 'Opening balance',
    'bankOpenedOn'             => 'Opened on',
    'bankBookBalance'          => 'Book balance',
    'bankTransactions'         => 'Transactions',
    'bankAddTransaction'       => 'Add transaction',
    'bankNoAccounts'           => 'No bank accounts yet.',
    'bankNoTransactions'       => 'No transactions recorded.',
    'bankNoReconciliations'    => 'No reconciliations yet.',
    'bankStatusActive'         => 'Active',
    'bankStatusInactive'       => 'Inactive',
    'bankTxnDescription'       => 'Description',
    'bankTxnReference'        => 'Reference',
    'txnStatusUnmatched'       => 'Unmatched',
    'txnStatusCleared'         => 'Cleared',
    'txnStatusReconciled'      => 'Reconciled',
    'reconTitle'               => 'Bank reconciliation',
    'reconList'                => 'Reconciliations',
    'reconStatementDate'       => 'Statement date',
    'reconStatementBalance'    => 'Statement balance',
    'reconStart'               => 'Start reconciliation',
    'reconContinue'            => 'Continue',
    'reconOpenRecon'           => 'Open reconciliation',
    'reconCleared'             => 'Cleared',
    'reconClearedTotal'        => 'Cleared total',
    'reconClearedCount'        => 'Cleared items',
    'reconDifference'          => 'Difference',
    'reconSave'                => 'Save cleared items',
    'reconClose'               => 'Close reconciliation',
    'reconCloseConfirm'        => 'Close this reconciliation? Cleared transactions will be marked reconciled.',
    'reconStatusOpen'          => 'Open',
    'reconStatusClosed'        => 'Closed',
    'reconClosedAt'            => 'Closed at',
    'reconClosed'              => 'Reconciliation closed.',
    'reconBackToAccount'       => 'Back to account',
    'bankAccountNotFound'      => 'Bank account not found.',
    'bankAccountSaveFailed'    => 'Could not save the bank account.',
    'bankTransactionSaveFailed' => 'Could not save the transaction.',
    'reconNotFound'            => 'Reconciliation not found.',
    'reconNotOpen'             => 'This reconciliation is already closed.',
    'reconAlreadyOpen'         => 'A reconciliation is already open for this account.',
    'reconSaveFailed'          => 'Could not save the reconciliation.',
    'reconUnbalanced'          => 'Cleared total does not match the statement balance.',
    // -------------------------------------------------------------------------
    // Fund management
    // -------------------------------------------------------------------------
    'fundsTitle' => 'Funds',
    'fundCreate' => 'Add fund',
    'fundEdit' => 'Edit fund',
    'fundName' => 'Fund name',
    'fundDescription' => 'Description',
    'fundType' => 'Fund type',
    'fundRestrictionPurpose' => 'Restriction purpose',
    'fundBalance' => 'Balance',
    'fundIncome' => 'Income',
    'fundExpensesTotal' => 'Expenses',
    'fundActivity' => 'Activity',
    'fundNoFunds' => 'No funds found.',
    'fundStatusActive' => 'Active',
    'fundStatusInactive' => 'Inactive',
    'fundLockedBadge' => 'Locked',
    'fundLock' => 'Lock',
    'fundUnlock' => 'Unlock',
    'fundActivate' => 'Activate',
    'fundDeactivate' => 'Deactivate',
    'fundLockConfirm' => 'Lock this fund? No new donations or expenses can be recorded against it until it is unlocked.',
    'fundFilterActive' => 'Active',
    'fundFilterInactive' => 'Inactive',
    'fundFilterAll' => 'All',
    'fundTotalsByType' => 'Active balance — {0}',
    'fundTypeChangeHelp' => 'The fund type cannot be changed once the fund has donations or expenses.',
    'fundPurposeHelp' => 'Required for restricted and permanent funds (e.g. "Roof restoration").',
    'fundTransactionsCount' => '{0} donations / {1} expenses',
    'fundCreated' => 'Fund created.',
    'fundUpdated' => 'Fund updated.',
    'fundLocked' => 'Fund locked.',
    'fundUnlocked' => 'Fund unlocked.',
    'fundActivated' => 'Fund activated.',
    'fundDeactivated' => 'Fund deactivated.',
    'fundNotFound' => 'Fund not found.',
    'fundSaveFailed' => 'Could not save the fund.',
    'fundLockedNoEdit' => 'This fund is locked. Unlock it before editing.',
    'fundTypeChangeBlocked' => 'The fund type cannot be changed because the fund already has transactions.',
    'fundPurposeRequired' => 'A restriction purpose is required for restricted and permanent funds.',
    'fundNameTaken' => 'A fund with this name already exists in your parish.',
    'fundChartAccount' => 'Linked account',
    'fundChartAccountNone' => 'None',
    'fundChartAccountHelp' => 'Optional link to a chart of accounts category.',
    'fundExportCsv' => 'Export CSV',
    'fundView' => 'View fund',
    'fundLedger' => 'Fund Ledger',
    'fundDonations' => 'Donations',
    'fundExpenses' => 'Expenses',
    'fundNoDonations' => 'No donations recorded for this fund.',
    'fundNoExpenses' => 'No expenses recorded for this fund.',
    'fundDetails' => 'Fund Details',
    'fundRecentTransactions' => 'Recent Transactions',

    // Dashboard Navigation & Polish
    'pledgesTitle'          => 'Pledges',
    'reportsTitle'          => 'Reports',
    'bankAccountsTitle'     => 'Bank Accounts',
    'pledgeProgressTitle'   => 'Stewardship & Annual Pledges',
    'pledgedTotal'          => 'Pledged',
    'fulfilledTotal'        => 'Fulfilled',
    'remainingTotal'        => 'Remaining',
    'pledgeCount'           => '{0} Pledges',
    'bankBalancesTitle'     => 'Cash & Bank Balances',
    'totalCashBalance'      => 'Total Cash',
    'noActiveBankAccounts'  => 'No active bank accounts configured.',
    'activeFundsTitle'      => 'Active Funds',
    'noActiveFunds'         => 'No active funds configured.',
    'managePledges'         => 'Manage Pledges',
    'manageBanking'         => 'Banking & Reconciliation',
    'manageFunds'           => 'Manage Funds',
    'viewAllDonations'      => 'View All Donations',
    'viewAllExpenses'       => 'View All Expenses',
    'viewAllFunds'          => 'View All Funds',
];
