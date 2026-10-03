<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * FollowupTemplates
 *
 * Message template management for follow-up sequences: listing, save,
 * delete, and preview (raw + parsed with sample variables).
 *
 * Permissions required:
 *   Read  – visitors.view
 *   Write – visitors.edit
 */
class FollowupTemplates extends BaseController
{
    protected $templateModel;
    protected $visitorDirectory;

    public function __construct()
    {
        $this->templateModel   = model('FollowupTemplateModel');
        $this->visitorDirectory  = new \App\Services\VisitorDirectoryService();
    }

    // ------------------------------------------------------------------
    // Templates – list
    // ------------------------------------------------------------------

    public function templates()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId     = (int) $this->getOperationalParishId();
        $templatesRaw = $this->templateModel->getAllForParish($parishId);
        $templates    = [];

        // Normalize all rows to a flat associative array so the view can safely render scalar values.
        foreach ($templatesRaw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = isset($row['name']) && !is_array($row['name']) ? (string) $row['name'] : '';
            if ($name === '') {
                continue;
            }

            $subject = '';
            if (isset($row['subject']) && !is_array($row['subject'])) {
                $subject = (string) $row['subject'];
            }

            $bodyText = '';
            if (isset($row['body_text']) && !is_array($row['body_text'])) {
                $bodyText = (string) $row['body_text'];
            }

            $bodyHtml = '';
            if (isset($row['body_html']) && !is_array($row['body_html'])) {
                $bodyHtml = (string) $row['body_html'];
            }

            $variables = '';
            if (isset($row['variables'])) {
                if (is_array($row['variables'])) {
                    $encoded = json_encode($row['variables']);
                    $variables = $encoded !== false ? $encoded : '';
                } else {
                    $variables = (string) $row['variables'];
                }
            }

            $channel = isset($row['channel']) && !is_array($row['channel']) ? (string) $row['channel'] : '';
            if (!in_array($channel, ['text', 'email', 'call', 'mail', 'gift'], true)) {
                $channel = 'email';
            }

            $templates[] = [
                'id'         => isset($row['id']) ? (int) $row['id'] : 0,
                'parish_id'  => array_key_exists('parish_id', $row) && $row['parish_id'] !== null ? (int) $row['parish_id'] : null,
                'name'       => $name,
                'channel'    => $channel,
                'subject'    => $subject,
                'body_text'  => $bodyText,
                'body_html'  => $bodyHtml,
                'variables'  => $variables,
            ];
        }

        // Build step metadata from follow-up actions so templates list in workflow order.
        $usageRows = db_connect()
            ->table('followup_action fa')
            ->select('fa.template_id, MIN(fa.week_number) AS min_week, MIN(fa.day_offset) AS min_day, COUNT(*) AS usage_count')
            ->join('followup_sequence fs', 'fs.id = fa.sequence_id', 'inner')
            ->where('fa.template_id IS NOT NULL', null, false)
            ->groupStart()
            ->where('fs.parish_id', $parishId)
            ->orWhere('fs.parish_id IS NULL', null, false)
            ->groupEnd()
            ->groupBy('fa.template_id')
            ->get()
            ->getResultArray();

        $usageByTemplateId = [];
        foreach ($usageRows as $row) {
            $usageByTemplateId[(int) $row['template_id']] = [
                'min_week'    => isset($row['min_week']) ? (int) $row['min_week'] : null,
                'min_day'     => isset($row['min_day']) ? (int) $row['min_day'] : null,
                'usage_count' => (int) ($row['usage_count'] ?? 0),
            ];
        }

        foreach ($templates as &$tpl) {
            $templateId = (int) ($tpl['id'] ?? 0);
            $usage      = $usageByTemplateId[$templateId] ?? null;

            $stepWeek = null;
            $stepDay  = null;

            if (is_array($usage)) {
                $stepWeek = $usage['min_week'];
                $stepDay  = $usage['min_day'];
            } elseif (preg_match('/\bweek\s*(\d+)\b/i', (string) ($tpl['name'] ?? ''), $m)) {
                $stepWeek = (int) $m[1];
            }

            $tpl['step_week']  = $stepWeek;
            $tpl['step_day']   = $stepDay;
            $tpl['step_label'] = ($stepWeek !== null)
                ? ($stepDay !== null ? ('W' . $stepWeek . ' / D+' . $stepDay) : ('W' . $stepWeek))
                : '—';

            // Sort priority: templates used in actions first, then by day offset/week, then channel+name.
            $sortBase = $stepDay !== null
                ? $stepDay
                : ($stepWeek !== null ? ($stepWeek * 7) : 9999);
            $tpl['step_sort_key'] = $sortBase;
            $tpl['step_has_usage'] = is_array($usage) ? 1 : 0;
        }
        unset($tpl);

        usort($templates, static function (array $a, array $b): int {
            $usageCmp = ($b['step_has_usage'] <=> $a['step_has_usage']);
            if ($usageCmp !== 0) {
                return $usageCmp;
            }

            $stepCmp = ((int) $a['step_sort_key'] <=> (int) $b['step_sort_key']);
            if ($stepCmp !== 0) {
                return $stepCmp;
            }

            $channelCmp = strcmp((string) ($a['channel'] ?? ''), (string) ($b['channel'] ?? ''));
            if ($channelCmp !== 0) {
                return $channelCmp;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        // Recent visitors for the live-preview visitor selector (50 most recent)
        $visitorsForPreview = $this->visitorDirectory->pickerRowsForParish($parishId, 50);

        return view('Clergy\Views\FollowupWorkflow/templates', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitleTemplates')),
            [
                'templates'        => $templates,
                'parishId'         => $parishId,
                'visitorsForPreview' => $visitorsForPreview,
            ]
        ));
    }


    // ------------------------------------------------------------------
    // Templates – save (AJAX POST, create or update)
    // ------------------------------------------------------------------

    /**
     * @param int $id  0 = create
     */
    public function saveTemplate(int $id = 0)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();

        // Upstream templates (parish_id = NULL: global/diocesan/provincial)
        // are read-only at parish level.
        if ($id > 0) {
            $existing = $this->templateModel->find($id);
            if ($existing && $existing['parish_id'] === null) {
                return $this->jsonResponse(
                    ['success' => false, 'message' => 'Upstream templates cannot be edited here.'],
                    403
                );
            }
            if ($existing && (int) $existing['parish_id'] !== $parishId) {
                return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
            }
        }

        $rules = [
            'channel'   => 'required|in_list[text,email,call,mail,gift]',
            'name'      => 'required|min_length[3]|max_length[150]',
            'body_text' => 'required',
            'subject'   => 'permit_empty|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return $this->jsonResponse(['success' => false, 'errors' => $this->validator->getErrors()], 422);
        }

        $variablesRaw = $this->request->getPost('variables');
        if (is_string($variablesRaw) && $variablesRaw !== '') {
            $decoded = json_decode($variablesRaw, true);
            if (!is_array($decoded)) {
                $tags         = array_filter(array_map('trim', explode(',', $variablesRaw)));
                $variablesRaw = json_encode(array_values($tags));
            } else {
                $variablesRaw = json_encode($decoded);
            }
        } else {
            $variablesRaw = null;
        }

        $data = [
            'parish_id' => $parishId,
            'channel'   => $this->request->getPost('channel'),
            'name'      => $this->request->getPost('name'),
            'subject'   => $this->request->getPost('subject') ?: null,
            'body_text' => $this->request->getPost('body_text'),
            'body_html' => $this->request->getPost('body_html') ?: null,
            'variables' => $variablesRaw,
        ];

        if ($id > 0) {
            // Parish edits to a cloned template detach it from propagation.
            if (!empty($existing['source_template_id'])) {
                $data['is_customized'] = 1;
            }
            $this->templateModel->update($id, $data);
            $event = 'followup_template.updated';
        } else {
            $id    = (int) $this->templateModel->insert($data);
            $event = 'followup_template.created';
        }

        $this->audit()->log($event, [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_template',
            'entity_id'   => $id,
        ]);

        // Warn about unrecognised tokens (template is saved regardless).
        $knownTokens = [
            '{first_name}',
            '{last_name}',
            '{parish_name}',
            '{visit_date}',
            '{clergy_name}',
            '{phone}',
            '{next_event_title}',
            '{next_event_date}',
            '{clergy_email}',
            '{parish_address}',
            '{unsubscribe_url}',
        ];
        $allContent = ($data['body_text'] ?? '') . ' ' . ($data['body_html'] ?? '') . ' ' . ($data['subject'] ?? '');
        preg_match_all('/\{[^}]+\}/', $allContent, $matches);
        $unknownTokens = array_values(array_diff(array_unique($matches[0] ?? []), $knownTokens));

        return $this->jsonResponse([
            'success'       => true,
            'id'            => $id,
            'unknown_tokens' => $unknownTokens,
        ]);
    }


    // ------------------------------------------------------------------
    // Templates – delete (AJAX POST)
    // ------------------------------------------------------------------

    public function deleteTemplate(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $template = $this->templateModel->find($id);

        if (!$template) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        if ($template['parish_id'] === null || (int) $template['parish_id'] !== $parishId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $this->templateModel->delete($id);

        $this->audit()->log('followup_template.deleted', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_template',
            'entity_id'   => $id,
        ]);

        return $this->jsonResponse(['success' => true]);
    }


    // ------------------------------------------------------------------
    // Templates – preview
    // ------------------------------------------------------------------

    /**
     * Render a template substituted with sample values so staff can
     * verify formatting before assigning it to a workflow action.
     */
    public function preview_template(int $id)
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        $template = $this->templateModel->find($id);

        if (!$template) {
            return redirect()->to('followup-workflow/templates')->with('error', 'Template not found.');
        }

        $parishId = (int) $this->getOperationalParishId();
        $parish   = db_connect()->table('parish')->where('id', $parishId)->get()->getRowArray();

        $sampleVars = [
            '{first_name}'  => 'Maria',
            '{last_name}'   => 'García',
            '{parish_name}' => $parish['name'] ?? 'Grace Parish',
            '{visit_date}'  => date('F j, Y'),
            '{clergy_name}' => 'Clergy Juan',
            '{phone}'       => '(555) 000-0000',
        ];

        // Decode stored variables JSON to show available tags in the UI
        $definedVars = [];
        if (!empty($template['variables'])) {
            $decoded = json_decode($template['variables'], true);
            if (is_array($decoded)) {
                $definedVars = $decoded;
            }
        }

        $preview = [
            'subject'   => $this->applyTemplateVars((string) ($template['subject'] ?? ''), $sampleVars),
            'body_text' => $this->applyTemplateVars((string) ($template['body_text'] ?? ''), $sampleVars),
            'body_html' => $this->applyTemplateVars((string) ($template['body_html'] ?? ''), $sampleVars),
        ];

        return view('Clergy\Views\FollowupWorkflow/preview_template', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitlePreviewTemplate')),
            [
                'template'    => $template,
                'preview'     => $preview,
                'sampleVars'  => $sampleVars,
                'definedVars' => $definedVars,
            ]
        ));
    }


    // ------------------------------------------------------------------
    // Preview parsed template (AJAX POST)
    // ------------------------------------------------------------------

    /**
     * Return a parsed preview of a template body using a real visitor's data.
     *
     * POST body:
     *   template_id  – (int) ID of the template to preview.
     *   visitor_id     – (int|optional) Visitor to use for variable substitution.
     *                  If omitted or invalid, falls back to hardcoded sample data.
     *
     * Returns JSON:
     *   { success: true, subject: '…', body_text: '…', body_html: '…' }
     */
    public function previewParsed()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if (! $this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'AJAX only.'], 400);
        }

        $templateId = (int) $this->request->getPost('template_id');
        $visitorId    = (int) $this->request->getPost('visitor_id');
        $parishId   = (int) $this->getOperationalParishId();

        // Load template and guard ownership
        $template = $this->templateModel->find($templateId);
        if (! $template) {
            return $this->jsonResponse(['success' => false, 'message' => 'Template not found.'], 404);
        }

        $tplParishId = (int) ($template['parish_id'] ?? 0);
        if ($tplParishId !== 0 && $tplParishId !== $parishId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Load the parish row
        $parish = db_connect()->table('parish')->where('id', $parishId)->get()->getRowArray() ?: null;

        // Load parish settings as a flat key→value map
        $settingModel    = model('ParishSettingModel');
        $settingsRows    = $settingModel->getParishSettings($parishId);   // rows with key, value
        $parishSettings  = [];
        foreach ($settingsRows as $row) {
            $parishSettings[(string) $row['key']] = (string) ($row['value'] ?? '');
        }

        // Load and decrypt visitor, or fall back to sample data
        $visitor = null;
        if ($visitorId > 0) {
            $visitor = $this->visitorDirectory->findByPerson($visitorId, $parishId);
        }

        if ($visitor === null) {
            $visitor = [
                'first_name'  => 'María',
                'last_name'   => 'García',
                'visit_date'  => date('Y-m-d'),
                'phone'       => '(555) 000-0000',
                'interests'   => '{"children":true,"music":true}',
                'parish_id'   => $parishId,
            ];
        }

        // Find next upcoming event for this parish
        $nextEvent = null;
        $today     = date('Y-m-d H:i:s');
        $nextEvent = db_connect()
            ->table('event')
            ->where('parish_id', $parishId)
            ->where('status', 'published')
            ->where('start_at >=', $today)
            ->orderBy('start_at', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray() ?: null;

        // Apply token substitution via the helper
        helper('followup_template');
        $vars = parse_template_vars($visitor, $parishSettings, $parish, $nextEvent);

        return $this->jsonResponse([
            'success'   => true,
            'subject'   => str_replace(array_keys($vars), array_values($vars), (string) ($template['subject']   ?? '')),
            'body_text' => str_replace(array_keys($vars), array_values($vars), (string) ($template['body_text'] ?? '')),
            'body_html' => str_replace(array_keys($vars), array_values($vars), (string) ($template['body_html'] ?? '')),
        ]);
    }


    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Replace {placeholder} tokens in a template string with sample values.
     */
    private function applyTemplateVars(string $text, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $text);
    }
}
