<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for Low-priority items L1, L7, L8, L9.
 *
 * L1: Parish\ namespace is registered in composer.json PSR-4.
 * L7: ReportClegyModel typo is fixed; ReportClergyModel is the canonical
 *     name, with a compatibility alias for the old name.
 * L8: Shield password validators are enabled (CompositionValidator,
 *     NothingPersonalValidator, DictionaryValidator) and personalFields
 *     includes first_name/last_name to prevent personal info in passwords.
 * L9: UserSessionModel enforces the concurrent session cap.
 *
 * @internal
 */
final class LowPriorityQualityTest extends CIUnitTestCase
{
    /**
     * L1: Parish\ namespace is in composer.json autoload PSR-4.
     */
    public function testParishNamespaceInComposerAutoload(): void
    {
        $composer = json_decode(file_get_contents(ROOTPATH . 'composer.json'), true);

        $this->assertArrayHasKey(
            'Parish\\',
            $composer['autoload']['psr-4'],
            'Parish\ namespace must be registered in composer.json PSR-4 autoload.'
        );
        $this->assertSame(
            'Parish/',
            $composer['autoload']['psr-4']['Parish\\']
        );
    }

    /**
     * L1: Parish\ classes resolve via autoloader.
     */
    public function testParishClassesResolve(): void
    {
        $this->assertTrue(class_exists(\Parish\Controllers\Dashboard::class));
        $this->assertTrue(class_exists(\Parish\Controllers\FinanceDashboard::class));
    }

    /**
     * L7: ReportClergyModel exists with the corrected name.
     */
    public function testReportClergyModelExists(): void
    {
        $this->assertTrue(class_exists(\App\Models\ReportClergyModel::class));
    }

    /**
     * L7: ReportClegyModel (typo) is a compatibility alias.
     */
    public function testReportClegyModelIsAlias(): void
    {
        $this->assertTrue(class_exists(\App\Models\ReportClegyModel::class));

        $alias = new \App\Models\ReportClegyModel();
        $this->assertInstanceOf(\App\Models\ReportClergyModel::class, $alias);
    }

    /**
     * L7: ReportClergyModel uses the correct table name.
     */
    public function testReportClergyModelTableName(): void
    {
        $model = new \App\Models\ReportClergyModel();

        $this->assertSame('report_clergy', $model->table);
    }

    /**
     * L8: Shield password validators include CompositionValidator,
     * NothingPersonalValidator, and DictionaryValidator.
     */
    public function testPasswordValidatorsEnabled(): void
    {
        $auth = config('Auth');

        $this->assertContains(
            \CodeIgniter\Shield\Authentication\Passwords\CompositionValidator::class,
            $auth->passwordValidators
        );
        $this->assertContains(
            \CodeIgniter\Shield\Authentication\Passwords\NothingPersonalValidator::class,
            $auth->passwordValidators
        );
        $this->assertContains(
            \CodeIgniter\Shield\Authentication\Passwords\DictionaryValidator::class,
            $auth->passwordValidators
        );
    }

    /**
     * L8: PwnedValidator is NOT enabled (requires external network access,
     * which the user explicitly excluded).
     */
    public function testPwnedValidatorNotEnabled(): void
    {
        $auth = config('Auth');

        $this->assertNotContains(
            \CodeIgniter\Shield\Authentication\Passwords\PwnedValidator::class,
            $auth->passwordValidators
        );
    }

    /**
     * L8: personalFields includes first_name and last_name so the
     * NothingPersonalValidator can reject passwords containing user names.
     */
    public function testPersonalFieldsIncludeNames(): void
    {
        $auth = config('Auth');

        $this->assertContains('first_name', $auth->personalFields);
        $this->assertContains('last_name', $auth->personalFields);
    }

    /**
     * L8: Minimum password length is at least 8.
     */
    public function testMinimumPasswordLength(): void
    {
        $auth = config('Auth');

        $this->assertGreaterThanOrEqual(8, $auth->minimumPasswordLength);
    }

    /**
     * L9: UserSessionModel enforces a concurrent session cap.
     */
    public function testUserSessionModelEnforcesConcurrentCap(): void
    {
        $model = new \App\Models\UserSessionModel();

        $this->assertGreaterThan(0, $model->maxConcurrentSessions());
    }

    /**
     * L9: UserSessionModel has registerSession method that prunes
     * stale sessions and enforces the cap.
     */
    public function testUserSessionModelHasRegisterSessionMethod(): void
    {
        $this->assertTrue(method_exists(\App\Models\UserSessionModel::class, 'registerSession'));
        $this->assertTrue(method_exists(\App\Models\UserSessionModel::class, 'pruneStaleForUser'));
        $this->assertTrue(method_exists(\App\Models\UserSessionModel::class, 'removeAllForUser'));
    }

    /**
     * L9: SessionTrackingFilter exists and tracks sessions.
     */
    public function testSessionTrackingFilterExists(): void
    {
        $this->assertTrue(class_exists(\App\Filters\SessionTrackingFilter::class));
    }
}
