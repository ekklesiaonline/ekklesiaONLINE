<?php

namespace App\Services;


use App\Models\MessageCampaignModel;
use App\Models\MessageRecipientModel;
use App\Models\PersonModel;
use CodeIgniter\Database\BaseConnection;

/**
 * BulkMessageService
 *
 * Resolves a parish-scoped audience (membership statuses, small groups,
 * ministry teams, legacy guests) into canonical recipients, then drafts and
 * dispatches a message_campaign.
 *
 * Channels:
 *  - email        — MessagingService::sendEmail, per recipient
 *  - sms          — MessagingService::queueSms (rate-limited by sms:process)
 *  - notification — one notification + notification_recipient per linked user
 *
 * Compliance:
 *  - person.opted_out_channels / guest.opted_out_channels are honored
 *    (channel synonyms: sms=text, notification=in_app)
 *  - Recipients without a usable address/user are recorded as 'skipped'
 *    with a reason, never silently dropped
 *  - Contact fields are hydrated through the models so encrypted
 *    person/guest email/phone values are decrypted
 *
 * Audience descriptor shape (stored as JSON on the campaign):
 *   ['statuses' => string[], 'group_ids' => int[], 'team_ids' => int[],
 *    'include_guests' => bool]
 */
class BulkMessageService
{
    /** Opt-out synonyms per channel (opted_out_channels vocabulary). */
    private const OPTOUT_MAP = [
        'email'        => ['email'],
        'sms'          => ['sms', 'text'],
        'notification' => ['notification', 'in_app'],
    ];

    private BaseConnection $db;
    private MessageCampaignModel $campaigns;
    private MessageRecipientModel $recipients;
    private MessagingService $messaging;

    public function __construct(
        ?BaseConnection $db = null,
        ?MessageCampaignModel $campaigns = null,
        ?MessageRecipientModel $recipients = null,
        ?MessagingService $messaging = null
    ) {
        $this->db         = $db ?? db_connect();
        $this->campaigns  = $campaigns ?? new MessageCampaignModel();
        $this->recipients = $recipients ?? new MessageRecipientModel();
        $this->messaging  = $messaging ?? new MessagingService();
    }

    /**
     * @return array<int, string>
     */
    public static function audienceStatuses(): array
    {
        return MembershipStatusService::affiliationStatuses();
    }

    /**
     * Resolve the audience descriptor into unique recipients.
     *
     * @param array{statuses?: array, group_ids?: array, team_ids?: array, include_guests?: bool} $audience
     * @return array<int, array<string, mixed>> keyed list: person_id, guest_id,
     *         user_id, first_name, last_name, name, email, phone, opted_out
     */
    public function resolveAudience(int $parishId, array $audience): array
    {
        $personIds = $this->personIdsForAudience($parishId, $audience);
        $recipients = [];
        $userMap = [];

        if ($personIds !== []) {
            $persons = (new PersonModel())->whereIn('id', $personIds)->findAll();
            $userMap = $this->userIdsForPersons($personIds);

            foreach ($persons as $person) {
                $pid = (int) $person['id'];
                $recipients['p' . $pid] = [
                    'person_id'  => $pid,
                    'guest_id'   => null,
                    'user_id'    => $userMap[$pid] ?? null,
                    'first_name' => (string) ($person['first_name'] ?? ''),
                    'last_name'  => (string) ($person['last_name'] ?? ''),
                    'name'       => trim((string) ($person['first_name'] ?? '') . ' ' . (string) ($person['last_name'] ?? '')),
                    'email'      => (string) ($person['email'] ?? ''),
                    'phone'      => (string) ($person['phone'] ?? ''),
                    'opted_out'  => $this->parseOptedOut($person['opted_out_channels'] ?? null),
                ];
            }
        }

        if (! empty($audience['include_guests'])) {
            foreach ($this->guestPersonRows($parishId) as $guest) {
                $pid = (int) $guest['id'];
                if (isset($recipients['p' . $pid])) {
                    continue;
                }

                $recipients['p' . $pid] = [
                    'person_id'  => $pid,
                    'guest_id'   => null,
                    'user_id'    => $userMap[$pid] ?? ($this->userIdsForPersons([$pid])[$pid] ?? null),
                    'first_name' => (string) ($guest['first_name'] ?? ''),
                    'last_name'  => (string) ($guest['last_name'] ?? ''),
                    'name'       => trim((string) ($guest['first_name'] ?? '') . ' ' . (string) ($guest['last_name'] ?? '')),
                    'email'      => (string) ($guest['email'] ?? ''),
                    'phone'      => (string) ($guest['phone'] ?? ''),
                    'opted_out'  => $this->parseOptedOut($guest['opted_out_channels'] ?? null),
                ];
            }
        }

        return array_values($recipients);
    }

    /**
     * Classify a resolved audience for a channel — for the preview step.
     *
     * @return array{total: int, deliverable: int, skipped_opted_out: int,
     *               skipped_no_address: int, skipped_no_user: int}
     */
    public function previewCounts(array $recipients, string $channel): array
    {
        $counts = [
            'total'              => count($recipients),
            'deliverable'        => 0,
            'skipped_opted_out'  => 0,
            'skipped_no_address' => 0,
            'skipped_no_user'    => 0,
        ];

        foreach ($recipients as $recipient) {
            $skip = $this->skipReason($recipient, $channel);
            if ($skip === null) {
                $counts['deliverable']++;
            } elseif ($skip === MessageRecipientModel::SKIP_OPTED_OUT) {
                $counts['skipped_opted_out']++;
            } elseif ($skip === MessageRecipientModel::SKIP_NO_USER) {
                $counts['skipped_no_user']++;
            } else {
                $counts['skipped_no_address']++;
            }
        }

        return $counts;
    }

    /**
     * Persist a draft campaign plus its resolved recipient rows.
     *
     * @return array{ok: bool, id?: int, code?: string}
     */
    public function createDraft(int $parishId, string $channel, string $subject, string $body, array $audience, ?int $createdBy): array
    {
        if (! in_array($channel, MessageCampaignModel::channels(), true)) {
            return ['ok' => false, 'code' => 'invalid_channel'];
        }

        $recipients = $this->resolveAudience($parishId, $audience);
        if ($recipients === []) {
            return ['ok' => false, 'code' => 'empty_audience'];
        }

        $this->db->resetTransStatus();
        $this->db->transStart();

        $campaignId = $this->campaigns->insert([
            'parish_id'        => $parishId,
            'channel'          => $channel,
            'subject'          => $subject !== '' ? $subject : null,
            'body'             => $body,
            'audience'         => json_encode($audience),
            'status'           => MessageCampaignModel::STATUS_DRAFT,
            'total_recipients' => count($recipients),
            'created_by'       => $createdBy,
        ], true);

        if ($campaignId === false) {
            $this->db->transRollback();
            return ['ok' => false, 'code' => 'insert_failed'];
        }

        foreach ($recipients as $recipient) {
            $skip = $this->skipReason($recipient, $channel);
            $this->recipients->insert([
                'campaign_id' => (int) $campaignId,
                'person_id'   => $recipient['person_id'],
                'guest_id'    => $recipient['guest_id'],
                'user_id'     => $recipient['user_id'],
                'name'        => $recipient['name'] !== '' ? $recipient['name'] : null,
                'first_name'  => $recipient['first_name'] !== '' ? $recipient['first_name'] : null,
                'last_name'   => $recipient['last_name'] !== '' ? $recipient['last_name'] : null,
                'address'     => $this->addressFor($recipient, $channel),
                'status'      => $skip === null ? MessageRecipientModel::STATUS_PENDING : MessageRecipientModel::STATUS_SKIPPED,
                'skip_reason' => $skip,
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['ok' => false, 'code' => 'insert_failed'];
        }

        return ['ok' => true, 'id' => (int) $campaignId];
    }

    /**
     * Dispatch a draft campaign. Idempotent: only 'draft' campaigns send, and
     * only 'pending' recipients dispatch.
     *
     * @return array{ok: bool, code?: string, counts?: array<string, int>}
     */
    public function send(int $campaignId, int $parishId): array
    {
        $campaign = $this->campaigns
            ->where('id', $campaignId)
            ->where('parish_id', $parishId)
            ->first();

        if ($campaign === null) {
            return ['ok' => false, 'code' => 'not_found'];
        }

        if (($campaign['status'] ?? '') !== MessageCampaignModel::STATUS_DRAFT) {
            return ['ok' => false, 'code' => 'already_sent'];
        }

        $channel = (string) $campaign['channel'];
        $parishName = $this->parishName($parishId);

        $this->campaigns->update($campaignId, ['status' => MessageCampaignModel::STATUS_SENDING]);

        $pending = $this->recipients
            ->where('campaign_id', $campaignId)
            ->where('status', MessageRecipientModel::STATUS_PENDING)
            ->findAll();

        foreach ($pending as $recipient) {
            $this->dispatch($campaign, $recipient, $parishName);
        }

        $counts = $this->recipients->countsForCampaign($campaignId);
        $sent = $counts[MessageRecipientModel::STATUS_SENT] + $counts[MessageRecipientModel::STATUS_QUEUED];

        $this->campaigns->update($campaignId, [
            'status'         => MessageCampaignModel::STATUS_SENT,
            'sent_count'     => $sent,
            'skipped_count'  => $counts[MessageRecipientModel::STATUS_SKIPPED],
            'failed_count'   => $counts[MessageRecipientModel::STATUS_FAILED],
            'sent_at'        => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'counts' => $counts];
    }

    /**
     * Cancel a draft campaign.
     */
    public function cancel(int $campaignId, int $parishId): bool
    {
        $campaign = $this->campaigns
            ->where('id', $campaignId)
            ->where('parish_id', $parishId)
            ->first();

        if ($campaign === null || ($campaign['status'] ?? '') !== MessageCampaignModel::STATUS_DRAFT) {
            return false;
        }

        return (bool) $this->campaigns->update($campaignId, ['status' => MessageCampaignModel::STATUS_CANCELLED]);
    }

    /**
     * Render a template for a recipient ({first_name}, {last_name}, {name},
     * {parish_name}).
     */
    public function render(string $template, array $recipient, string $parishName): string
    {
        return strtr($template, [
            '{first_name}'  => (string) ($recipient['first_name'] ?? ''),
            '{last_name}'   => (string) ($recipient['last_name'] ?? ''),
            '{name}'        => (string) ($recipient['name'] ?? ''),
            '{parish_name}' => $parishName,
        ]);
    }

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------

    private function dispatch(array $campaign, array $recipient, string $parishName): void
    {
        $channel  = (string) $campaign['channel'];
        $subject  = (string) ($campaign['subject'] ?? '');
        $parishId = (int) $campaign['parish_id'];

        $body    = $this->render((string) $campaign['body'], $recipient, $parishName);
        $subject = $this->render($subject, $recipient, $parishName);
        $now     = date('Y-m-d H:i:s');

        $update = ['sent_at' => $now];

        try {
            switch ($channel) {
                case MessageCampaignModel::CHANNEL_EMAIL:
                    $result = $this->messaging->sendEmail(
                        (string) $recipient['address'],
                        $subject !== '' ? $subject : $parishName,
                        nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'), false),
                        $body
                    );
                    $update['status'] = $result['success'] ? MessageRecipientModel::STATUS_SENT : MessageRecipientModel::STATUS_FAILED;
                    $update['error']  = $result['success'] ? null : substr((string) ($result['error'] ?? 'send failed'), 0, 500);
                    break;

                case MessageCampaignModel::CHANNEL_SMS:
                    $result = $this->messaging->queueSms((string) $recipient['address'], $body, $now, null, $parishId);
                    $update['status']   = $result['success'] ? MessageRecipientModel::STATUS_QUEUED : MessageRecipientModel::STATUS_FAILED;
                    $update['queue_id'] = $result['success'] ? (int) $result['queue_id'] : null;
                    $update['error']    = $result['success'] ? null : substr((string) ($result['error'] ?? 'queue failed'), 0, 500);
                    break;

                case MessageCampaignModel::CHANNEL_NOTIFICATION:
                    $update['status'] = $this->dispatchNotification($recipient, $subject, $body, $parishId)
                        ? MessageRecipientModel::STATUS_SENT
                        : MessageRecipientModel::STATUS_FAILED;
                    break;

                default:
                    $update['status'] = MessageRecipientModel::STATUS_FAILED;
                    $update['error']  = 'unknown channel';
            }
        } catch (\Throwable $e) {
            $update['status'] = MessageRecipientModel::STATUS_FAILED;
            $update['error']  = substr($e->getMessage(), 0, 500);
            log_message('error', 'BulkMessageService dispatch failed for recipient {id}: {error}', [
                'id'    => (int) ($recipient['id'] ?? 0),
                'error' => $e->getMessage(),
            ]);
        }

        $this->recipients->update((int) $recipient['id'], $update);
    }

    /**
     * One notification + notification_recipient row per user so token
     * substitution stays per-person.
     */
    private function dispatchNotification(array $recipient, string $subject, string $body, int $parishId): bool
    {
        $userId = (int) ($recipient['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $notificationId = $this->db->table('notification')->insert([
            'scope_type' => 'parish',
            'scope_id'   => $parishId,
            'category'   => 'bulk_message',
            'severity'   => 'info',
            'title'      => $subject !== '' ? $subject : $body,
            'body'       => $body,
            'status'     => 'published',
            'publish_at' => date('Y-m-d H:i:s'),
            'created_by' => (int) (auth()->id() ?? 0) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (! $notificationId) {
            return false;
        }

        return (bool) $this->db->table('notification_recipient')->insert([
            'notification_id' => (int) $notificationId,
            'user_id'         => $userId,
            'delivery_status' => 'delivered',
            'delivered_at'    => date('Y-m-d H:i:s'),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------------
    // Audience resolution
    // ------------------------------------------------------------------

    /**
     * @return array<int, int> distinct person_ids
     */
    private function personIdsForAudience(int $parishId, array $audience): array
    {
        $ids = [];

        $statuses = array_values(array_intersect(
            array_map('strval', (array) ($audience['statuses'] ?? [])),
            self::audienceStatuses()
        ));

        if ($statuses !== []) {
            $rows = $this->db->table('parish_affiliation')
                ->select('person_id')
                ->distinct()
                ->where('parish_id', $parishId)
                ->where('deleted_at IS NULL', null, false)
                ->whereIn('membership_status', $statuses)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $ids[(int) $row['person_id']] = true;
            }
        }

        $groupIds = array_values(array_filter(array_map('intval', (array) ($audience['group_ids'] ?? []))));
        if ($groupIds !== [] && $this->db->tableExists('group_membership') && $this->db->tableExists('small_group')) {
            $rows = $this->db->table('group_membership gm')
                ->select('gm.person_id')
                ->distinct()
                ->join('small_group sg', 'sg.id = gm.small_group_id')
                ->where('sg.parish_id', $parishId)
                ->where('sg.deleted_at IS NULL', null, false)
                ->where('gm.status', 'active')
                ->whereIn('gm.small_group_id', $groupIds)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $ids[(int) $row['person_id']] = true;
            }
        }

        $teamIds = array_values(array_filter(array_map('intval', (array) ($audience['team_ids'] ?? []))));
        if ($teamIds !== [] && $this->db->tableExists('team_member') && $this->db->tableExists('team')) {
            $rows = $this->db->table('team_member tm')
                ->select('tm.person_id, tm.user_id')
                ->join('team vt', 'vt.id = tm.team_id')
                ->where('vt.parish_id', $parishId)
                ->whereIn('tm.team_id', $teamIds)
                ->get()
                ->getResultArray();

            $orphanUserIds = [];
            foreach ($rows as $row) {
                $pid = (int) ($row['person_id'] ?? 0);
                if ($pid > 0) {
                    $ids[$pid] = true;
                } elseif ((int) ($row['user_id'] ?? 0) > 0) {
                    $orphanUserIds[] = (int) $row['user_id'];
                }
            }

            // Legacy rows keyed only by user_id resolve through user_person.
            if ($orphanUserIds !== [] && $this->db->tableExists('user_person')) {
                $links = $this->db->table('user_person')
                    ->select('person_id')
                    ->whereIn('user_id', $orphanUserIds)
                    ->get()
                    ->getResultArray();
                foreach ($links as $link) {
                    $ids[(int) $link['person_id']] = true;
                }
            }
        }

        unset($ids[0]);

        return array_map('intval', array_keys($ids));
    }

    /**
     * Unconverted parish guests (decrypted via the model).
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Unconverted guests as canonical person rows (contact fields still
     * encrypted — decrypted downstream before dispatch).
     */
    private function guestPersonRows(int $parishId): array
    {
        $rows = (new GuestDirectoryService())
            ->scopeBuilder($parishId)
            ->select('pe.id, pe.first_name, pe.last_name, pe.email, pe.phone, pe.opted_out_channels')
            ->where('pa.converted_to_member_at IS NULL', null, false)
            ->where('pa.affiliation_type', 'guest')
            ->get()
            ->getResultArray();

        $directory = new GuestDirectoryService();
        return array_map(static fn(array $row): array => $directory->decryptRow($row), $rows);
    }

    /**
     * @return array<int, int> person_id => user_id (primary link preferred)
     */
    private function userIdsForPersons(array $personIds): array
    {
        if ($personIds === [] || ! $this->db->tableExists('user_person')) {
            return [];
        }

        $rows = $this->db->table('user_person')
            ->select('person_id, user_id')
            ->whereIn('person_id', $personIds)
            ->orderBy('is_primary', 'DESC')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $pid = (int) $row['person_id'];
            if (! isset($map[$pid])) {
                $map[$pid] = (int) $row['user_id'];
            }
        }

        return $map;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Why a recipient cannot receive the channel — null when deliverable.
     */
    private function skipReason(array $recipient, string $channel): ?string
    {
        $optedOut = (array) ($recipient['opted_out'] ?? []);

        foreach (self::OPTOUT_MAP[$channel] ?? [$channel] as $marker) {
            if (in_array($marker, $optedOut, true)) {
                return MessageRecipientModel::SKIP_OPTED_OUT;
            }
        }

        if ($channel === MessageCampaignModel::CHANNEL_NOTIFICATION) {
            return ((int) ($recipient['user_id'] ?? 0) > 0)
                ? null
                : MessageRecipientModel::SKIP_NO_USER;
        }

        return $this->addressFor($recipient, $channel) !== null
            ? null
            : MessageRecipientModel::SKIP_NO_ADDRESS;
    }

    /**
     * Delivery address for the channel (email address or phone number).
     */
    private function addressFor(array $recipient, string $channel): ?string
    {
        $address = trim((string) ($channel === MessageCampaignModel::CHANNEL_SMS
            ? ($recipient['phone'] ?? '')
            : ($recipient['email'] ?? '')));

        return $address !== '' ? $address : null;
    }

    /**
     * @return array<int, string>
     */
    private function parseOptedOut(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === '[]') {
            return [];
        }

        $channels = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($channels) ? array_values(array_map('strval', $channels)) : [];
    }

    private function parishName(int $parishId): string
    {
        $row = $this->db->table('parish')
            ->select('name')
            ->where('id', $parishId)
            ->get()
            ->getRowArray();

        return (string) ($row['name'] ?? '');
    }
}
