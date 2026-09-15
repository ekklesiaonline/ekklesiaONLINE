<?php

use App\Controllers\BaseController;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FinanceAuditParishContextContractTest extends CIUnitTestCase
{
    public function testBaseControllerDefinesAttributionPolicyConstants(): void
    {
        $this->assertSame('operational_default', BaseController::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT);
        $this->assertSame('home_default', BaseController::ATTRIBUTION_POLICY_HOME_DEFAULT);
    }

    public function testBuildParishContextMetaShapeIsStable(): void
    {
        $controller = new class extends BaseController {
            protected function getHomeparishId(): ?int
            {
                return 42;
            }

            protected function getOperationalParishId(): ?int
            {
                return 42;
            }
        };

        $reflection = new ReflectionClass($controller);
        $buildMetaMethod = $reflection->getMethod('buildParishContextMeta');
        $buildMetaMethod->setAccessible(true);
        $meta = $buildMetaMethod->invoke($controller, 99, BaseController::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT);

        $this->assertArrayHasKey('parish_context', $meta);
        $this->assertSame(42, $meta['parish_context']['home_parish_id']);
        $this->assertSame(42, $meta['parish_context']['operational_parish_id']);
        $this->assertSame(99, $meta['parish_context']['attribution_parish_id']);
        $this->assertSame(BaseController::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT, $meta['parish_context']['attribution_policy']);
    }

    /**
     * @dataProvider financeAuditEventContractProvider
     */
    public function testFinanceAuditEventsIncludeParishContextMeta(
        string $filePath,
        string $eventName
    ): void {
        $fullPath = ROOTPATH . $filePath;
        $contents = file_get_contents($fullPath);

        $this->assertNotFalse($contents, 'Failed to read file: ' . $filePath);

        $pattern = '/audit\(\)->log\(\'' . preg_quote($eventName, '/') . '\',[\s\S]*?buildParishContextMeta\([\s\S]*?(ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT|\$attributionPolicy)[\s\S]*?\)/';
        $matched = preg_match($pattern, (string) $contents);

        $this->assertSame(
            1,
            $matched,
            sprintf('Expected %s in %s to include buildParishContextMeta(..., self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT)', $eventName, $filePath)
        );
    }

    public static function financeAuditEventContractProvider(): array
    {
        return [
            ['Treasurer/Controllers/Donations.php', 'donation.create'],
            ['Treasurer/Controllers/Donations.php', 'donation.delete'],
            ['Treasurer/Controllers/Donations.php', 'donation.export_csv'],
            ['Treasurer/Controllers/Donations.php', 'donation.export_pdf'],
            ['Treasurer/Controllers/Donations.php', 'donation.statement_pdf'],
            ['Treasurer/Controllers/Expenses.php', 'expense.create'],
            ['Treasurer/Controllers/Expenses.php', 'expense.update'],
            ['Treasurer/Controllers/Expenses.php', 'expense.delete'],
            ['Treasurer/Controllers/Expenses.php', 'expense.export_csv'],
            ['Treasurer/Controllers/Expenses.php', 'expense.export_pdf'],
            ['MemberPortal/Controllers/AnnualPledges.php', 'annual_pledge.create'],
            ['MemberPortal/Controllers/AnnualPledges.php', 'annual_pledge.update'],
            ['MemberPortal/Controllers/AnnualPledges.php', 'annual_pledge.delete'],
        ];
    }
}
