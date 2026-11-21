<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * POST /login echoes the auth Result reason into a flash error. Shield's
 * Session::check() returns 'Auth.badAttempt' when the email is unknown and
 * 'Auth.invalidPassword' when it exists but the password is wrong — the
 * messages must be identical so responses cannot be used to enumerate
 * registered accounts.
 *
 * @internal
 */
final class LoginErrorEnumerationTest extends CIUnitTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): iterable
    {
        return [['en'], ['es'], ['fr']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('localeProvider')]
    public function testBadAttemptAndInvalidPasswordAreIdentical(string $locale): void
    {
        service('request')->setLocale($locale);

        $badAttempt      = lang('Auth.badAttempt');
        $invalidPassword = lang('Auth.invalidPassword');

        $this->assertSame($badAttempt, $invalidPassword);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('localeProvider')]
    public function testNotActivatedIsIdenticalToBadAttempt(string $locale): void
    {
        service('request')->setLocale($locale);

        $this->assertSame(lang('Auth.badAttempt'), lang('Auth.notActivated'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('localeProvider')]
    public function testLoginFailureKeysResolveToRealMessages(string $locale): void
    {
        service('request')->setLocale($locale);

        foreach (['badAttempt', 'invalidPassword', 'notActivated'] as $key) {
            $message = lang('Auth.' . $key);

            $this->assertNotSame('', trim($message));
            // A missing override renders the raw key name back.
            $this->assertStringNotContainsString($key, $message);
        }
    }
}
