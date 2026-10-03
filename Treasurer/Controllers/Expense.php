<?php

namespace Treasurer\Controllers;

/**
 * Singular naming adapter for legacy Expenses controller.
 *
 * Extends ExpenseExports so every `Expense::*` route string resolves the
 * export endpoints as well as the CRUD operations inherited from Expenses.
 */
class Expense extends ExpenseExports {}
