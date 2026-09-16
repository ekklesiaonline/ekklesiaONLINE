<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for H8 (settings scope) and H9 (cast nullability).
 *
 * H8: ConfigurationService::getAllDioceseSettings() and
 * getAllProvinceSettings() must scope by the provided ID, not return
 * all settings across all dioceses/provinces via findAll().
 *
 * H9: Model casts must use the nullable prefix form (?int, ?string)
 * and never the suffix form (int?, string?). Columns that are NOT NULL
 * in the migration should use the non-nullable form (int, string).
 *
 * @internal
 */
final class SettingsScopeAndCastTest extends CIUnitTestCase
{
    /**
     * H8: getAllDioceseSettings must scope by diocese_id, not return all.
     */
    public function testGetAllDioceseSettingsIsScoped(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\ConfigurationService::class,
            'getAllDioceseSettings'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'getAll',
            $source,
            'getAllDioceseSettings must call dioceseModel->getAll($dioceseId) to scope by diocese.'
        );
        $this->assertStringNotContainsString(
            'findAll',
            $source,
            'getAllDioceseSettings must not call findAll() — that returns all dioceses\' settings.'
        );
    }

    /**
     * H8: getAllProvinceSettings must scope by province_id, not return all.
     */
    public function testGetAllProvinceSettingsIsScoped(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\ConfigurationService::class,
            'getAllProvinceSettings'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'getAll',
            $source,
            'getAllProvinceSettings must call provinceModel->getAll($provinceId) to scope by province.'
        );
        $this->assertStringNotContainsString(
            'findAll',
            $source,
            'getAllProvinceSettings must not call findAll() — that returns all provinces\' settings.'
        );
    }

    /**
     * H8: DioceseSettingModel::getAll must scope by diocese_id.
     */
    public function testDioceseSettingModelGetAllIsScoped(): void
    {
        $reflection = new ReflectionMethod(
            \App\Models\DioceseSettingModel::class,
            'getAll'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('diocese_id', $source);
        $this->assertStringContainsString('findAll', $source);
    }

    /**
     * H8: ProvinceSettingModel::getAll must scope by province_id.
     */
    public function testProvinceSettingModelGetAllIsScoped(): void
    {
        $reflection = new ReflectionMethod(
            \App\Models\ProvinceSettingModel::class,
            'getAll'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('province_id', $source);
        $this->assertStringContainsString('findAll', $source);
    }

    /**
     * H9: No model in the codebase uses the forbidden suffix-form casts
     * (int?, string?, bool?, datetime?).
     */
    public function testNoSuffixFormCastsAnywhere(): void
    {
        $modelDirs = [
            APPPATH . 'Models',
            ROOTPATH . 'Clergy/Models',
            ROOTPATH . 'Diocesan/Models',
            ROOTPATH . 'MemberPortal/Models',
            ROOTPATH . 'Parish/Models',
            ROOTPATH . 'Province/Models',
            ROOTPATH . 'Treasurer/Models',
            ROOTPATH . 'Volunteer/Models',
            ROOTPATH . 'Worship/Models',
        ];

        $suffixPattern = "/=>\s*'(?:int|string|bool|datetime)\?'/";

        foreach ($modelDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*.php');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $this->assertDoesNotMatchRegularExpression(
                    $suffixPattern,
                    $content,
                    "Suffix-form cast found in {$file}. Use prefix form (?int, ?string) instead."
                );
            }
        }
    }

    /**
     * H9: PersonStatusHistoryModel person_id cast is 'int' (NOT NULL column).
     */
    public function testPersonStatusHistoryModelPersonIdCastIsInt(): void
    {
        $model = new \App\Models\PersonStatusHistoryModel();
        $casts = $this->getModelCasts($model);

        $this->assertSame('int', $casts['person_id'] ?? null);
    }

    /**
     * H9: PersonOnboardingModel person_id cast is 'int' (NOT NULL column).
     */
    public function testPersonOnboardingModelPersonIdCastIsInt(): void
    {
        $model = new \App\Models\PersonOnboardingModel();
        $casts = $this->getModelCasts($model);

        $this->assertSame('int', $casts['person_id'] ?? null);
    }

    /**
     * H9: PersonVisitModel person_id cast is 'int' (NOT NULL column).
     */
    public function testPersonVisitModelPersonIdCastIsInt(): void
    {
        $model = new \App\Models\PersonVisitModel();
        $casts = $this->getModelCasts($model);

        $this->assertSame('int', $casts['person_id'] ?? null);
    }

    /**
     * H9: PersonMembershipDetailsModel boolean flags use 'int' (NOT NULL columns).
     */
    public function testPersonMembershipDetailsModelFlagsAreInt(): void
    {
        $model = new \App\Models\PersonMembershipDetailsModel();
        $casts = $this->getModelCasts($model);

        $this->assertSame('int', $casts['is_baptized'] ?? null);
        $this->assertSame('int', $casts['is_confirmed'] ?? null);
        $this->assertSame('int', $casts['is_communicant'] ?? null);
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($file);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }

    /**
     * @return array<string, string>
     */
    private function getModelCasts(object $model): array
    {
        $reflection = new ReflectionClass($model);
        $castsProperty = $reflection->getProperty('casts');
        $castsProperty->setAccessible(true);

        return (array) $castsProperty->getValue($model);
    }
}
