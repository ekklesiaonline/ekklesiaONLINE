<?php

namespace App\Services;

use Config\Email as EmailConfig;

/**
 * EmailScopeService
 *
 * Resolves the effective From identity and SMTP transport for an outbound
 * email based on the hierarchy tier that owns the send.
 *
 * Resolution order (most specific wins):
 *   parish → diocese → province → organisation → system → Config\Email
 *
 * Security boundary:
 *   - Tenant tiers (parish/diocese/province) may configure From identity and,
 *     optionally, their own SMTP transport for non-credential mail.
 *   - Credential-bearing mail (activation codes, magic links, OTP, device
 *     verification) uses tenant-scoped From identity but is ALWAYS sent
 *     through the System transport.
 */
class EmailScopeService
{
    /**
     * Setting key namespace used for all email-related settings.
     */
    private const KEY_PREFIX = 'email.';

    private const EMAIL_KEYS = [
        'from_email',
        'from_name',
        'reply_to',
        'protocol',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_pass',
        'smtp_crypto',
        'smtp_timeout',
        'smtp_keep_alive',
        'smtp_auth_method',
        'mail_path',
        'charset',
        'mail_type',
        'validate',
        'priority',
    ];

    private ConfigurationService $config;
    private HierarchyService $hierarchy;
    private EmailConfig $emailConfig;

    public function __construct(
        ?ConfigurationService $config = null,
        ?HierarchyService $hierarchy = null,
        ?EmailConfig $emailConfig = null
    ) {
        $this->config      = $config ?? new ConfigurationService();
        $this->hierarchy   = $hierarchy ?? new HierarchyService();
        $this->emailConfig = $emailConfig ?? new EmailConfig();
    }

    /**
     * Resolve a scope array from whatever hint the caller has.
     *
     * Recognised hints:
     *   - parish_id  → looks up diocese_id/province_id
     *   - diocese_id → looks up province_id
     *   - province_id
     *
     * @param array<string,int|null> $hint
     * @return array{parish_id: int|null, diocese_id: int|null, province_id: int|null}
     */
    public function resolveScope(array $hint = []): array
    {
        $parishId  = !empty($hint['parish_id'])  ? (int) $hint['parish_id']  : null;
        $dioceseId = !empty($hint['diocese_id']) ? (int) $hint['diocese_id'] : null;
        $provinceId = !empty($hint['province_id']) ? (int) $hint['province_id'] : null;

        // Parish -> diocese -> province
        if ($parishId !== null && $parishId > 0) {
            $parishRow = $this->hierarchy->parishRow($parishId);
            if ($parishRow) {
                $dioceseId  = !empty($parishRow['diocese_id'])  ? (int) $parishRow['diocese_id']  : $dioceseId;
                $provinceId = !empty($parishRow['province_id']) ? (int) $parishRow['province_id'] : $provinceId;
            }
        }

        // Diocese -> province
        if ($dioceseId !== null && $dioceseId > 0 && $provinceId === null) {
            $provinceId = $this->hierarchy->provinceIdForDiocese($dioceseId);
        }

        return [
            'parish_id'   => $parishId   ?? null,
            'diocese_id'  => $dioceseId  ?? null,
            'province_id' => $provinceId ?? null,
        ];
    }

    /**
     * Resolve scope from an existing user's profile/affiliations.
     *
     * @return array{parish_id: int|null, diocese_id: int|null, province_id: int|null}
     */
    public function resolveScopeForUser(int $userId): array
    {
        $db = db_connect();
        $scope = [];

        try {
            $profile = $db->table('profile')->where('user_id', $userId)->get()->getRowArray();
            if (!empty($profile['parish_id'])) {
                $scope['parish_id'] = (int) $profile['parish_id'];
            }
            if (!empty($profile['diocese_id'])) {
                $scope['diocese_id'] = (int) $profile['diocese_id'];
            }
            if (!empty($profile['province_id'])) {
                $scope['province_id'] = (int) $profile['province_id'];
            }
        } catch (\Throwable $e) {
            log_message('debug', '[EmailScopeService] profile lookup failed for user {userId}: {message}', [
                'userId'  => $userId,
                'message' => $e->getMessage(),
            ]);
        }

        if (empty($scope['parish_id'])) {
            try {
                $columns = array_map('strtolower', $db->getFieldNames('parish_affiliation'));
                if (in_array('linked_user_id', $columns, true) && in_array('parish_id', $columns, true)) {
                    $builder = $db->table('parish_affiliation')
                        ->where('linked_user_id', $userId)
                        ->where('deleted_at IS NULL', null, false);

                    if (in_array('is_operational', $columns, true)) {
                        $builder->orderBy('is_operational', 'DESC');
                    }

                    $affiliation = $builder->orderBy('id', 'ASC')->get()->getRowArray();
                    if (!empty($affiliation['parish_id'])) {
                        $scope['parish_id'] = (int) $affiliation['parish_id'];
                    }
                }
            } catch (\Throwable $e) {
                log_message('debug', '[EmailScopeService] affiliation lookup failed for user {userId}: {message}', [
                    'userId'  => $userId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $this->resolveScope($scope);
    }

    /**
     * Resolve scope for a freshly registered user using the registration
     * request row (which holds the parish they selected) before affiliations
     * are fully established.
     *
     * @return array{parish_id: int|null, diocese_id: int|null, province_id: int|null}
     */
    public function resolveScopeForPendingUser(int $userId): array
    {
        $db = db_connect();

        try {
            $columns = array_map('strtolower', $db->getFieldNames('user_registration_requests'));
            $parishColumn = null;
            foreach (['requested_parish_id', 'parish_id'] as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    $parishColumn = $candidate;
                    break;
                }
            }

            if ($parishColumn !== null) {
                $request = $db->table('user_registration_requests')
                    ->where('user_id', $userId)
                    ->get()
                    ->getRowArray();

                if (!empty($request[$parishColumn])) {
                    return $this->resolveScope(['parish_id' => (int) $request[$parishColumn]]);
                }
            }
        } catch (\Throwable $e) {
            log_message('debug', '[EmailScopeService] registration-request lookup failed for user {userId}: {message}', [
                'userId'  => $userId,
                'message' => $e->getMessage(),
            ]);
        }

        return $this->resolveScopeForUser($userId);
    }

    /**
     * Resolve the From identity and email config keys for a scope.
     *
     * @param array<string,int|null> $scope
     * @return array{from_email: string, from_name: string, reply_to: string}
     */
    public function senderConfig(array $scope = []): array
    {
        return [
            'from_email' => (string) $this->getScopedSetting($scope, 'from_email', $this->emailConfig->fromEmail),
            'from_name'  => (string) $this->getScopedSetting($scope, 'from_name', $this->emailConfig->fromName),
            'reply_to'   => (string) $this->getScopedSetting($scope, 'reply_to', ''),
        ];
    }

    /**
     * Resolve a complete SMTP config block for a scope, if one exists.
     *
     * A tenant SMTP block is only considered complete when host, user and
     * password are all set. Partial blocks are ignored.
     *
     * @param array<string,int|null> $scope
     * @return array<string,mixed>|null
     */
    public function smtpConfig(array $scope = []): ?array
    {
        $keys = [
            'host'     => 'smtp_host',
            'user'     => 'smtp_user',
            'pass'     => 'smtp_pass',
            'port'     => 'smtp_port',
            'crypto'   => 'smtp_crypto',
            'timeout'  => 'smtp_timeout',
            'keep_alive' => 'smtp_keep_alive',
            'auth_method' => 'smtp_auth_method',
        ];

        $smtp = [];
        foreach ($keys as $outKey => $settingKey) {
            $value = $this->getScopedSetting($scope, $settingKey, null);
            if ($value !== null) {
                $smtp[$outKey] = $value;
            }
        }

        if (empty($smtp['host']) || empty($smtp['user']) || empty($smtp['pass'])) {
            return null;
        }

        return $smtp;
    }

    /**
     * Build a CI4 Email instance configured for the scope.
     *
     * Options:
     *   - force_system_transport (bool): ignore tenant SMTP; always use the
     *     System/Config\Email transport. Used for credential-bearing mail.
     *   - overrides (array): additional CI4 Email config keys merged on top.
     *
     * @param array<string,int|null> $scope
     * @param array<string,mixed>    $options
     * @return \CodeIgniter\Email\Email
     */
    public function mailer(array $scope = [], array $options = []): \CodeIgniter\Email\Email
    {
        $forceSystem = (bool) ($options['force_system_transport'] ?? false);
        $overrides   = (array) ($options['overrides'] ?? []);

        $sender = $this->senderConfig($scope);
        // Forced system transport resolves SMTP only from the System tier so that
        // credential-bearing mail never rides a tenant-controlled relay.
        $smtp   = $forceSystem ? $this->smtpConfig([]) : $this->smtpConfig($scope);

        $config = $this->buildEmailConfig($sender, $smtp);
        $config = array_merge($config, $overrides);

        // If a tenant is providing their own SMTP credentials, use a
        // non-shared instance so those credentials cannot leak into the
        // shared service('email') state.
        if ($forceSystem || $smtp === null) {
            $mailer = service('email');
        } else {
            $mailer = new \CodeIgniter\Email\Email(new EmailConfig());
        }
        $mailer->initialize($config);

        if ($sender['from_email'] !== '') {
            $mailer->setFrom($sender['from_email'], $sender['from_name']);
        }

        if ($sender['reply_to'] !== '') {
            $mailer->setReplyTo($sender['reply_to']);
        }

        return $mailer;
    }

    /**
     * System-only mailer. Used for credential-bearing or platform-level mail.
     *
     * @param array<string,mixed> $options
     * @return \CodeIgniter\Email\Email
     */
    public function systemMailer(array $options = []): \CodeIgniter\Email\Email
    {
        return $this->mailer([], array_merge($options, ['force_system_transport' => true]));
    }

    /**
     * Look up one setting across the hierarchy, respecting system-level locks.
     *
     * @param array<string,int|null> $scope
     * @param string                 $key             Without the 'email.' prefix
     * @param mixed                  $default
     * @return mixed
     */
    public function getScopedSetting(array $scope, string $key, mixed $default = null): mixed
    {
        $fullKey = self::KEY_PREFIX . $key;

        // System lock: a locked system_settings key wins over all lower tiers.
        if ($this->config->isSettingLocked($fullKey)) {
            return $this->config->getSettingForScope($fullKey, [], $default);
        }

        return $this->config->getSettingForScope($fullKey, $scope, $default);
    }

    /**
     * Build the CI4 Email configuration array from resolved settings.
     *
     * @param array{from_email: string, from_name: string, reply_to: string} $sender
     * @param array<string,mixed>|null                                       $smtp
     * @return array<string,mixed>
     */
    private function buildEmailConfig(array $sender, ?array $smtp): array
    {
        $config = [
            'userAgent'     => $this->emailConfig->userAgent,
            'protocol'      => $this->stringSetting($smtp['protocol'] ?? null, $this->emailConfig->protocol),
            'mailPath'      => $this->stringSetting($smtp['mail_path'] ?? null, $this->emailConfig->mailPath),
            'SMTPHost'      => $this->stringSetting($smtp['host'] ?? null, $this->emailConfig->SMTPHost),
            'SMTPUser'      => $this->stringSetting($smtp['user'] ?? null, $this->emailConfig->SMTPUser),
            'SMTPPass'      => $this->stringSetting($smtp['pass'] ?? null, $this->emailConfig->SMTPPass),
            'SMTPPort'      => $this->intSetting($smtp['port'] ?? null, $this->emailConfig->SMTPPort),
            'SMTPTimeout'   => $this->intSetting($smtp['timeout'] ?? null, $this->emailConfig->SMTPTimeout),
            'SMTPKeepAlive' => $this->boolSetting($smtp['keep_alive'] ?? null, $this->emailConfig->SMTPKeepAlive),
            'SMTPCrypto'    => $this->stringSetting($smtp['crypto'] ?? null, $this->emailConfig->SMTPCrypto),
            'SMTPAuthMethod'=> $this->stringSetting($smtp['auth_method'] ?? null, $this->emailConfig->SMTPAuthMethod),
            'wordWrap'      => $this->emailConfig->wordWrap,
            'wrapChars'     => $this->emailConfig->wrapChars,
            'mailType'      => $this->stringSetting($smtp['mail_type'] ?? null, $this->emailConfig->mailType),
            'charset'       => $this->stringSetting($smtp['charset'] ?? null, $this->emailConfig->charset),
            'validate'      => $this->boolSetting($smtp['validate'] ?? null, $this->emailConfig->validate),
            'priority'      => $this->intSetting($smtp['priority'] ?? null, $this->emailConfig->priority),
            'CRLF'          => $this->emailConfig->CRLF,
            'newline'       => $this->emailConfig->newline,
            'BCCBatchMode'  => $this->emailConfig->BCCBatchMode,
            'BCCBatchSize'  => $this->emailConfig->BCCBatchSize,
            'DSN'           => $this->emailConfig->DSN,
        ];

        return $config;
    }

    private function stringSetting(mixed $value, string $fallback): string
    {
        return ($value !== null && $value !== '') ? (string) $value : $fallback;
    }

    private function intSetting(mixed $value, int $fallback): int
    {
        return ($value !== null && $value !== '') ? (int) $value : $fallback;
    }

    private function boolSetting(mixed $value, bool $fallback): bool
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }
}
