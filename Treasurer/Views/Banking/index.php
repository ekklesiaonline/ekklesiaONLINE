<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <?php if (!empty($canManage)): ?>
        <a href="<?= base_url('banking/create') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Finance.bankAccountCreate') ?>
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($accounts)): ?>
            <p class="text-muted mb-0"><?= lang('Finance.bankNoAccounts') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Finance.bankAccountName') ?></th>
                            <th scope="col"><?= lang('Finance.bankInstitution') ?></th>
                            <th scope="col"><?= lang('Finance.bankAccountNumber') ?></th>
                            <th scope="col"><?= lang('Finance.bankCurrency') ?></th>
                            <th scope="col" class="text-end"><?= lang('Finance.bankOpeningBalance') ?></th>
                            <th scope="col" class="text-end"><?= lang('Finance.bankBookBalance') ?></th>
                            <th scope="col"><?= lang('Finance.bankTransactions') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <?php
                            $bookBalance = (float) ($account['opening_balance'] ?? 0) + (float) ($account['txn_sum'] ?? 0);
                            $isActive = (int) ($account['is_active'] ?? 0) === 1;
                            $statusColor = $isActive ? 'success' : 'secondary';
                            ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= esc($account['name']) ?></th>
                                <td><?= esc($account['institution_name'] ?? '—') ?></td>
                                <td><?= esc($account['masked_account_number'] ?? '—') ?></td>
                                <td><?= esc($account['currency_code']) ?></td>
                                <td class="text-end"><?= number_format((float) $account['opening_balance'], 2) ?></td>
                                <td class="text-end"><?= number_format($bookBalance, 2) ?></td>
                                <td><?= (int) ($account['txn_count'] ?? 0) ?></td>
                                <td><span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang($isActive ? 'Finance.bankStatusActive' : 'Finance.bankStatusInactive') ?></span></td>
                                <td class="text-end">
                                    <a href="<?= base_url('banking/' . (int) $account['id']) ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-eye" aria-hidden="true"></i> <?= lang('App.view') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
