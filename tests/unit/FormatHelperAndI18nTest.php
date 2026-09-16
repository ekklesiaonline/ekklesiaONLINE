<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for L3 (shared format helpers) and L5 (i18n normalization).
 *
 * L3: format_localized_date() and format_currency() exist as shared helpers
 * and are registered in app/Config/Autoload.php.
 * L5: The Parish Finance view no longer has hardcoded English strings;
 * all labels use lang() keys with en/es/fr translations.
 *
 * @internal
 */
final class FormatHelperAndI18nTest extends CIUnitTestCase
{
    /**
     * L3: format_currency() helper exists.
     */
    public function testFormatCurrencyHelperExists(): void
    {
        $this->assertTrue(function_exists('format_currency'));
    }

    /**
     * L3: format_currency() formats correctly.
     */
    public function testFormatCurrencyFormatsCorrectly(): void
    {
        $this->assertSame('1,234.56', format_currency(1234.56));
        $this->assertSame('0.00', format_currency(null));
        $this->assertSame('0.00', format_currency(0));
        $this->assertSame('100.00', format_currency('100'));
    }

    /**
     * L3: format_localized_date() helper exists.
     */
    public function testFormatLocalizedDateHelperExists(): void
    {
        $this->assertTrue(function_exists('format_localized_date'));
    }

    /**
     * L3: format_localized_date() handles null/empty/zero dates.
     */
    public function testFormatLocalizedDateHandlesEmptyValues(): void
    {
        $this->assertSame('-', format_localized_date(null));
        $this->assertSame('-', format_localized_date(''));
        $this->assertSame('-', format_localized_date('0000-00-00'));
    }

    /**
     * L3: format_localized_date() returns a non-empty string for valid dates.
     */
    public function testFormatLocalizedDateReturnsStringForValidDate(): void
    {
        $result = format_localized_date('2026-01-15');
        $this->assertNotSame('-', $result);
        $this->assertNotEmpty($result);
    }

    /**
     * L3: format helper is registered in Autoload config.
     */
    public function testFormatHelperRegisteredInAutoload(): void
    {
        $autoload = config('Autoload');

        $this->assertContains('format', $autoload->helpers);
    }

    /**
     * L5: Parish Finance view uses format_currency() instead of inline closure.
     */
    public function testParishFinanceViewUsesSharedHelper(): void
    {
        $source = file_get_contents(ROOTPATH . 'Parish/Views/Finance/index.php');

        $this->assertStringContainsString('format_currency(', $source);
        $this->assertStringNotContainsString(
            '$formatCurrency = static function',
            $source,
            'Parish Finance view should not have inline formatCurrency closure.'
        );
    }

    /**
     * L5: Parish Finance view has no hardcoded English strings for labels.
     */
    public function testParishFinanceViewHasNoHardcodedEnglish(): void
    {
        $source = file_get_contents(ROOTPATH . 'Parish/Views/Finance/index.php');

        // These were the hardcoded strings that should now use lang()
        $this->assertStringNotContainsString('>Net</h6>', $source);
        $this->assertStringNotContainsString('12-Month Trend</h5>', $source);
        $this->assertStringNotContainsString('>Date</th>', $source);
        $this->assertStringNotContainsString('>Amount</th>', $source);
        $this->assertStringNotContainsString('No donations in this period.', $source);
        $this->assertStringNotContainsString('No expenses in this period.', $source);
        $this->assertStringNotContainsString("label: 'Income'", $source);
        $this->assertStringNotContainsString("label: 'Expenses'", $source);
    }

    /**
     * L5: Parish Finance view uses lang() for all labels.
     */
    public function testParishFinanceViewUsesLangKeys(): void
    {
        $source = file_get_contents(ROOTPATH . 'Parish/Views/Finance/index.php');

        $this->assertStringContainsString("lang('Dashboard.netLabel')", $source);
        $this->assertStringContainsString("lang('Dashboard.trend12Months')", $source);
        $this->assertStringContainsString("lang('Dashboard.colDate')", $source);
        $this->assertStringContainsString("lang('Dashboard.colAmount')", $source);
        $this->assertStringContainsString("lang('Dashboard.noDonationsInPeriod')", $source);
        $this->assertStringContainsString("lang('Dashboard.noExpenses')", $source);
        $this->assertStringContainsString("lang('Dashboard.chartLabelIncome')", $source);
        $this->assertStringContainsString("lang('Dashboard.chartLabelExpenses')", $source);
    }

    /**
     * L5: New language keys exist in en, es, and fr.
     */
    public function testNewLanguageKeysExistInAllLocales(): void
    {
        $keys = [
            'noExpenses',
            'noDonationsInPeriod',
            'trend12Months',
            'chartLabelIncome',
            'chartLabelExpenses',
        ];

        foreach (['en', 'es', 'fr'] as $locale) {
            foreach ($keys as $key) {
                $value = lang("Dashboard.{$key}", [], $locale);

                $this->assertNotSame(
                    "Dashboard.{$key}",
                    $value,
                    "Dashboard.{$key} must exist in {$locale} locale."
                );
                $this->assertNotEmpty($value, "Dashboard.{$key} must not be empty in {$locale} locale.");
            }
        }
    }
}
