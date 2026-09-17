<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * CanonicalSchemaExtension
 *
 * Repairs the shared SQLite :memory: test schema before every test method.
 * Several test setUp() routines drop and recreate shared tables with a
 * narrower column set than the application expects; because the connection
 * is shared across the whole PHPUnit process, that narrow shape would
 * otherwise leak into later test classes.
 *
 * The Prepared event fires after each test's setUp() and before the test
 * body, so this repairs any damage a setUp() caused before it can affect
 * the test itself or anything that runs later.
 */
final class CanonicalSchemaExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class implements PreparedSubscriber {
            public function notify(Prepared $event): void
            {
                $db = db_connect('tests');

                if ($db->DBDriver === 'SQLite3') {
                    CanonicalTestSchema::ensure($db);
                }
            }
        });
    }
}
