<?php

return [
    // Buttons
    'btn_newAccount'        => 'New Account',
    'btn_filter'            => 'Filter',
    'btn_back'              => 'Back',
    'btn_cancel'            => 'Cancel',
    'btn_createAccount'     => 'Create Account',
    'btn_updateAccount'     => 'Update Account',

    // Page headings
    'pageHeadingCreate'     => 'Create Chart Account',
    'pageHeadingEdit'       => 'Edit Chart Account',

    // Filter labels
    'labelAccountType'      => 'Account Type',
    'labelAllTypes'         => 'All Types',
    'labelStatus'           => 'Status',
    'labelAll'              => 'All',
    'labelActive'           => 'Active',
    'labelInactive'         => 'Inactive',
    'labelEpiscopalSection' => 'Episcopal Section',
    'labelAllSections'      => 'All Sections',
    'sectionBalanceSheet' => 'Balance Sheet (1000s-3000s)',
    'sectionIncomeUnrestricted' => 'Unrestricted Income (4000s)',
    'sectionIncomeRestricted' => 'Restricted/Designated Income (5000-5399)',
    'sectionIncomeTransfers' => 'Fund Transfers (5400s)',
    'sectionIncomeGrants' => 'Grant Tracking (5500s)',
    'sectionExpenseOutreach' => 'Outreach Expenses (6000s)',
    'sectionExpenseMinistry' => 'Ministry & Program (7000-7499)',
    'sectionExpensePayroll' => 'Payroll Overhead (7500s)',
    'sectionExpenseGovernance' => 'Governance (7600s)',
    'sectionExpenseOperations' => 'Operations & Facilities (8000s)',
    'sectionMultiCampus' => 'Multi-campus Suffix Accounts',

    // Table column headers
    'colCode'               => 'Code',
    'colName'               => 'Name',
    'colType'               => 'Type',
    'colCategory'           => 'Category',
    'colBudgetable'         => 'Budgetable',
    'colStatus'             => 'Status',
    'colSort'               => 'Sort',
    'colActions'            => 'Actions',
    'actionToggle'          => 'Toggle status',

    // Cell values
    'valueYes'              => 'Yes',
    'valueNo'               => 'No',

    // Empty state
    'noAccountsFound'       => 'No chart accounts found.',

    // Account type options
    'typeIncome'            => 'Income',
    'typeExpense'           => 'Expense',
    'typeAsset'             => 'Asset',
    'typeLiability'         => 'Liability',
    'typeEquity'            => 'Equity',

    // Form labels
    'labelCode'             => 'Code',
    'labelName'             => 'Name',
    'labelDefaultCategory'  => 'Default Category',
    'labelSortOrder'        => 'Sort Order',
    'labelAllowBudgeting'   => 'Allow budgeting',
    'labelDescription'      => 'Description',
    'labelParentAccount'    => 'Parent Account',
    'selectAccountType'     => 'Select an account type',
    'selectParentAccount'   => 'None (standalone account)',
    'placeholderCode'       => 'e.g. 01',
    'helpAccountType'       => 'Select the financial classification for this account.',
    'helpParentAccount'     => 'Choose an active account of the selected type to create a subaccount.',
    'helpCode'              => 'Enter the unique account suffix; the selected parent code is added automatically.',
    'helpName'              => 'Enter a clear name that identifies the account in reports and transactions.',
    'helpDefaultCategory'   => 'Optionally group this account under a reporting category.',
    'helpSortOrder'         => 'Lower numbers display before higher numbers within the chart.',
    'helpAllowBudgeting'    => 'Enable this account for use in parish budget lines.',
    'helpActive'            => 'Active accounts are available for new financial activity.',
    'helpDescription'       => 'Optionally describe the purpose and intended use of this account.',

    // Hierarchy & Tiers
    'colTier'               => 'Tier',
    'tierMaster'            => 'TEC Master',
    'tierDiocese'           => 'Diocese',
    'tierParish'            => 'Parish',
    'readOnly'              => 'Read-only',
    'canonicalMasterBadge'  => 'Canonical Master',
    'dioceseScopeNotice'    => 'Diocesan accounts expand the canonical TEC master chart of accounts for all parishes within the diocese.',
    'parishScopeNotice'     => 'Parish accounts expand the diocesan and canonical TEC master chart of accounts.',
    'parishAccountCreated'  => 'Parish chart account created successfully.',
    'parishAccountUpdated'  => 'Parish chart account updated successfully.',
    'parishAccountStatusUpdated' => 'Parish chart account status updated successfully.',
    'parishCannotEditDioceseAccount' => 'Diocesan and master accounts are read-only for parishes.',
    'parishAccountNotFound' => 'Chart account not found.',
    'parishAccountCodeExists' => 'Account code already exists in your chart of accounts.',
    'parishAccountCreateFailed' => 'Could not create chart account.',
    'parishAccountUpdateFailed' => 'Could not update chart account.',
    'systemAccountReadOnly' => 'Master Episcopal Church accounts are canonical and read-only.',
];
