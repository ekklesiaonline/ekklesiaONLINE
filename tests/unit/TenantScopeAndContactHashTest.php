<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for H4 (tenant scope) and H5 (contact hashing).
 *
 * H4: BaseTenantModel::doFind() must apply the parish scope so that internal
 * CI4 model methods that call doFind() directly cannot bypass tenant scoping.
 * ParishScopeValidator must expose hasUnrestrictedAccess() to distinguish
 * "system admin (all parishes)" from "no access".
 *
 * H5: PersonContactService::hash() must use a keyed HMAC (via
 * DataEncryption::blindIndex) instead of plain SHA-256, with a legacy
 * fallback. emailIndex() and phoneIndex() must use correct normalization.
 *
 * @internal
 */
final class TenantScopeAndContactHashTest extends CIUnitTestCase
{
    /**
     * H4: doFind must apply the tenant scope.
     */
    public function testDoFindAppliesTenantScope(): void
    {
        $reflection = new ReflectionMethod(\App\Models\BaseTenantModel::class, 'doFind');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'tenantScoped',
            $source,
            'doFind must check $this->tenantScoped before applying scope.'
        );
        $this->assertStringContainsString(
            'applyScope',
            $source,
            'doFind must call applyScope (or applyScopeToBuilder) when tenantScoped is true.'
        );
    }

    /**
     * H4: applyScopeToBuilder must exist and apply the parish_id filter.
     */
    public function testApplyScopeToBuilderExists(): void
    {
        $this->assertTrue(
            method_exists(\App\Models\BaseTenantModel::class, 'applyScopeToBuilder'),
            'BaseTenantModel must have applyScopeToBuilder() for doFind to use.'
        );

        $reflection = new ReflectionMethod(\App\Models\BaseTenantModel::class, 'applyScopeToBuilder');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('parishId', $source);
        $this->assertStringContainsString('scopeColumn', $source);
        $this->assertStringContainsString('where', $source);
    }

    /**
     * H4: ParishScopeValidator must expose hasUnrestrictedAccess().
     */
    public function testParishScopeValidatorHasUnrestrictedAccessMethod(): void
    {
        $this->assertTrue(
            method_exists(\App\Services\ParishScopeValidator::class, 'hasUnrestrictedAccess'),
            'ParishScopeValidator must expose hasUnrestrictedAccess() to distinguish system admin from no-access.'
        );
    }

    /**
     * H4: getAccessibleParishIds docblock must document the system-admin
     * empty-array semantics.
     */
    public function testGetAccessibleParishIdsDocumentsSystemAdminSemantics(): void
    {
        $reflection = new ReflectionMethod(\App\Services\ParishScopeValidator::class, 'getAccessibleParishIds');
        $docblock = (string) $reflection->getDocComment();

        $this->assertStringContainsString('unrestricted', $docblock);
        $this->assertStringContainsString('hasUnrestrictedAccess', $docblock);
    }

    /**
     * H5: PersonContactService::hash() must use DataEncryption::blindIndex
     * (keyed HMAC), not plain hash('sha256', ...).
     */
    public function testHashUsesKeyedHmac(): void
    {
        $reflection = new ReflectionMethod(\App\Services\PersonContactService::class, 'hash');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'DataEncryption::blindIndex',
            $source,
            'hash() must use DataEncryption::blindIndex (keyed HMAC) for rainbow-table resistance.'
        );
        $this->assertStringContainsString(
            'hash(\'sha256\'',
            $source,
            'hash() must retain a legacy SHA-256 fallback for environments without a configured key.'
        );
    }

    /**
     * H5: emailIndex() must exist and delegate to hash() with email normalization.
     */
    public function testEmailIndexExists(): void
    {
        $this->assertTrue(
            method_exists(\App\Services\PersonContactService::class, 'emailIndex'),
            'PersonContactService must expose emailIndex() for email-specific blind indexing.'
        );
    }

    /**
     * H5: phoneIndex() must exist and strip non-digit characters before hashing.
     */
    public function testPhoneIndexExistsAndStripsNonDigits(): void
    {
        $reflection = new ReflectionMethod(\App\Services\PersonContactService::class, 'phoneIndex');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'preg_replace',
            $source,
            'phoneIndex() must strip non-digit characters before hashing.'
        );
    }

    /**
     * H5: GuestIntakeService must use emailIndex() and phoneIndex() for
     * correct normalization, not the generic hash().
     */
    public function testGuestIntakeServiceUsesTypedIndexMethods(): void
    {
        $reflection = new ReflectionMethod(\App\Services\GuestIntakeService::class, 'record');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('emailIndex', $source);
        $this->assertStringContainsString('phoneIndex', $source);
    }

    /**
     * H5: PersonModel must use emailIndex() and phoneIndex() in its
     * insert/update trigger for correct normalization.
     */
    public function testPersonModelUsesTypedIndexMethods(): void
    {
        $source = file_get_contents(APPPATH . 'Models/PersonModel.php');

        $this->assertStringContainsString('emailIndex', $source, 'PersonModel must use emailIndex() for email hashing.');
        $this->assertStringContainsString('phoneIndex', $source, 'PersonModel must use phoneIndex() for phone hashing.');
    }

    /**
     * H5: PeopleDirectoryService must use emailIndex() and phoneIndex()
     * for search lookups.
     */
    public function testPeopleDirectoryServiceUsesTypedIndexMethods(): void
    {
        $source = file_get_contents(APPPATH . 'Services/PeopleDirectoryService.php');

        $this->assertStringContainsString('emailIndex', $source);
        $this->assertStringContainsString('phoneIndex', $source);
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($file);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
