<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') { return '-'; }
    $timestamp = strtotime($value);
    if ($timestamp === false) { return (string) $value; }
    return date('M d, Y', $timestamp);
};

$motions = $meeting['motions'] ?? [];
$typeKey = 'vestryMeetingType' . ucfirst($meeting['meeting_type'] ?? 'regular');
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><?= esc($formatLocalizedDate($meeting['meeting_date'] ?? '')) ?></h4>
        <span class="badge bg-secondary"><?= lang('Dashboard.' . $typeKey) ?></span>
    </div>
    <div class="card-body">
        <?php if (!empty($meeting['minutes_text'])): ?>
            <h6>Minutes</h6>
            <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;"><?= esc($meeting['minutes_text']) ?></div>
        <?php else: ?>
            <p class="text-muted small">No minutes recorded.</p>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5><?= lang('Dashboard.vestryMotions') ?></h5>
    <?php if (!empty($can_manage)): ?>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newMotionModal">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> New Motion
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($motions)): ?>
<?php foreach ($motions as $motion): ?>
    <?php
    $status = $motion['status'] ?? 'open';
    $statusColor = match ($status) {
        'passed' => 'success',
        'failed' => 'danger',
        'tabled' => 'warning',
        default => 'info',
    };
    $tally = $motion['vote_tally'] ?? ['yea' => 0, 'nay' => 0, 'abstain' => 0, 'total' => 0];
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><?= esc($motion['title'] ?? '') ?></h6>
            <span class="badge bg-<?= $statusColor ?>"><?= lang('Dashboard.motionStatus' . ucfirst($status)) ?></span>
        </div>
        <div class="card-body">
            <?php if (!empty($motion['body_text'])): ?>
                <p class="small text-muted"><?= esc($motion['body_text']) ?></p>
            <?php endif; ?>

            <div class="row text-center g-2 mb-3">
                <div class="col-3">
                    <span class="badge bg-success"><?= lang('Dashboard.voteYea') ?>: <?= (int) ($tally['yea'] ?? 0) ?></span>
                </div>
                <div class="col-3">
                    <span class="badge bg-danger"><?= lang('Dashboard.voteNay') ?>: <?= (int) ($tally['nay'] ?? 0) ?></span>
                </div>
                <div class="col-3">
                    <span class="badge bg-secondary"><?= lang('Dashboard.voteAbstain') ?>: <?= (int) ($tally['abstain'] ?? 0) ?></span>
                </div>
                <div class="col-3">
                    <small class="text-muted">Total: <?= (int) ($tally['total'] ?? 0) ?></small>
                </div>
            </div>

            <?php if ($status === 'open' && !empty($can_vote)): ?>
            <div class="d-flex gap-2">
                <form action="<?= base_url('vestry/motion/' . (int) ($motion['id'] ?? 0) . '/vote') ?>" method="post" class="d-inline vote-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vote" value="yea">
                    <button type="submit" class="btn btn-sm btn-success"><?= lang('Dashboard.voteYea') ?></button>
                </form>
                <form action="<?= base_url('vestry/motion/' . (int) ($motion['id'] ?? 0) . '/vote') ?>" method="post" class="d-inline vote-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vote" value="nay">
                    <button type="submit" class="btn btn-sm btn-danger"><?= lang('Dashboard.voteNay') ?></button>
                </form>
                <form action="<?= base_url('vestry/motion/' . (int) ($motion['id'] ?? 0) . '/vote') ?>" method="post" class="d-inline vote-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vote" value="abstain">
                    <button type="submit" class="btn btn-sm btn-secondary"><?= lang('Dashboard.voteAbstain') ?></button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($status === 'open' && !empty($can_manage)): ?>
            <div class="mt-2">
                <a href="<?= base_url('vestry/motion/' . (int) ($motion['id'] ?? 0) . '/close') ?>" class="btn btn-sm btn-outline-warning">Close Motion</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php else: ?>
<p class="text-muted">No motions recorded for this meeting.</p>
<?php endif; ?>

<!-- New Motion Modal -->
<?php if (!empty($can_manage)): ?>
<div class="modal fade" id="newMotionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('vestry/meeting/' . (int) ($meeting['id'] ?? 0) . '/motion') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">New Motion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Body</label>
                        <textarea name="body_text" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Motion</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<a href="<?= base_url('/parish/governance') ?>" class="btn btn-outline-secondary mt-3">
    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Governance
</a>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.querySelectorAll('.vote-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                credentials: 'same-origin',
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.message || 'Vote failed');
                }
            })
            .catch(() => alert('Network error'));
        });
    });
</script>
<?= $this->endSection() ?>
