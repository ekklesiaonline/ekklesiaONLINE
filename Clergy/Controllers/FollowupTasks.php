<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * FollowupTasks
 *
 * Manual trigger dispatch and the call-task queue: list, complete.
 * Extracted from FollowupWorkflow (phase 2 split); routes and behavior
 * are unchanged.
 *
 * Permissions required:
 *   Read  – visitors.view
 *   Write – visitors.edit
 */
class FollowupTasks extends BaseController
{
    protected $actionModel;
    protected $logModel;
    protected $templateModel;
    protected $visitorDirectory;

    public function __construct()
    {
        $this->actionModel     = model('FollowupActionModel');
        $this->logModel        = model('FollowupLogModel');
        $this->templateModel   = model('FollowupTemplateModel');
        $this->visitorDirectory  = new \App\Services\VisitorDirectoryService();
    }

    // ------------------------------------------------------------------
    // Manual trigger (AJAX POST)
    // ------------------------------------------------------------------

    /**
     * Immediately dispatch a follow-up action for a visitor, bypassing
     * the scheduled execution date.
     *
     * If a pending log entry exists for the (visitor, action) pair, it is
     * marked executed. Otherwise a new log row is inserted in the
     * 'sent' state so the timeline reflects the manual send.
     */
    public function manual_trigger(int $visitorId, int $actionId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $visitor    = $this->visitorDirectory->findByPerson($visitorId, $parishId);

        if (!$visitor) {
            return $this->jsonResponse(['success' => false, 'message' => 'Visitor not found or forbidden.'], 404);
        }

        $action = $this->actionModel->find($actionId);
        if (!$action) {
            return $this->jsonResponse(['success' => false, 'message' => 'Action not found.'], 404);
        }

        $currentUserId = (int) auth()->id();

        // Prefer dispatching an existing pending row over creating a duplicate.
        $logEntry = $this->logModel
            ->where('person_id', $visitorId)
            ->where('parish_id', $parishId)
            ->where('action_id', $actionId)
            ->where('status', 'pending')
            ->first();

        $humanChannel = in_array((string) $action['channel'], \App\Services\CallTaskSyncService::HUMAN_CHANNELS, true);
        if (!$logEntry) {
            $logEntry = [
                'person_id'      => $visitorId,
                'parish_id'      => $parishId,
                'action_id'      => $actionId,
                'assigned_to'    => (int) ($action['assigned_to'] ?? 0) ?: $currentUserId,
                'scheduled_date' => date('Y-m-d'),
                'status'         => 'pending',
            ];

            if ($humanChannel) {
                $logId = $this->logModel->insert($logEntry, true);
                if (!is_int($logId) || $logId <= 0) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Failed to create follow-up task.'], 500);
                }
                $logEntry['id'] = $logId;
            } else {
                $logEntry['id']           = null;
                $logEntry['channel']      = $action['channel'];
                $logEntry['template_id']  = $action['template_id'] ?? null;
                $logEntry['instructions'] = $action['instructions'] ?? null;
            }
        }
        $logEntry['channel']      = $action['channel'];
        $logEntry['template_id']  = $action['template_id'] ?? null;
        $logEntry['instructions'] = $action['instructions'] ?? null;

        $template = null;
        if (!empty($logEntry['template_id'])) {
            $template = $this->templateModel->find((int) $logEntry['template_id']);
        }

        $result = (new \App\Services\FollowupDispatchService())->dispatch($logEntry, is_array($template) ? $template : null, $visitor);

        if (!$result['success'] && empty($result['metadata']['skipped'])) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Dispatch failed: ' . ($result['error'] ?? 'Unknown error'),
            ], 500);
        }

        if ($logEntry['id'] !== null) {
            $logId = (int) $logEntry['id'];
            if (!empty($result['metadata']['skipped'])) {
                $this->logModel->markSkipped($logId, array_merge(
                    $result['metadata'],
                    ['triggered_by' => $currentUserId, 'manual' => true]
                ));
            } elseif ($humanChannel) {
                $this->logModel->update($logId, [
                    'response_metadata' => json_encode(array_merge(
                        $result['metadata'],
                        ['triggered_by' => $currentUserId, 'manual' => true]
                    )),
                ]);
            } else {
                $this->logModel->markExecuted($logId, array_merge(
                    $result['metadata'],
                    ['triggered_by' => $currentUserId, 'manual' => true]
                ));
            }
        } else {
            // Create a log row reflecting the manual dispatch.
            $logId = (int) $this->logModel->insert([
                'person_id'         => $visitorId,
                'parish_id'         => $parishId,
                'action_id'         => $actionId,
                'assigned_to'       => $currentUserId,
                'scheduled_date'    => date('Y-m-d'),
                'executed_at'       => date('Y-m-d H:i:s'),
                'status'            => !empty($result['metadata']['skipped']) ? 'skipped' : 'sent',
                'response_metadata' => json_encode(array_merge(
                    $result['metadata'],
                    ['triggered_by' => $currentUserId, 'manual' => true]
                )),
            ]);
        }

        $this->audit()->log('followup.manual_trigger', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'meta'        => [
                'visitor_id'  => $visitorId,
                'action_id' => $actionId,
                'channel'   => $action['channel'],
            ],
        ]);

        return $this->jsonResponse([
            'success' => true,
            'log_id'  => $logId,
            'channel' => $action['channel'],
        ]);
    }


    // ------------------------------------------------------------------
    // Call tasks
    // ------------------------------------------------------------------

    /**
     * Display pending (and recent) call tasks for the operational parish.
     * Accepts ?status= filter (pending|completed|cancelled|all).
     */
    public function callTasks()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();
        $status   = $this->request->getGet('status') ?? 'pending';
        $status   = in_array($status, ['pending', 'completed', 'cancelled', 'all'], true) ? $status : 'pending';
        $taskType = $this->request->getGet('type');
        $taskType = in_array($taskType, ['call', 'manual'], true) ? $taskType : null;

        /** @var \App\Models\CallTaskModel $model */
        $model = model('CallTaskModel');

        if ($status === 'pending') {
            $tasks = $model->getPendingForParish($parishId, $taskType);
        } elseif ($status === 'all') {
            $tasks = $model->getAllForParish($parishId, 100, $taskType);
        } else {
            // Status filtered in SQL — filtering after LIMIT would hide
            // recent completions once the parish passes 100 tasks.
            $tasks = $model->getByStatusForParish($parishId, $status, 100, $taskType);
        }

        return view('Clergy\Views\FollowupWorkflow/call_tasks', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitleCallTasks')),
            [
                'tasks'          => $tasks,
                'activeStatus'   => $status,
                'activeType'     => $taskType,
                'pendingCount'   => $model->countPending($parishId),
            ]
        ));
    }


    /**
     * AJAX POST — mark a call task as completed.
     * Body: { notes?: string }
     */
    public function completeCallTask(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'AJAX only.'], 400);
        }

        /** @var \App\Models\CallTaskModel $model */
        $model = model('CallTaskModel');
        $task  = $model->find($id);

        if (!$task) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.taskNotFound')], 404);
        }

        // Same scope contract as Visitors::logMarkDone — the task must
        // belong to the operational parish and its person must still be in
        // the visitor domain there. person_id falls back to the linked log
        // for drifted rows; log_id is always set per the follow-up schema.
        $parishId  = $this->getOperationalParishId();
        $personId  = (int) ($task['person_id'] ?? 0);
        if ($personId <= 0 && !empty($task['log_id'])) {
            $logRow   = $this->logModel->find((int) $task['log_id']);
            $personId = (int) ($logRow['person_id'] ?? 0);
        }
        if ((int) $task['parish_id'] !== $parishId
            || ! $this->visitorDirectory->visitorAffiliationExists($personId, $parishId)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 403);
        }

        $notes = (string) ($this->request->getPost('notes') ?? '');
        if ($notes !== '') {
            $model->update($id, ['notes' => $notes]);
        }

        if (!$model->markCompleted($id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Update failed.'], 500);
        }

        // Resolve the linked log when the task is closed ahead of dispatch
        // ('pending') or completed on a retry ('failed') — the call is done,
        // no cron run is needed to reflect it in the follow-up timeline.
        $logId = (int) ($task['log_id'] ?? 0);
        if ($logId > 0) {
            $log = $this->logModel->find($logId);
            if (is_array($log) && in_array($log['status'] ?? '', ['pending', 'failed'], true)) {
                $this->logModel->markExecuted($logId, ['completed_via' => 'call_task']);
            }
        }

        $this->audit()->log('followup.call_task_completed', ['call_task_id' => $id]);

        return $this->jsonResponse(['success' => true, 'task_id' => $id]);
    }
}
