<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard: the Shield 'hmac' authenticator is disabled.
 *
 * No route in this application uses the 'hmac' or 'chain' filter, and
 * Config\AuthToken::$hmacEncryptionKeys has no provisioned key ('k1' is
 * an empty string). If HMAC were left registered, identity secret keys
 * would be encrypted with an empty key — silently broken/weak.
 *
 * @internal
 */
final class HmacAuthenticatorDisabledTest extends CIUnitTestCase
{
    public function testHmacIsNotRegisteredAsAuthenticator(): void
    {
        $auth = config('Auth');

        $this->assertArrayNotHasKey(
            'hmac',
            $auth->authenticators,
            "The 'hmac' authenticator must not be registered in Config\\Auth::\$authenticators."
        );
    }

    public function testHmacIsNotInAuthenticationChain(): void
    {
        $auth = config('Auth');

        $this->assertNotContains(
            'hmac',
            $auth->authenticationChain,
            "The 'hmac' authenticator must not appear in Config\\Auth::\$authenticationChain."
        );
    }

    public function testSessionAndTokensAuthenticatorsStillRegistered(): void
    {
        $auth = config('Auth');

        $this->assertArrayHasKey('session', $auth->authenticators);
        $this->assertArrayHasKey('tokens', $auth->authenticators);
        $this->assertContains('session', $auth->authenticationChain);
    }
}
