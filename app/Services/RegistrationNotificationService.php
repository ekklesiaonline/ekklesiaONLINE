<?php

namespace App\Services;

use App\Libraries\EmailFailureClassifier;
use App\Models\ParishRoleAuditModel;
use CodeIgniter\Database\BaseConnection;
use Config\AuthGroups;

/**
 * RegistrationNotificationService
 *
 * Notifies parish/diocese/province admins when a registration request
 * awaits review, and writes the accompanying parish role audit rows.
 * HTTP concerns stay in the controller.
 */
class RegistrationNotificationService
{
    private BaseConnection $db;
    private ParishRoleAuditModel $roleAudit;
    private HierarchyService $hierarchy;
    private EmailScopeService $emailScope;

    public function __construct(
        ?BaseConnection $db = null,
        ?ParishRoleAuditModel $roleAudit = null,
        ?HierarchyService $hierarchy = null,
        ?EmailScopeService $emailScope = null
    ) {
        $this->db = $db ?? db_connect();
        $this->roleAudit = $roleAudit ?? model(ParishRoleAuditModel::class);
        $this->hierarchy = $hierarchy ?? new HierarchyService($this->db);
        $this->emailScope = $emailScope ?? new EmailScopeService(null, $this->hierarchy);
    }

    /**
     * Email all admins in scope for the parish's pending-review queue.
     */
    public function notifyParishAdminsOfPendingReview(
        int $parishId,
        int $requestId,
        int $requestUserId,
        string $firstName,
        string $lastName,
        string $emailAddress,
        ?string $profileTable = null,
        array $profileColumns = [],
        ?int $actorUserId = null
    ): void {
        if ($parishId <= 0) {
            return;
        }

        $recipients = $this->parishAdminRecipients($parishId, $profileTable, $profileColumns);
        if ($recipients === []) {
            $this->logAudit(
                $parishId,
                $actorUserId,
                $requestUserId,
                'registration_review_notification_missing_recipients',
                'No admin recipients found for selected parish.'
            );
            return;
        }

        $parishName = $this->hierarchy->nameFor('parish', $parishId);

        $requesterName = trim($firstName . ' ' . $lastName);
        $dashboardUrl = base_url('members');
        $subject = lang('Auth.registrationPendingSubject');
        $message = lang('Auth.registrationPendingBody', [
            $parishName !== '' ? $parishName : lang('Auth.registrationPendingUnknown'),
            $requesterName !== '' ? $requesterName : lang('Auth.registrationPendingNoName'),
            $emailAddress,
            $requestId,
            $dashboardUrl,
        ]);

        $delivered = 0;

        foreach ($recipients as $recipient) {
            try {
                $email = $this->emailScope->mailer(['parish_id' => $parishId]);
                $email->setTo($recipient);
                $email->setSubject($subject);
                $email->setMessage($message);
                if ($email->send(false) === false) {
                    log_message('warning', 'Pending review notification send failed; code={code}; parish_id={parishId}', [
                        'code'     => EmailFailureClassifier::classify((string) $email->printDebugger(['headers'])),
                        'parishId' => $parishId,
                    ]);
                    continue;
                }
                $delivered++;
            } catch (\Throwable $e) {
                log_message('warning', 'Pending review notification raised {class}; parish_id={parishId}', [
                    'class'    => $e::class,
                    'parishId' => $parishId,
                ]);
            }
        }

        $this->logAudit(
            $parishId,
            $actorUserId,
            $requestUserId,
            'registration_review_notification_sent',
            'Pending registration notification dispatched to ' . $delivered . ' admin recipient(s).'
        );
    }

    /**
     * Distinct admin emails covering the parish plus its diocese and
     * province scopes (when the profile table carries those columns).
     *
     * @param list<string> $profileColumns Lowercased profile column names.
     * @return list<string>
     */
    public function parishAdminRecipients(int $parishId, ?string $profileTable, array $profileColumns): array
    {
        if ($profileTable === null) {
            return [];
        }

        $hasDioceseId = in_array('diocese_id', $profileColumns, true);
        $hasProvinceId = in_array('province_id', $profileColumns, true);

        $parish = $this->hierarchy->parishRow($parishId);
        $dioceseId = (int) ($parish['diocese_id'] ?? 0);
        $provinceId = (int) ($parish['province_id'] ?? 0);
        if ($provinceId <= 0 && $dioceseId > 0) {
            $provinceId = (int) ($this->hierarchy->provinceIdForDiocese($dioceseId) ?? 0);
        }

        $emails = $this->adminEmailsForScope($profileTable, 'parish_id', AuthGroups::PARISH_ADMIN, $parishId);

        if ($dioceseId > 0 && $hasDioceseId) {
            $emails = array_merge($emails, $this->adminEmailsForScope($profileTable, 'diocese_id', AuthGroups::DIOCESE_ADMIN, $dioceseId));
        }

        if ($provinceId > 0 && $hasProvinceId) {
            $emails = array_merge($emails, $this->adminEmailsForScope($profileTable, 'province_id', AuthGroups::PROVINCE_ADMIN, $provinceId));
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return list<string>
     */
    public function adminEmailsForScope(string $profileTable, string $profileColumn, string $group, int $scopeId): array
    {
        $rows = $this->db->table('auth_groups_users agu')
            ->select('ai.secret as email')
            ->distinct()
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->join($profileTable . ' p', 'p.user_id = u.id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'inner')
            ->where('agu.group', $group)
            ->where('p.' . $profileColumn, $scopeId)
            ->get()
            ->getResultArray();

        $emails = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Write a registration-related row to the parish role audit log.
     */
    public function logAudit(int $parishId, ?int $actorUserId, int $targetUserId, string $action, string $details): void
    {
        $actorUserId = (int) ($actorUserId ?? 0);
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            return;
        }

        try {
            $this->roleAudit->insert([
                'parish_id' => $parishId > 0 ? $parishId : null,
                'actor_user_id' => $actorUserId,
                'target_user_id' => $targetUserId,
                'action' => $action,
                'role' => null,
                'details' => $details,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed writing registration audit log: {message}', ['message' => $e->getMessage()]);
        }
    }
}
