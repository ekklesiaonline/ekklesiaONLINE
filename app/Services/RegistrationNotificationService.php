<?php

namespace App\Services;

use App\Models\ParishRoleAuditModel;
use CodeIgniter\Database\BaseConnection;

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

    public function __construct(
        ?BaseConnection $db = null,
        ?ParishRoleAuditModel $roleAudit = null,
        ?HierarchyService $hierarchy = null
    ) {
        $this->db = $db ?? db_connect();
        $this->roleAudit = $roleAudit ?? model(ParishRoleAuditModel::class);
        $this->hierarchy = $hierarchy ?? new HierarchyService($this->db);
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
        $subject = 'New registration awaiting parish review';
        $message = "A new user registration is awaiting review.\n\n"
            . 'Parish: ' . ($parishName !== '' ? $parishName : 'Unknown') . "\n"
            . 'Applicant: ' . ($requesterName !== '' ? $requesterName : 'Name not provided') . "\n"
            . 'Email: ' . $emailAddress . "\n\n"
            . 'Action needed: authorize or ban this registration in the Members module.\n'
            . 'Request ID: #' . $requestId . "\n"
            . 'Review: ' . $dashboardUrl;

        $email = service('email');
        $delivered = 0;

        foreach ($recipients as $recipient) {
            try {
                $email->clear(true);
                $email->setTo($recipient);
                $email->setSubject($subject);
                $email->setMessage($message);
                $email->send();
                $delivered++;
            } catch (\Throwable $e) {
                log_message('warning', 'Failed sending parish pending review email to {email}: {message}', [
                    'email' => $recipient,
                    'message' => $e->getMessage(),
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

        $emails = $this->adminEmailsForScope($profileTable, 'parish_id', 'parishadmin', $parishId);

        if ($dioceseId > 0 && $hasDioceseId) {
            $emails = array_merge($emails, $this->adminEmailsForScope($profileTable, 'diocese_id', 'diocesanadmin', $dioceseId));
        }

        if ($provinceId > 0 && $hasProvinceId) {
            $emails = array_merge($emails, $this->adminEmailsForScope($profileTable, 'province_id', 'provinceadmin', $provinceId));
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
