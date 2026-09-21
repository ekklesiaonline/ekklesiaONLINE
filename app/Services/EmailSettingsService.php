<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Models\SystemSettingModel;
use CodeIgniter\Email\Email;

/**
 * EmailSettingsService
 *
 * Read/save/test scoped email sender configuration for system, organisation,
 * province, diocese and parish tiers. Keeps SMTP passwords encrypted and
 * treats a complete SMTP block as all-or-nothing.
 */
class EmailSettingsService
{
    /** @var list<string> */
    public const EMAIL_KEYS = [
        'email.from_email',
        'email.from_name',
        'email.reply_to',
        'email.smtp_host',
        'email.smtp_port',
        'email.smtp_user',
        'email.smtp_pass',
        'email.smtp_crypto',
    ];

    private ConfigurationService $config;
    private SystemSettingModel $systemModel;

    public function __construct(
        ?ConfigurationService $config = null,
        ?SystemSettingModel $systemModel = null
    ) {
        $this->config      = $config ?? new ConfigurationService();
        $this->systemModel = $systemModel ?? model(SystemSettingModel::class);
    }

    /**
     * Read current settings for a scope.  SMTP password is never returned
     * decrypted; callers get an empty string plus a boolean flag.
     *
     * @param array{type: string, id: int|null} $scope
     */
    public function currentSettings(array $scope): array
    {
        $settings = $this->loadRawSettings($scope);
        $hasPass  = !empty($settings['email.smtp_pass']);

        $values = [];
        foreach (self::EMAIL_KEYS as $key) {
            $short = str_replace('email.', '', $key);
            $values[$short] = $settings[$key] ?? '';
        }
        $values['smtp_pass'] = '';

        return [
            'values'        => $values,
            'has_smtp_pass' => $hasPass,
        ];
    }

    /**
     * Save submitted settings.  Returns a list of field-level errors or an
     * empty array on success.
     *
     * @param array{type: string, id: int|null} $scope
     * @param array<string,string>              $data
     */
    public function save(array $scope, array $data, bool $canEditSmtp): array
    {
        $existing = $this->loadRawSettings($scope);
        $errors   = [];

        $values = [];
        foreach (self::EMAIL_KEYS as $key) {
            $short          = str_replace('email.', '', $key);
            $values[$key]   = isset($data[$short]) ? trim((string) $data[$short]) : '';
        }

        if ($values['email.from_email'] !== '' && !filter_var($values['email.from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['from_email'] = lang('Email.invalidFromEmail');
        }

        if ($values['email.reply_to'] !== '' && !filter_var($values['email.reply_to'], FILTER_VALIDATE_EMAIL)) {
            $errors['reply_to'] = lang('Email.invalidReplyTo');
        }

        if ($values['email.smtp_port'] !== '' && !ctype_digit($values['email.smtp_port'])) {
            $errors['smtp_port'] = lang('Email.smtpPortInteger');
        }

        // SMTP all-or-nothing validation.
        $smtpFields = [
            'email.smtp_host',
            'email.smtp_user',
            'email.smtp_pass',
        ];
        $anySmtp    = false;
        $allSmtp    = true;
        foreach ($smtpFields as $key) {
            if ($values[$key] !== '') {
                $anySmtp = true;
            } else {
                // Missing password is OK if a password already exists.
                if ($key !== 'email.smtp_pass' || empty($existing['email.smtp_pass'])) {
                    $allSmtp = false;
                }
            }
        }

        if ($anySmtp && !$allSmtp) {
            $errors['smtp_host'] = lang('Email.smtpIncompleteBlock');
        }

        if ($errors !== []) {
            return $errors;
        }

        // If the user cannot edit SMTP, drop any SMTP changes.
        if (!$canEditSmtp) {
            foreach ($smtpFields as $key) {
                $values[$key] = '';
            }
        }

        $this->persistSettings($scope, $values, $existing);

        return [];
    }

    /**
     * Send a test email using the resolved scoped configuration.
     *
     * @param array{type: string, id: int|null} $scope
     * @param array<string,string>              $settings
     * @return array{success: bool, message: string, code: string}
     */
    public function sendTest(array $scope, array $settings, string $recipient): array
    {
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => lang('Email.invalidTestRecipient'), 'code' => 'INVALID_RECIPIENT'];
        }

        $mailer = $this->buildTestMailer($scope, $settings);
        if (!$mailer instanceof Email) {
            return ['success' => false, 'message' => lang('Email.noMailer'), 'code' => 'MAILER_CONFIGURATION_FAILED'];
        }

        try {
            $mailer->setTo($recipient);
            $mailer->setSubject(lang('Email.testSubject'));
            $mailer->setMessage('<p>' . esc(lang('Email.testBody')) . '</p>');
            $mailer->setAltMessage(lang('Email.testBody'));

            if (!$mailer->send(false)) {
                $failureCode = $this->classifySendFailure((string) $mailer->printDebugger(['headers']));
                $this->logTestFailure($scope, $failureCode);

                return [
                    'success' => false,
                    'message' => $this->failureMessage($failureCode),
                    'code'    => $failureCode,
                ];
            }

            return ['success' => true, 'message' => lang('Email.testSent'), 'code' => 'EMAIL_TEST_SENT'];
        } catch (\Throwable) {
            $this->logTestFailure($scope, 'SMTP_SEND_FAILED');

            return ['success' => false, 'message' => lang('Email.testSendFailed'), 'code' => 'SMTP_SEND_FAILED'];
        }
    }

    private function classifySendFailure(string $debugger): string
    {
        $normalized = strtolower($debugger);

        if (str_contains($normalized, '535') || str_contains($normalized, 'failed to authenticate') || str_contains($normalized, 'username and password not accepted')) {
            return 'SMTP_AUTHENTICATION_FAILED';
        }

        if (str_contains($normalized, '550 5.7.30') || (str_contains($normalized, 'dkim') && str_contains($normalized, 'did not pass'))) {
            return 'EMAIL_AUTHENTICATION_FAILED';
        }

        if (str_contains($normalized, 'connection refused') || str_contains($normalized, 'unable to connect') || str_contains($normalized, 'timed out')) {
            return 'SMTP_CONNECTION_FAILED';
        }

        if (str_contains($normalized, 'tls') && (str_contains($normalized, 'failed') || str_contains($normalized, 'error'))) {
            return 'SMTP_TLS_FAILED';
        }

        return 'SMTP_SEND_FAILED';
    }

    private function failureMessage(string $code): string
    {
        return match ($code) {
            'SMTP_AUTHENTICATION_FAILED' => lang('Email.smtpAuthenticationFailed'),
            'EMAIL_AUTHENTICATION_FAILED'=> lang('Email.emailAuthenticationFailed'),
            'SMTP_CONNECTION_FAILED'     => lang('Email.smtpConnectionFailed'),
            'SMTP_TLS_FAILED'            => lang('Email.smtpTlsFailed'),
            default                      => lang('Email.testSendFailed'),
        };
    }

    private function logTestFailure(array $scope, string $reason): void
    {
        log_message('warning', '[EmailSettings] Test send failed; scope={scope}; scope_id={scope_id}; reason={reason}', [
            'scope'    => $scope['type'],
            'scope_id' => $scope['id'] ?? 0,
            'reason'   => $reason,
        ]);
    }

    /**
     * Resolve the effective sender config for a scope (for UI preview).
     *
     * @param array{type: string, id: int|null} $scope
     */
    public function effectiveSender(array $scope): array
    {
        $emailScope = $this->emailScopeFor($scope);

        return (new EmailScopeService($this->config))->senderConfig($emailScope);
    }

    /**
     * @param array{type: string, id: int|null} $scope
     * @return array<string,mixed>
     */
    private function loadRawSettings(array $scope): array
    {
        $settings = [];

        switch ($scope['type']) {
            case 'system':
                foreach (self::EMAIL_KEYS as $key) {
                    $settings[$key] = $this->systemModel->getValue($key);
                }
                break;

            case 'organisation':
                foreach (self::EMAIL_KEYS as $key) {
                    $settings[$key] = $this->config->getSettingForScope($key, []);
                }
                break;

            case 'province':
                $all = $this->config->getAllSettingsForScope(['province_id' => (int) $scope['id']]);
                foreach (self::EMAIL_KEYS as $key) {
                    $settings[$key] = $all[$key] ?? null;
                }
                break;

            case 'diocese':
                $all = $this->config->getAllSettingsForScope(['diocese_id' => (int) $scope['id']]);
                foreach (self::EMAIL_KEYS as $key) {
                    $settings[$key] = $all[$key] ?? null;
                }
                break;

            case 'parish':
                $all = $this->config->getAllSettingsForScope(['parish_id' => (int) $scope['id']]);
                foreach (self::EMAIL_KEYS as $key) {
                    $settings[$key] = $all[$key] ?? null;
                }
                break;
        }

        return $settings;
    }

    /**
     * @param array{type: string, id: int|null}    $scope
     * @param array<string,string>                 $values
     * @param array<string,mixed>                  $existing
     */
    private function persistSettings(array $scope, array $values, array $existing): void
    {
        foreach ($values as $key => $value) {
            $short = str_replace('email.', '', $key);

            if ($short === 'smtp_pass') {
                if ($value === '') {
                    // Keep existing password if any.
                    if (!empty($existing[$key])) {
                        continue;
                    }
                    // No existing password and no new value: store empty.
                }
                $this->persistValue($scope, $key, $value !== '' ? DataEncryption::encrypt($value) : '', $value !== '');
                continue;
            }

            $this->persistValue($scope, $key, $value, false);
        }
    }

    /**
     * @param array{type: string, id: int|null} $scope
     */
    private function persistValue(array $scope, string $key, mixed $value, bool $encrypt): void
    {
        switch ($scope['type']) {
            case 'system':
                $this->systemModel->setValue($key, $value, 'string', 'email');
                break;

            case 'organisation':
                $this->config->setOrganisationSetting($key, $value, $encrypt);
                break;

            case 'province':
                $this->config->setProvinceSetting((int) $scope['id'], $key, $value, $encrypt);
                break;

            case 'diocese':
                $this->config->setDioceseSetting((int) $scope['id'], $key, $value, $encrypt);
                break;

            case 'parish':
                $this->config->setParishSetting((int) $scope['id'], $key, $value, $encrypt);
                break;
        }
    }

    /**
     * @param array{type: string, id: int|null} $scope
     * @return array<string,int|null>
     */
    private function emailScopeFor(array $scope): array
    {
        return match ($scope['type']) {
            'parish'  => ['parish_id'   => (int) $scope['id']],
            'diocese' => ['diocese_id'  => (int) $scope['id']],
            'province'=> ['province_id' => (int) $scope['id']],
            default   => [],
        };
    }

    /**
     * Build a test mailer that reflects the submitted values (including a
     * newly-typed password that has not been persisted yet).
     *
     * @param array{type: string, id: int|null} $scope
     * @param array<string,string>              $settings
     */
    private function buildTestMailer(array $scope, array $settings): ?Email
    {
        $emailScopeService = new EmailScopeService($this->config);
        $emailScope        = $this->emailScopeFor($scope);
        $sender            = $emailScopeService->senderConfig($emailScope);

        // If the user has filled in an SMTP block, build a non-shared mailer
        // with those values so the test reflects what will happen after save.
        if (!empty($settings['smtp_host']) && !empty($settings['smtp_user'])) {
            $pass = $settings['smtp_pass'];
            if ($pass === '') {
                $existing = $this->loadRawSettings($scope);
                $pass     = (string) ($existing['email.smtp_pass'] ?? '');
            }

            if ($pass !== '') {
                $config = [
                    'protocol'   => 'smtp',
                    'SMTPHost'   => $settings['smtp_host'],
                    'SMTPUser'   => $settings['smtp_user'],
                    'SMTPPass'   => $pass,
                    'SMTPPort'   => (int) ($settings['smtp_port'] ?: 587),
                    'SMTPCrypto' => $settings['smtp_crypto'] ?? 'tls',
                    'mailType'   => 'html',
                ];

                $mailer = new \CodeIgniter\Email\Email(new \Config\Email());
                $mailer->initialize($config);

                if ($sender['from_email'] !== '') {
                    $mailer->setFrom($sender['from_email'], $sender['from_name']);
                }

                return $mailer;
            }
        }

        // Otherwise test with the currently persisted/resolved configuration.
        try {
            return $emailScopeService->mailer($emailScope, ['overrides' => ['mailType' => 'html']]);
        } catch (\Throwable) {
            return null;
        }
    }
}
