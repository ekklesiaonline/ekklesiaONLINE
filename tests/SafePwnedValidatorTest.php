<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\Passwords\SafePwnedValidator;
use CodeIgniter\Shield\Authentication\Passwords\PwnedValidator;
use CodeIgniter\Shield\Authentication\Passwords\ValidatorInterface;
use CodeIgniter\Shield\Config\Auth as AuthConfig;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Auth;

final class SafePwnedValidatorTest extends CIUnitTestCase
{
    public function testAuthConfigUsesSafePwnedValidator(): void
    {
        $validators = (new Auth())->passwordValidators;

        $this->assertContains(SafePwnedValidator::class, $validators);
        $this->assertNotContains(PwnedValidator::class, $validators);
    }

    public function testReturnsPassingResultWhenApiThrows(): void
    {
        $inner = new class implements ValidatorInterface {
            public function check(string $password, ?User $user = null): Result
            {
                throw new \RuntimeException('DNS failure');
            }

            public function error(): ?string
            {
                return null;
            }

            public function suggestion(): ?string
            {
                return null;
            }
        };

        $validator = new SafePwnedValidator(new AuthConfig(), $inner);
        $result    = $validator->check('Password123!');

        $this->assertTrue($result->isOK());
        $this->assertNull($validator->error());
    }

    public function testPropagatesBreachedPasswordFailure(): void
    {
        $inner = new class implements ValidatorInterface {
            public function check(string $password, ?User $user = null): Result
            {
                return new Result([
                    'success'   => false,
                    'reason'    => 'Password found in a data breach.',
                    'extraInfo' => 'Choose a different password.',
                ]);
            }

            public function error(): ?string
            {
                return null;
            }

            public function suggestion(): ?string
            {
                return null;
            }
        };

        $validator = new SafePwnedValidator(new AuthConfig(), $inner);
        $result    = $validator->check('Password123!');

        $this->assertFalse($result->isOK());
        $this->assertSame('Password found in a data breach.', $validator->error());
        $this->assertSame('Choose a different password.', $validator->suggestion());
    }

    public function testPwnedLanguageKeysExistInAllLocales(): void
    {
        foreach (['en', 'es', 'fr'] as $locale) {
            $this->assertNotSame(
                'Auth.errorPasswordPwned',
                lang('Auth.errorPasswordPwned', ['x', 1, 'a database'], $locale),
                "Missing errorPasswordPwned for {$locale}"
            );
            $this->assertNotSame(
                'Auth.suggestPasswordPwned',
                lang('Auth.suggestPasswordPwned', ['x'], $locale),
                "Missing suggestPasswordPwned for {$locale}"
            );
        }
    }
}
