<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Libraries\EmailFailureClassifier;
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
        'email.enabled',
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
        $storedPass = $this->tierSetting($scope, 'email.smtp_pass');
        $hasPass  = $storedPass !== null && $storedPass !== '';

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
        $errors = [];

        $values = [];
        foreach (self::EMAIL_KEYS as $key) {
            $short = str_replace('email.', '', $key);
            if (array_key_exists($short, $data)) {
                $values[$key] = trim((string) $data[$short]);
            }
        }

        // The enabled flag only persists when the form submits it (hidden
        // '0' + checkbox '1' pair); otherwise the stored value is kept.
        if (array_key_exists('enabled', $data)) {
            $values['email.enabled'] = ((string) $data['enabled'] === '1') ? '1' : '0';
        }

        // SMTP fields the user may not edit are dropped before validation
        // and persistence, so stale or partial submissions can neither
        // error nor clobber the stored block.
        if (!$canEditSmtp) {
            unset(
                $values['email.smtp_host'],
                $values['email.smtp_user'],
                $values['email.smtp_pass'],
                $values['email.smtp_port'],
                $values['email.smtp_crypto']
            );
        }

        if (($values['email.from_email'] ?? '') !== '' && !filter_var($values['email.from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['from_email'] = lang('Email.invalidFromEmail');
        }

        if (($values['email.reply_to'] ?? '') !== '' && !filter_var($values['email.reply_to'], FILTER_VALIDATE_EMAIL)) {
            $errors['reply_to'] = lang('Email.invalidReplyTo');
        }

        if (($values['email.smtp_port'] ?? '') !== '' && !ctype_digit($values['email.smtp_port'])) {
            $errors['smtp_port'] = lang('Email.smtpPortInteger');
        }

        // SMTP all-or-nothing validation against this tier's stored block.
        $storedPass = $this->tierSetting($scope, 'email.smtp_pass');
        $hasStoredPass = $storedPass !== null && $storedPass !== '';

        if ($canEditSmtp) {
            $anySmtp = false;
            $allSmtp = true;
            foreach (['email.smtp_host', 'email.smtp_user', 'email.smtp_pass'] as $key) {
                if (($values[$key] ?? '') !== '') {
                    $anySmtp = true;
                } elseif ($key !== 'email.smtp_pass' || !$hasStoredPass) {
                    $allSmtp = false;
                }
            }

            if ($anySmtp && !$allSmtp) {
                $errors['smtp_host'] = lang('Email.smtpIncompleteBlock');
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->persistSettings($scope, $values, $hasStoredPass);

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
            $reference = strtoupper(bin2hex(random_bytes(4)));
            $sentAt = gmdate('Y-m-d H:i:s') . ' UTC';
            $body = lang('Email.testBody', [$reference, $sentAt]);

            $mailer->setTo($recipient);
            $mailer->setSubject(lang('Email.testSubject') . ' [' . $reference . ']');
            $mailer->setMessage('<p>' . esc($body) . '</p>');
            $mailer->setAltMessage($body);

            if (!$mailer->send(false)) {
                $failureCode = $this->classifySendFailure((string) $mailer->printDebugger(['headers']));
                $this->logTestFailure($scope, $failureCode);

                return [
                    'success' => false,
                    'message' => $this->failureMessage($failureCode),
                    'code'    => $failureCode,
                ];
            }

            return ['success' => true, 'message' => lang('Email.testSent', [$reference]), 'code' => 'EMAIL_TEST_SENT'];
        } catch (\Throwable) {
            $this->logTestFailure($scope, 'SMTP_SEND_FAILED');

            return ['success' => false, 'message' => lang('Email.testSendFailed'), 'code' => 'SMTP_SEND_FAILED'];
        }
    }

    private function classifySendFailure(string $debugger): string
    {
        return EmailFailureClassifier::classify($debugger);
    }

    private function failureMessage(string $code): string
    {
        return match ($code) {
            'SMTP_AUTHENTICATION_FAILED' => lang('Email.smtpAuthenticationFailed'),
            'EMAIL_AUTHENTICATION_FAILED'=> lang('Email.emailAuthenticationFailed'),
            'RECIPIENT_POLICY_REJECTED'  => lang('Email.recipientPolicyRejected'),
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
     * Read a single key from exactly this scope's tier (no inheritance).
     * Returns the decrypted value for encrypted rows.
     *
     * @param array{type: string, id: int|null} $scope
     */
    private function tierSetting(array $scope, string $key): mixed
    {
        return $this->config->getSettingForTier(
            (string) $scope['type'],
            isset($scope['id']) ? (int) $scope['id'] : null,
            $key
        );
    }

    /**
     * @param array{type: string, id: int|null} $scope
     * @return array<string,mixed>
     */
    private function loadRawSettings(array $scope): array
    {
        $raw = [];

        switch ($scope['type']) {
            case 'system':
                foreach (self::EMAIL_KEYS as $key) {
                    $raw[$key] = $this->systemModel->getValue($key);
                }
                break;

            case 'organisation':
                foreach (self::EMAIL_KEYS as $key) {
                    $raw[$key] = $this->config->getSettingForScope($key, []);
                }
                break;

            case 'province':
                $raw = $this->config->getAllSettingsForScope(['province_id' => (int) $scope['id']]);
                break;

            case 'diocese':
                $raw = $this->config->getAllSettingsForScope(['diocese_id' => (int) $scope['id']]);
                break;

            case 'parish':
                $raw = $this->config->getAllSettingsForScope(['parish_id' => (int) $scope['id']]);
                break;
        }

        $settings = [];
        foreach (self::EMAIL_KEYS as $key) {
            $value = $raw[$key] ?? null;
            // SystemSettingModel::getAll() returns metadata envelopes; unwrap.
            if (is_array($value) && array_key_exists('value', $value)) {
                $value = $value['value'];
            }
            $settings[$key] = $value;
        }

        return $settings;
    }

    /**
     * @param array{type: string, id: int|null}    $scope
     * @param array<string,string>                 $values
     */
    private function persistSettings(array $scope, array $values, bool $hasStoredPass): void
    {
        foreach ($values as $key => $value) {
            $short = str_replace('email.', '', $key);

            if ($short === 'smtp_pass') {
                if ($value === '') {
                    // Keep existing password if any.
                    if ($hasStoredPass) {
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
                $pass = (string) ($this->tierSetting($scope, 'email.smtp_pass') ?? '');
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
