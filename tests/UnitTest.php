<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class UnitTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testFrameworkLoads(): void
    {
        $this->assertTrue(true);
    }
}
