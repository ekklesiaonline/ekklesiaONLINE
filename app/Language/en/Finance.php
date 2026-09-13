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
];