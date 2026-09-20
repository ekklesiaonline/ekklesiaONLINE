<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Auth;

final class LoginViewTest extends CIUnitTestCase
{
    public function testAuthConfigUsesApplicationLoginView(): void
    {
        $this->assertSame('\App\Views\auth\login', (new Auth())->views['login']);
    }

    public function testLoginViewPreservesShieldFeaturesAndUsesAuthLayout(): void
    {
        $view = file_get_contents(APPPATH . 'Views/auth/login.php');

        $this->assertIsString($view);
        $this->assertStringContainsString("config('Auth')->views['layout']", $view);
        $this->assertStringContainsString('csrf_field()', $view);
        $this->assertStringContainsString("url_to('login')", $view);
        $this->assertStringContainsString("setting('Auth.sessionConfig')['allowRemembering']", $view);
        $this->assertStringContainsString("setting('Auth.allowMagicLinkLogins')", $view);
        $this->assertStringContainsString("url_to('magic-link')", $view);
        $this->assertStringContainsString("setting('Auth.allowRegistration')", $view);
        $this->assertStringContainsString("url_to('register')", $view);
    }

    public function testLoginViewEscapesMessagesAndIncludesPasswordVisibilityToggle(): void
    {
        $view = file_get_contents(APPPATH . 'Views/auth/login.php');

        $this->assertIsString($view);
        $this->assertStringContainsString("esc((string) session('error'))", $view);
        $this->assertStringContainsString('esc((string) $error)', $view);
        $this->assertStringContainsString("esc((string) session('message'))", $view);
        $this->assertStringContainsString('id="passwordVisibilityToggle"', $view);
        $this->assertStringContainsString("passwordInput.type = showing ? 'password' : 'text'", $view);
        $this->assertStringContainsString("lang('Auth.showPassword')", $view);
        $this->assertStringContainsString("lang('Auth.hidePassword')", $view);
    }

    public function testPasswordVisibilityLabelsExistForSupportedLocales(): void
    {
        foreach (['en', 'es', 'fr'] as $locale) {
            $messages = require APPPATH . 'Language/' . $locale . '/Auth.php';

            $this->assertNotEmpty($messages['showPassword'] ?? null);
            $this->assertNotEmpty($messages['hidePassword'] ?? null);
        }
    }
}
