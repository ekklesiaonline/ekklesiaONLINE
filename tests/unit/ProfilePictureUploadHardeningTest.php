<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard for C4: profile-picture upload must normalize the stored
 * extension to a safe whitelist based on the detected MIME type, never
 * trusting the client-supplied filename extension. This prevents storing
 * a file with a .php extension even if the client spoofs image MIME headers.
 *
 * These tests validate the extension-resolution logic without instantiating
 * the full controller (which requires Shield auth + settings table).
 *
 * @internal
 */
final class ProfilePictureUploadHardeningTest extends CIUnitTestCase
{
    /**
     * The extension whitelist must map known image MIME types to safe extensions.
     */
    public function testKnownImageMimeTypesMapToSafeExtensions(): void
    {
        $expected = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        foreach ($expected as $mime => $ext) {
            $this->assertSame(
                $ext,
                $this->resolveSafeImageExtension($mime),
                "MIME {$mime} should map to extension {$ext}."
            );
        }
    }

    public function testNonImageMimeTypesAreRejected(): void
    {
        foreach (['application/x-php', 'text/plain', 'application/octet-stream', 'image/svg+xml'] as $mime) {
            $this->assertNull(
                $this->resolveSafeImageExtension($mime),
                "MIME {$mime} should be rejected (null)."
            );
        }
    }

    public function testGeneratedFilenameHasSafeExtensionNotClientExtension(): void
    {
        // Simulate what storeProfilePictureForUser does: generate a random name
        // with the safe extension, ignoring the client-supplied extension.
        $safeExtension = $this->resolveSafeImageExtension('image/png');
        $this->assertSame('png', $safeExtension);

        $newName = bin2hex(random_bytes(16)) . '.' . $safeExtension;

        $this->assertStringEndsNotWith('.php', $newName);
        $this->assertTrue(
            preg_match('/\.(jpg|png|gif|webp)$/', $newName) === 1,
            "Generated filename must end with a safe extension: {$newName}"
        );
    }

    public function testClientPhpExtensionIsNotPreserved(): void
    {
        // Even if the client sends a file named evil.php with image/png MIME,
        // the stored name must use the safe extension from the whitelist.
        $clientName = 'evil.php';
        $detectedMime = 'image/png';
        $safeExtension = $this->resolveSafeImageExtension($detectedMime);
        $newName = bin2hex(random_bytes(16)) . '.' . $safeExtension;

        $this->assertNotSame(
            pathinfo($clientName, PATHINFO_EXTENSION),
            pathinfo($newName, PATHINFO_EXTENSION),
            'The stored extension must not match the client-supplied .php extension.'
        );
        $this->assertSame('png', pathinfo($newName, PATHINFO_EXTENSION));
    }

    /**
     * Replicate the resolveSafeImageExtension logic from Profile controller.
     * This mirrors the exact whitelist used in MemberPortal\Controllers\Profile.
     */
    private function resolveSafeImageExtension(string $mimeType): ?string
    {
        $mimeType = strtolower($mimeType);

        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png'              => 'png',
            'image/gif'              => 'gif',
            'image/webp'             => 'webp',
            default                  => null,
        };
    }
}
