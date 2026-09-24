<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Models\MessageCampaignModel;
use App\Models\MessageRecipientModel;
use App\Models\MessageTemplateModel;
use App\Services\BulkMessageService;

class Messages extends BaseController
{
    private BulkMessageService $messaging;

    public function __construct()
    {
        $this->messaging = new BulkMessageService();
    }

    /**
     * Campaign list for the operational parish.
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('messaging.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        $campaigns = model('MessageCampaignModel')
            ->where('parish_id', $parishId)
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('Clergy\Views\Messages/index', array_merge($this->getCommonViewData(lang('Messages.title')), [
            'campaigns' => $campaigns,
            'canSend'   => auth()->user()->can('messaging.send'),
        ]));
    }

    /**
     * Compose form: audience pickers, channel, template, subject, body.
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        return view('Clergy\Views\Messages/compose', array_merge($this->getCommonViewData(lang('Messages.compose')), [
            'statuses'   => BulkMessageService::audienceStatuses(),
            'groups'     => model('SmallGroupModel')->where('parish_id', $parishId)->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'teams'      => model('TeamModel')->getParishTeams($parishId),
            'templates'  => model('MessageTemplateModel')->activeForParish($parishId),
            'channels'   => MessageCampaignModel::channels(),
        ]));
    }

    /**
     * Persist a draft campaign and its resolved recipients.
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        if (! $this->validate($this->campaignRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $audience = $this->postedAudience();
        if ($audience === null) {
            return redirect()->back()->withInput()->with('error', lang('Messages.errorNoAudience'));
        }

        $channel = (string) $this->request->getPost('channel');
        $result  = $this->messaging->createDraft(
            $parishId,
            $channel,
            trim((string) ($this->request->getPost('subject') ?? '')),
            trim((string) $this->request->getPost('body')),
            $audience,
            auth()->loggedIn() ? (int) auth()->id() : null
        );

        if (! $result['ok']) {
            $message = ((string) ($result['code'] ?? '')) === 'empty_audience'
                ? lang('Messages.errorEmptyAudience')
                : lang('Messages.errorSaveFailed');

            return redirect()->back()->withInput()->with('error', $message);
        }

        $this->audit()->log('messaging.campaign.draft', [
            'parish_id'   => $parishId,
            'entity_type' => 'message_campaign',
            'entity_id'   => (int) $result['id'],
            'new_values'  => ['channel' => $channel],
        ]);

        return redirect()->to('/messages/preview/' . (int) $result['id']);
    }

    /**
     * Draft preview: deliverable vs skipped, recipient list, send/cancel.
     */
    public function preview($id)
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $campaign = $this->campaignForParish((int) $id, $parishId);

        if ($campaign === null) {
            return redirect()->to('/messages')->with('error', lang('Messages.errorNotFound'));
        }

        if (($campaign['status'] ?? '') !== MessageCampaignModel::STATUS_DRAFT) {
            return redirect()->to('/messages/view/' . (int) $campaign['id']);
        }

        return view('Clergy\Views\Messages/preview', array_merge($this->getCommonViewData(lang('Messages.preview')), [
            'campaign'   => $campaign,
            'recipients' => $this->recipientsFor((int) $campaign['id']),
        ]));
    }

    /**
     * Dispatch a draft campaign.
     */
    public function send($id)
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $campaign = $this->campaignForParish((int) $id, $parishId);

        if ($campaign === null) {
            return redirect()->to('/messages')->with('error', lang('Messages.errorNotFound'));
        }

        $result = $this->messaging->send((int) $campaign['id'], $parishId);

        if (! $result['ok']) {
            $message = ((string) ($result['code'] ?? '')) === 'already_sent'
                ? lang('Messages.errorAlreadySent')
                : lang('Messages.errorSendFailed');

            return redirect()->to('/messages/view/' . (int) $campaign['id'])->with('error', $message);
        }

        $counts = $result['counts'] ?? [];
        $this->audit()->log('messaging.campaign.send', [
            'parish_id'   => $parishId,
            'entity_type' => 'message_campaign',
            'entity_id'   => (int) $campaign['id'],
            'new_values'  => [
                'channel' => (string) $campaign['channel'],
                'sent'    => ($counts[MessageRecipientModel::STATUS_SENT] ?? 0) + ($counts[MessageRecipientModel::STATUS_QUEUED] ?? 0),
                'skipped' => $counts[MessageRecipientModel::STATUS_SKIPPED] ?? 0,
                'failed'  => $counts[MessageRecipientModel::STATUS_FAILED] ?? 0,
            ],
        ]);

        return redirect()->to('/messages/view/' . (int) $campaign['id'])->with('success', lang('Messages.sendSuccess'));
    }

    /**
     * Campaign detail: tallies + recipient delivery rows.
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('messaging.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $campaign = $this->campaignForParish((int) $id, $parishId);

        if ($campaign === null) {
            return redirect()->to('/messages')->with('error', lang('Messages.errorNotFound'));
        }

        return view('Clergy\Views\Messages/view', array_merge($this->getCommonViewData(lang('Messages.viewTitle')), [
            'campaign'   => $campaign,
            'recipients' => $this->recipientsFor((int) $campaign['id']),
            'canSend'    => auth()->user()->can('messaging.send'),
        ]));
    }

    /**
     * Cancel a draft campaign.
     */
    public function cancel($id)
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        if (! $this->messaging->cancel((int) $id, $parishId)) {
            return redirect()->to('/messages')->with('error', lang('Messages.errorCancelFailed'));
        }

        $this->audit()->log('messaging.campaign.cancel', [
            'parish_id'   => $parishId,
            'entity_type' => 'message_campaign',
            'entity_id'   => (int) $id,
        ]);

        return redirect()->to('/messages')->with('success', lang('Messages.cancelled'));
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    /**
     * Template list.
     */
    public function templates()
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        return view('Clergy\Views\Messages\templates', array_merge($this->getCommonViewData(lang('Messages.templates')), [
            'templates' => model('MessageTemplateModel')
                ->where('parish_id', $parishId)
                ->orderBy('name', 'ASC')
                ->findAll(),
        ]));
    }

    /**
     * Template create/edit form.
     */
    public function templateForm($id = null)
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $template = null;

        if ($id !== null) {
            $template = model('MessageTemplateModel')
                ->where('id', (int) $id)
                ->where('parish_id', $parishId)
                ->first();

            if ($template === null) {
                return redirect()->to('/messages/templates')->with('error', lang('Messages.errorNotFound'));
            }
        }

        return view('Clergy\Views\Messages\template_form', array_merge($this->getCommonViewData(
            $template === null ? lang('Messages.templateCreate') : lang('Messages.templateEdit')
        ), [
            'template' => $template,
        ]));
    }

    /**
     * Persist a template (create or update).
     */
    public function templateSave($id = null)
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $templates = model('MessageTemplateModel');
        $template = null;

        if ($id !== null) {
            $template = $templates->where('id', (int) $id)->where('parish_id', $parishId)->first();
            if ($template === null) {
                return redirect()->to('/messages/templates')->with('error', lang('Messages.errorNotFound'));
            }
        }

        if (! $this->validate($this->templateRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = [
            'name'      => trim((string) $this->request->getPost('name')),
            'channel'   => (string) ($this->request->getPost('channel') ?: 'any'),
            'subject'   => trim((string) ($this->request->getPost('subject') ?? '')) ?: null,
            'body'      => trim((string) $this->request->getPost('body')),
            'is_active' => (int) $this->request->getPost('is_active') === 1 ? 1 : 0,
        ];

        if ($template === null) {
            $payload['parish_id']  = $parishId;
            $payload['created_by'] = auth()->loggedIn() ? (int) auth()->id() : null;
            $newId = $templates->insert($payload);
            $ok = $newId !== false;
        } else {
            $ok = (bool) $templates->update((int) $template['id'], $payload);
        }

        if (! $ok) {
            return redirect()->back()->withInput()->with('error', lang('Messages.errorSaveFailed'));
        }

        $this->audit()->log($template === null ? 'messaging.template.create' : 'messaging.template.update', [
            'parish_id'   => $parishId,
            'entity_type' => 'message_template',
            'entity_id'   => (int) ($template['id'] ?? $newId ?? 0),
            'new_values'  => ['name' => $payload['name']],
        ]);

        return redirect()->to('/messages/templates')->with('success', lang('App.successSaved'));
    }

    /**
     * Soft-delete a template.
     */
    public function templateDelete($id)
    {
        if ($redirect = $this->requirePermission('messaging.send')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $template = model('MessageTemplateModel')
            ->where('id', (int) $id)
            ->where('parish_id', $parishId)
            ->first();

        if ($template === null) {
            return redirect()->to('/messages/templates')->with('error', lang('Messages.errorNotFound'));
        }

        if (! model('MessageTemplateModel')->delete((int) $template['id'])) {
            return redirect()->back()->with('error', lang('Messages.errorSaveFailed'));
        }

        $this->audit()->log('messaging.template.delete', [
            'parish_id'   => $parishId,
            'entity_type' => 'message_template',
            'entity_id'   => (int) $template['id'],
            'old_values'  => ['name' => (string) ($template['name'] ?? '')],
        ]);

        return redirect()->to('/messages/templates')->with('success', lang('App.successDeleted'));
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function campaignForParish(int $id, int $parishId): ?array
    {
        $row = model('MessageCampaignModel')
            ->where('id', $id)
            ->where('parish_id', $parishId)
            ->first();

        return $row === null ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recipientsFor(int $campaignId): array
    {
        return model('MessageRecipientModel')
            ->where('campaign_id', $campaignId)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Audience descriptor from the posted form — null when nothing selected.
     *
     * @return array<string, mixed>|null
     */
    private function postedAudience(): ?array
    {
        $audience = [
            'statuses'       => array_values(array_intersect(
                array_map('strval', (array) $this->request->getPost('statuses')),
                BulkMessageService::audienceStatuses()
            )),
            'group_ids'      => array_values(array_filter(array_map('intval', (array) $this->request->getPost('group_ids')))),
            'team_ids'       => array_values(array_filter(array_map('intval', (array) $this->request->getPost('team_ids')))),
            'include_visitors' => (int) $this->request->getPost('include_visitors') === 1,
        ];

        $hasAny = $audience['statuses'] !== []
            || $audience['group_ids'] !== []
            || $audience['team_ids'] !== []
            || $audience['include_visitors'];

        return $hasAny ? $audience : null;
    }

    /**
     * @return array<string, string>
     */
    private function campaignRules(): array
    {
        return [
            'channel' => 'required|in_list[email,sms,notification]',
            'subject' => 'permit_empty|max_length[255]',
            'body'    => 'required|max_length[8000]',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function templateRules(): array
    {
        return [
            'name'    => 'required|max_length[120]',
            'channel' => 'permit_empty|in_list[email,sms,notification,any]',
            'subject' => 'permit_empty|max_length[255]',
            'body'    => 'required|max_length[8000]',
        ];
    }
}
