<?php

use App\Controllers\BaseController;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

class ValidDateParamOrTestController extends BaseController
{
    public function validDateParamOrForTest(mixed $value, string $fallback): string
    {
        return $this->validDateParamOr($value, $fallback);
    }
}

/**
 * @internal
 */
final class ValidDateParamOrTest extends CIUnitTestCase
{
    private const FALLBACK = '1999-12-31';

    private ValidDateParamOrTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
        $this->controller = new ValidDateParamOrTestController();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testAcceptsStrictCalendarDate(): void
    {
        $this->assertSame('2026-02-28', $this->controller->validDateParamOrForTest('2026-02-28', self::FALLBACK));
        $this->assertSame('2028-02-29', $this->controller->validDateParamOrForTest(' 2028-02-29 ', self::FALLBACK));
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testInvalidInputReturnsFallback(mixed $value): void
    {
        $this->assertSame(self::FALLBACK, $this->controller->validDateParamOrForTest($value, self::FALLBACK));
    }

    public static function invalidDateProvider(): array
    {
        return [
            'null'                 => [null],
            'empty'                => [''],
            'array'                => [['2026-01-05']],
            'garbage'              => ['abc'],
            'unpadded'             => ['2026-1-5'],
            'impossible day'       => ['2026-02-31'],
            'non-leap feb 29'      => ['2026-02-29'],
            'month 13'             => ['2026-13-01'],
            'trailing time'        => ['2026-01-05 10:00'],
            'embedded null byte'   => ["2026-01\0-01"],
        ];
    }
}
