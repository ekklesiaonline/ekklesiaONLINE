<?php

namespace App\Controllers;

class SystemPrimary extends BaseController
{
    use SystemAdminService;

    public function transferPrimarySystemAdmin(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $rules = [
            'current_password' => 'required|min_length[8]',
            'otp_code' => 'permit_empty|exact_length[6]|numeric',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $currentPrimaryId = $this->getPrimarySystemAdminId();
        if ($currentPrimaryId === null) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.primarySystemAdminNotConfigured'));
        }

        if (!$this->isPrimarySystemAdmin()) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.onlyPrimaryCanTransferPrimary'));
        }

        $actorUserId = (int) (auth()->id() ?? 0);

        if ($userId === $currentPrimaryId) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.alreadyPrimarySystemAdmin'));
        }

        if (!$this->isSystemAdminUser($userId)) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.adminUserNotFound'));
        }

        // Brute-force protection for the password check that gates primary transfer.
        if ($this->isAccountLocked($actorUserId)) {
            return redirect()->to(base_url('system/system-admins'))
                ->with('error', 'Your account has been temporarily locked due to too many failed attempts. Please try again later.');
        }

        if (!$this->checkRateLimit($actorUserId, 'primary_transfer_auth', 5, 900)) {
            return redirect()->to(base_url('system/system-admins'))
                ->with('error', 'Too many primary transfer attempts. Please try again later.');
        }

        $users = auth()->getProvider();
        $actor = $users->findById($actorUserId);
        $currentPassword = (string) $this->request->getPost('current_password');
        if (!$actor || empty($actor->password_hash) || !service('passwords')->verify($currentPassword, $actor->password_hash)) {
            $this->trackFailedAttempt($actorUserId, 'primary_transfer_auth');
            $this->audit()->log('system.primary_transfer.failed', [
                'entity_type' => 'system_admin_primary',
                'entity_id' => 1,
                'meta' => [
                    'actor_user_id' => $actorUserId,
                    'target_user_id' => $userId,
                    'failure_reason' => 'password_verification_failed',
                ],
            ]);
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.invalidCurrentPassword'));
        }

        $this->clearFailedAttempts($actorUserId, 'primary_transfer_auth');

        $otpEnabled = $this->isPrimaryTransferOtpEnabled();
        if ($otpEnabled) {
            $otpCode = trim((string) $this->request->getPost('otp_code'));
            if ($otpCode === '') {
                $email = (string) ($actor->email ?? '');
                if ($email === '') {
                    return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.otpEmailUnavailable'));
                }

                $challengeCode = $this->issuePrimaryTransferOtpChallenge($actorUserId, $userId, $email);

                $this->audit()->log('system.primary_transfer.otp_sent', [
                    'entity_type' => 'system_admin_primary',
                    'entity_id' => 1,
                    'meta' => [
                        'actor_user_id' => $actorUserId,
                        'target_user_id' => $userId,
                        'channel' => 'email',
                    ],
                ]);

                try {
                    $emailService = service('email');
                    $emailService->setTo($email);
                    $emailService->setSubject(lang('System.transferOtpEmailSubject'));
                    $emailService->setMessage($this->buildPrimaryTransferOtpEmailBody($challengeCode, $userId));
                    $emailService->send(false);
                } catch (\Throwable $e) {
                    $this->clearPrimaryTransferOtpChallenge();
                    return $this->safeRedirectError(lang('System.otpSendFailed'), $e);
                }

                return redirect()->to(base_url('system/system-admins'))->with('success', lang('System.otpSentForPrimaryTransfer'));
            }

            if (!$this->verifyPrimaryTransferOtpChallenge($actorUserId, $userId, $otpCode)) {
                $this->audit()->log('system.primary_transfer.failed', [
                    'entity_type' => 'system_admin_primary',
                    'entity_id' => 1,
                    'meta' => [
                        'actor_user_id' => $actorUserId,
                        'target_user_id' => $userId,
                        'failure_reason' => 'otp_verification_failed',
                    ],
                ]);
                return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.invalidOrExpiredOtpCode'));
            }
        }

        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->transStart();
        try {
            $existingPrimary = $db->table('system_admin_primary')->where('id', 1)->countAllResults() > 0;
            if ($existingPrimary) {
                $db->table('system_admin_primary')
                    ->where('id', 1)
                    ->update([
                        'user_id' => $userId,
                        'updated_at' => $now,
                    ]);
            } else {
                $db->table('system_admin_primary')->insert([
                    'id' => 1,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->syncPrimarySystemAdminPermission($userId, $currentPrimaryId);
            $this->grantSystemAdminDelegationPermissions($db, $userId, $now);

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed');
            }

            if ($otpEnabled) {
                $this->clearPrimaryTransferOtpChallenge();
            }

            $this->audit()->log('system.primary_transfer.completed', [
                'entity_type' => 'system_admin_primary',
                'entity_id' => 1,
                'old_values' => [
                    'user_id' => $currentPrimaryId,
                ],
                'new_values' => [
                    'user_id' => $userId,
                    'delegation_permissions' => $this->getGrantedSystemAdminDelegationPermissions($userId),
                ],
                'meta' => [
                    'actor_user_id' => $actorUserId,
                    'otp_enabled' => $otpEnabled,
                ],
            ]);

            return redirect()->to(base_url('system/system-admins'))->with('success', lang('System.primaryTransferred'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->safeRedirectError(lang('System.primaryTransferFailed'), $e);
        }
    }
}
