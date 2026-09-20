<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\Auth\RegisterController;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class RegistrationEmailEnumerationTest extends CIUnitTestCase
{
    public function testEveryEmailValidationFailureUsesTheSameGenericMessage(): void
    {
        $controller = new class extends RegisterController {
            public function registrationRules(): array
            {
                return $this->getValidationRules();
            }
        };

        $emailErrors = $controller->registrationRules()['email']['errors'];
        $expected = 'Auth.registrationEmailUnavailable';

        $this->assertSame($expected, $emailErrors['required']);
        $this->assertSame($expected, $emailErrors['max_length']);
        $this->assertSame($expected, $emailErrors['valid_email']);
        $this->assertSame($expected, $emailErrors['is_unique']);
        $this->assertCount(1, array_unique($emailErrors));
    }

    public function testGenericRegistrationMessageIsAvailableInEverySupportedLocale(): void
    {
        foreach (['en', 'es', 'fr'] as $locale) {
            service('request')->setLocale($locale);
            $message = lang('Auth.registrationEmailUnavailable');

            $this->assertNotSame('Auth.registrationEmailUnavailable', $message);
            $this->assertNotSame('', trim($message));
        }
    }
}
