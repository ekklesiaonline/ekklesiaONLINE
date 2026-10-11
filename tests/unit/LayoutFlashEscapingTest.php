<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard: flash messages rendered in module layouts must be
 * HTML-escaped so that user-influenced text stored in session flash
 * (success/error) cannot inject markup or script.
 *
 * @internal
 */
final class LayoutFlashEscapingTest extends CIUnitTestCase
{
    /**
     * Every module layout that renders flash messages must escape them.
     */
    public static function layoutProvider(): array
    {
        $root = ROOTPATH;

        return [
            'app main'      => [$root . 'app/Views/layouts/main.php'],
            'clergy main'   => [$root . 'Clergy/Views/layouts/main.php'],
            'diocesan main' => [$root . 'Diocese/Views/layouts/main.php'],
            'province main' => [$root . 'Province/Views/layouts/main.php'],
            'finance main'  => [$root . 'Finance/Views/layouts/main.php'],
            'volunteer main'=> [$root . 'Volunteer/Views/layouts/main.php'],
            'worship main'  => [$root . 'Worship/Views/layouts/main.php'],
        ];
    }

    /**
     * No layout may output session('success') or session('error') raw.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('layoutProvider')]
    public function testLayoutDoesNotRenderRawFlashMessages(string $layoutPath): void
    {
        $this->assertFileExists($layoutPath);
        $content = file_get_contents($layoutPath);
        $this->assertNotFalse($content, "Failed to read layout: {$layoutPath}");

        // Raw, unescaped output of flash success/error.
        $this->assertStringNotContainsString(
            "<?= session('success') ?>",
            $content,
            "Layout {$layoutPath} renders session('success') without escaping."
        );
        $this->assertStringNotContainsString(
            "<?= session('error') ?>",
            $content,
            "Layout {$layoutPath} renders session('error') without escaping."
        );

        // Confirm the escaped form is present (proves the fix was applied, not removed).
        $this->assertStringContainsString(
            "esc(session('success'))",
            $content,
            "Layout {$layoutPath} is missing escaped session('success')."
        );
        $this->assertStringContainsString(
            "esc(session('error'))",
            $content,
            "Layout {$layoutPath} is missing escaped session('error')."
        );
    }

}
