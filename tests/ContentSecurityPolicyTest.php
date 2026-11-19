<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Guards the Content-Security-Policy rollout: the policy must be enabled,
 * emit the hardened directive set on real page responses, and autoNonce
 * must rewrite the {csp-script-nonce}/{csp-style-nonce} placeholders used
 * by inline <script>/<style> tags in views.
 *
 * @internal
 */
final class ContentSecurityPolicyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testPageResponseEmitsContentSecurityPolicyHeader(): void
    {
        $result = $this->get('health');
        $result->assertOK();

        $response = $result->response();

        // FeatureTestTrait returns the response before Response::send();
        // finalize() is the framework hook that emits the header and
        // injects nonces in production.
        $response->getCSP()->finalize($response);

        $this->assertTrue($response->hasHeader('Content-Security-Policy'));

        $header = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $header);
        $this->assertStringContainsString("script-src 'self'", $header);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $header);
        $this->assertStringContainsString('https://code.jquery.com', $header);
        $this->assertStringContainsString("img-src 'self' data:", $header);
        $this->assertStringContainsString("frame-ancestors 'self'", $header);
        $this->assertStringContainsString("base-uri 'self'", $header);
        $this->assertStringContainsString("form-action 'self'", $header);
        $this->assertStringContainsString("object-src 'none'", $header);
    }

    public function testAutoNonceRewritesInlineTagPlaceholders(): void
    {
        $result = $this->get('health');
        $result->assertOK();

        $response = $result->response();
        $response->setContentType('text/html');
        $response->setBody(
            '<html><head><style {csp-style-nonce}>body { color: #000; }</style></head>'
            . '<body><script {csp-script-nonce}>init();</script></body></html>'
        );

        $response->getCSP()->finalize($response);

        $body = (string) $response->getBody();

        $this->assertStringNotContainsString('{csp-script-nonce}', $body);
        $this->assertStringNotContainsString('{csp-style-nonce}', $body);
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+"/', $body);
        $this->assertMatchesRegularExpression('/<style nonce="[^"]+"/', $body);
        $this->assertStringContainsString("'nonce-", $response->getHeaderLine('Content-Security-Policy'));
    }
}
