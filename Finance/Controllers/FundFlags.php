<?php

namespace Finance\Controllers;

/**
 * FundFlags
 *
 * Fund lifecycle flag endpoints: lock, unlock, activate, deactivate.
 * Extracted from Funds (treasurer second pass); extends it to share
 * parish-scope lookup and audit helpers.
 */
class FundFlags extends FundExports
{

    public function lock($id)
    {
        return $this->setFlag((int) $id, 'is_locked', 1, 'fund.lock', 'Finance.fundLocked');
    }


    public function unlock($id)
    {
        return $this->setFlag((int) $id, 'is_locked', 0, 'fund.unlock', 'Finance.fundUnlocked');
    }


    public function activate($id)
    {
        return $this->setFlag((int) $id, 'is_active', 1, 'fund.activate', 'Finance.fundActivated');
    }


    public function deactivate($id)
    {
        return $this->setFlag((int) $id, 'is_active', 0, 'fund.deactivate', 'Finance.fundDeactivated');
    }


    /**
     * Toggle a single status flag on a parish fund.
     */
    private function setFlag(int $id, string $field, int $value, string $auditAction, string $successKey)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $fund     = $this->findInParish($id, $parishId);

        if ($fund === null) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundNotFound'));
        }

        if ((int) $fund[$field] !== $value) {
            if (! $this->fundModel->update($id, [$field => $value])) {
                return redirect()->to('/funds')->with('error', lang('Finance.fundSaveFailed'));
            }

            $this->auditFund($auditAction, $parishId, $id, [$field => (int) $fund[$field]], [$field => $value]);
        }

        return redirect()->to('/funds')->with('success', lang($successKey));
    }
}
