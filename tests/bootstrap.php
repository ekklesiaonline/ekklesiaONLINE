<?php

declare(strict_types=1);

/*
 * ekklesiaONLINE PHPUnit bootstrap.
 *
 * Loads the standard CodeIgniter test bootstrap, then builds the canonical
 * shared-table schema on the shared SQLite :memory: tests connection so that
 * every test class sees the full union column set regardless of execution
 * order. See Tests\Support\Database\CanonicalTestSchema.
 */

require_once __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
require_once __DIR__ . '/../Finance/Config/Aliases.php';

$db = db_connect('tests');

if ($db->DBDriver === 'SQLite3') {
    Tests\Support\Database\CanonicalTestSchema::ensure($db);
}
