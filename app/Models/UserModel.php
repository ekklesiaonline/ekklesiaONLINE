<?php

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

class UserModel extends ShieldUserModel
{
    protected $useSoftDeletes = false;

    protected $afterUpdate = ['saveEmailIdentity', 'syncMemberStandingOnActiveChange'];

    /**
     * Ensure active-flag updates trigger standing refresh even if model event payload omits changed fields.
     *
     * @param array|int|string|null $id
     * @param array|object|null $row
     */
    public function update($id = null, $row = null): bool
    {
        $shouldSyncStanding = is_array($row) && array_key_exists('active', $row);

        $result = parent::update($id, $row);

        if ($shouldSyncStanding) {
            $this->syncStandingForIds($id);
        }

        return $result;
    }

    /**
     * When a user's active flag changes, refresh their current-year standing snapshot immediately.
     */
    protected function syncMemberStandingOnActiveChange(array $data): array
    {
        $updatedData = $data['data'] ?? [];
        $id = $data['id'] ?? null;

        if (! is_array($updatedData) || (! array_key_exists('active', $updatedData) && ! empty($updatedData))) {
            return $data;
        }

        $this->syncStandingForIds($id);

        return $data;
    }

    /**
     * @param array|int|string|null $id
     */
    private function syncStandingForIds($id): void
    {
        $userIds = [];

        if (is_array($id)) {
            foreach ($id as $value) {
                $parsed = (int) $value;
                if ($parsed > 0) {
                    $userIds[] = $parsed;
                }
            }
        } elseif ($id !== null) {
            $parsed = (int) $id;
            if ($parsed > 0) {
                $userIds[] = $parsed;
            }
        }

        if ($userIds === []) {
            return;
        }

        try {
            /** @var MemberStandingSummaryModel $standingModel */
            $standingModel = model(MemberStandingSummaryModel::class);
            foreach (array_unique($userIds) as $userId) {
                $standingModel->updateSingleUserStanding($userId);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to sync standing summary after users.active change. user_ids={user_ids} error={error}', [
                'user_ids' => implode(',', $userIds),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
