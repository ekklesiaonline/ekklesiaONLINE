<?php

namespace App\Services;

use App\Libraries\EmailFailureClassifier;
use App\Libraries\SchemaResolver;
use App\Services\EmailScopeService;
use CodeIgniter\Database\BaseConnection;

class MemberAccessService
{
    private BaseConnection $db;
    private NonPortalMemberProvisioningService $nonPortalProvisioning;
    private SchemaResolver $schemaResolver;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->nonPortalProvisioning = new NonPortalMemberProvisioningService($this->db);
        $this->schemaResolver = new SchemaResolver($this->db);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createNonPortalUser(array $payload, int $parishId): array
    {
        return $this->nonPortalProvisioning->create($payload, $parishId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function enablePortalAccess(int $userId, array $payload, int $parishId): array
    {
        $familyId = (int) ($payload['family_id'] ?? 0);
        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        $user = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        if ($user === null) {
            return $this->result(false, 404, 'USER_NOT_FOUND', 'User not found.');
        }

        $affiliationColumns = array_map('strtolower', $this->getColumnsForTable('parish_affiliation'));
        $userColumn = in_array('linked_user_id', $affiliationColumns, true) ? 'linked_user_id' : 'user_id';
        $parishColumn = in_array('parish_id', $affiliationColumns, true) ? 'parish_id' : 'parish_id';

        $activeAffiliationBuilder = $this->db->table('parish_affiliation')
            ->where($userColumn, $userId)
            ->where($parishColumn, $parishId);
        if (in_array('is_member', $affiliationColumns, true)) {
            $activeAffiliationBuilder->where('is_member', 1);
        } elseif (in_array('active', $affiliationColumns, true)) {
            $activeAffiliationBuilder->where('active', 1);
        }

        $activeAffiliation = $activeAffiliationBuilder->get()->getRowArray();
        if ($activeAffiliation === null) {
            return $this->result(false, 403, 'OUT_OF_SCOPE', 'User has no active affiliation in this parish.', [
                [
                    'field' => 'userId',
                    'code' => 'OUT_OF_SCOPE',
                    'message' => 'User does not have an active affiliation in this parish.',
                ],
            ]);
        }

        if ($familyId > 0) {
            $family = $this->db->table('family')->where('id', $familyId)->get()->getRowArray();
            if ($family === null) {
                return $this->result(false, 404, 'FAMILY_NOT_FOUND', 'Family not found.');
            }

            if ((int) ($family['parish_id'] ?? 0) !== $parishId) {
                return $this->result(false, 403, 'OUT_OF_SCOPE', 'The requested family is out of scope.');
            }

            $relation = $this->db->table('family_member')
                ->where('family_id', $familyId)
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();
            if ($relation === null) {
                return $this->result(false, 403, 'OUT_OF_SCOPE', 'User is not linked to the selected family in scope.', [
                    [
                        'field' => 'userId',
                        'code' => 'OUT_OF_SCOPE',
                        'message' => 'User is not linked to this family.',
                    ],
                ]);
            }
        }

        $existingIdentity = $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        if ($existingIdentity !== null) {
            $existingRequiresReset = (int) ($existingIdentity['force_reset'] ?? 0) === 1;

            if ($existingRequiresReset) {
                $now = date('Y-m-d H:i:s');
                $targetEmail = $email;

                $existingEmail = strtolower(trim((string) ($existingIdentity['secret'] ?? '')));
                if ($targetEmail === '') {
                    $targetEmail = $existingEmail;
                }

                if ($targetEmail !== '' && $targetEmail !== $existingEmail) {
                    $emailIdentity = $this->db->table('auth_identities')
                        ->where('type', 'email_password')
                        ->where('secret', $targetEmail)
                        ->get()
                        ->getRowArray();
                    if ($emailIdentity !== null && (int) ($emailIdentity['user_id'] ?? 0) !== $userId) {
                        return $this->result(false, 409, 'EMAIL_ALREADY_IN_USE', 'Email is already in use.', [
                            [
                                'field' => 'email',
                                'code' => 'ALREADY_EXISTS',
                                'message' => 'Email is already used by another account.',
                            ],
                        ]);
                    }

                    $this->db->table('auth_identities')
                        ->where('id', (int) $existingIdentity['id'])
                        ->update([
                            'secret' => $targetEmail,
                            'updated_at' => $now,
                        ]);
                }

                $profileTable = $this->resolveExistingProfileTable();
                $profileRow = $profileTable !== null
                    ? $this->db->table($profileTable)->select('*')->where('user_id', $userId)->get()->getRowArray()
                    : null;
                if (is_array($profileRow)) {
                    $profileUpdate = [
                        'updated_at' => $now,
                    ];
                    if (array_key_exists('portal_access', $profileRow)) {
                        $profileUpdate['portal_access'] = 'invited';
                    }
                    if (array_key_exists('portal_enabled_at', $profileRow)) {
                        $profileUpdate['portal_enabled_at'] = null;
                    }

                    $this->db->table($profileTable)
                        ->where('user_id', $userId)
                        ->update($profileUpdate);
                }

                if ($targetEmail !== '') {
                    $this->sendPortalInviteEmail($targetEmail, $parishId);
                }

                return $this->result(true, 200, 'PORTAL_INVITE_RESENT', 'Portal invite email re-sent. Access activates after password setup.', [], [
                    'user_id' => $userId,
                    'family_id' => $familyId,
                    'parish_id' => $parishId,
                    'portal_access' => 'invited',
                    'portal_enabled_at' => null,
                    'group' => 'member',
                    'identity_type' => 'email_password',
                    'requires_password_reset' => true,
                ]);
            }

            return $this->result(false, 409, 'PORTAL_ACCESS_ALREADY_ENABLED', 'Portal access is already enabled for this user.', [
                [
                    'field' => 'userId',
                    'code' => 'ALREADY_EXISTS',
                    'message' => 'An email_password identity already exists.',
                ],
            ]);
        }

        $emailIdentity = $this->db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->get()
            ->getRowArray();
        if ($emailIdentity !== null) {
            return $this->result(false, 409, 'EMAIL_ALREADY_IN_USE', 'Email is already in use.', [
                [
                    'field' => 'email',
                    'code' => 'ALREADY_EXISTS',
                    'message' => 'Email is already used by another account.',
                ],
            ]);
        }

        $now = date('Y-m-d H:i:s');
        try {
            $temporaryPassword = bin2hex(random_bytes(16)) . 'Aa1!';
        } catch (\Throwable $e) {
            return $this->result(false, 500, 'RANDOM_GENERATION_FAILED', 'Failed to generate temporary credentials.', [
                [
                    'field' => 'system',
                    'code' => 'RANDOM_GENERATION_FAILED',
                    'message' => 'Temporary credential generation failed.',
                ],
            ]);
        }
        $hashedPassword = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $this->db->transBegin();

        try {
            $this->db->table('auth_identities')->insert([
                'user_id' => $userId,
                'type' => 'email_password',
                'secret' => $email,
                'secret2' => $hashedPassword,
                'expires' => null,
                'extra' => null,
                'force_reset' => 1,
                'last_used_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $existingGroup = $this->db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', 'member')
                ->get()
                ->getRowArray();
            if ($existingGroup === null) {
                $this->db->table('auth_groups_users')->insert([
                    'user_id' => $userId,
                    'group' => 'member',
                    'created_at' => $now,
                ]);
            }

            $profileTable = $this->resolveExistingProfileTable();
            $profileRow = $profileTable !== null
                ? $this->db->table($profileTable)->select('*')->where('user_id', $userId)->get()->getRowArray()
                : null;
            $profileUpdate = [
                'updated_at' => $now,
            ];
            if (is_array($profileRow) && array_key_exists('portal_access', $profileRow)) {
                // Keep non-portal style UI state until first successful login.
                $profileUpdate['portal_access'] = 'invited';
            }
            if (is_array($profileRow) && array_key_exists('portal_enabled_at', $profileRow)) {
                $profileUpdate['portal_enabled_at'] = null;
            }

            if ($profileTable !== null) {
                $this->db->table($profileTable)
                    ->where('user_id', $userId)
                    ->update($profileUpdate);
            }

            $this->sendPortalInviteEmail($email, $parishId);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaction status is false after portal enable flow.');
            }

            $this->db->transCommit();

            return $this->result(true, 200, 'PORTAL_ACCESS_ENABLED', 'Portal invite email sent successfully. Access activates after password setup.', [], [
                'user_id' => $userId,
                'family_id' => $familyId,
                'parish_id' => $parishId,
                'portal_access' => 'invited',
                'portal_enabled_at' => null,
                'group' => 'member',
                'identity_type' => 'email_password',
                'requires_password_reset' => true,
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            $message = strtolower($e->getMessage());
            if (str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
                return $this->result(false, 409, 'EMAIL_ALREADY_IN_USE', 'Email is already in use.', [
                    [
                        'field' => 'email',
                        'code' => 'ALREADY_EXISTS',
                        'message' => 'Email is already used by another account.',
                    ],
                ]);
            }

            return $this->result(false, 500, 'TRANSACTION_FAILED', 'Failed to enable portal access.', [
                [
                    'field' => 'system',
                    'code' => 'TRANSACTION_FAILED',
                    'message' => 'The enable operation could not be completed.',
                ],
            ]);
        }
    }

    public function activatePortalAccessAfterSuccessfulLogin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $profileTable = $this->resolveExistingProfileTable();
            $profile = $profileTable !== null
                ? $this->db->table($profileTable)
                ->select('*')
                ->where('user_id', $userId)
                ->get()
                ->getRowArray()
                : null;

            if (!is_array($profile) || !array_key_exists('portal_access', $profile)) {
                return false;
            }

            $portalAccess = strtolower(trim((string) ($profile['portal_access'] ?? 'none')));
            if (!in_array($portalAccess, ['none', 'invited', 'enabled'], true)) {
                $portalAccess = 'none';
            }

            $identity = $this->db->table('auth_identities')
                ->where('user_id', $userId)
                ->where('type', 'email_password')
                ->get()
                ->getRowArray();

            if ($identity === null) {
                return false;
            }

            $requiresReset = (int) ($identity['force_reset'] ?? 0) === 1;
            if ($requiresReset) {
                // Keep invited status until password reset is completed.
                if ($portalAccess !== 'invited') {
                    if ($profileTable !== null) {
                        $this->db->table($profileTable)
                            ->where('user_id', $userId)
                            ->update([
                                'portal_access' => 'invited',
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                    }
                }
                return false;
            }

            if ($portalAccess === 'enabled') {
                return false;
            }

            $now = date('Y-m-d H:i:s');
            $updates = [
                'portal_access' => 'enabled',
                'updated_at' => $now,
            ];

            if (array_key_exists('portal_enabled_at', $profile)) {
                $updates['portal_enabled_at'] = $now;
            }

            if ($profileTable !== null) {
                $this->db->table($profileTable)
                    ->where('user_id', $userId)
                    ->update($updates);
            }

            return true;
        } catch (\Throwable $e) {
            log_message('warning', '[MemberAccessService] Failed portal invited->enabled transition for user ' . $userId . ': ' . $e->getMessage());
            return false;
        }
    }

    private function sendPortalInviteEmail(string $recipientEmail, int $parishId): void
    {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            return;
        }

        $context = $parishId > 0 ? ['parish_id' => $parishId] : [];

        try {
            $emailService = (new EmailScopeService())->mailer($context, ['overrides' => ['mailType' => 'html']]);
        } catch (\Throwable $e) {
            log_message('error', '[MemberAccessService] Failed to build scoped portal invite mailer: {message}', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('PORTAL_INVITE_EMAIL_FAILED');
        }

        $emailService->setTo($recipientEmail);
        $emailService->setSubject(lang('Auth.portalInviteSubject'));
        $passwordSetupUrl = base_url('login/magic-link');
        $emailService->setMessage(
            '<p>' . esc(lang('Auth.portalInviteIntro')) . '</p>'
                . '<p>' . esc(lang('Auth.portalInvitePasswordNote')) . '</p>'
                . '<p>' . esc(lang('Auth.portalInviteGoTo')) . ' <a href="' . esc($passwordSetupUrl, 'attr') . '">' . esc($passwordSetupUrl) . '</a></p>'
        );

        if ($emailService->send(false) === false) {
            log_message('error', '[MemberAccessService] portal invite send failed; code={code}; parish_id={parishId}', [
                'code'     => EmailFailureClassifier::classify((string) $emailService->printDebugger(['headers'])),
                'parishId' => $parishId,
            ]);
            throw new \RuntimeException('PORTAL_INVITE_EMAIL_FAILED');
        }

        $emailService->clear();
    }

    /**
     * @return array<int,string>
     */
    private function getColumnsForTable(string $baseTable): array
    {
        return $this->schemaResolver->getColumnsForTable($baseTable);
    }

    private function resolveExistingProfileTable(): ?string
    {
        return $this->schemaResolver->resolveExistingProfileTable();
    }

    /**
     * @param array<int,array<string,string>> $errors
     * @param array<string,mixed>|null $data
     * @return array<string,mixed>
     */
    private function result(bool $ok, int $status, string $code, string $message, array $errors = [], ?array $data = null): array
    {
        return [
            'ok' => $ok,
            'http_status' => $status,
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
            'data' => $data,
        ];
    }
}
