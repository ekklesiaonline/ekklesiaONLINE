<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$channelLabels = [
    'email'        => 'Messages.channelEmail',
    'sms'          => 'Messages.channelSms',
    'notification' => 'Messages.channelNotification',
];
$templateChannelLabels = $channelLabels + ['any' => 'Messages.channelAny'];
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('messages') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Messages.backToMessages') ?>
    </a>
</div>

<form method="post" action="<?= base_url('messages/store') ?>">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><?= lang('Messages.audience') ?></h6></div>
        <div class="card-body">
            <p class="text-muted small"><?= lang('Messages.audienceHelp') ?></p>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <span class="form-label d-block"><?= lang('Messages.audienceStatuses') ?></span>
                    <?php foreach (($statuses ?? []) as $status): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="status_<?= esc($status, 'attr') ?>" name="statuses[]" value="<?= esc($status, 'attr') ?>" <?= in_array($status, (array) old('statuses'), true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_<?= esc($status, 'attr') ?>"><?= esc(\App\Services\MembershipStatusService::label($status)) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="group_ids" class="form-label"><?= lang('Messages.audienceGroups') ?></label>
                    <select class="form-select" id="group_ids" name="group_ids[]" multiple size="6">
                        <?php foreach (($groups ?? []) as $group): ?>
                            <option value="<?= (int) $group['id'] ?>" <?= in_array((int) $group['id'], array_map('intval', (array) old('group_ids')), true) ? 'selected' : '' ?>><?= esc($group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="team_ids" class="form-label"><?= lang('Messages.audienceTeams') ?></label>
                    <select class="form-select" id="team_ids" name="team_ids[]" multiple size="6">
                        <?php foreach (($teams ?? []) as $team): ?>
                            <option value="<?= (int) $team['id'] ?>" <?= in_array((int) $team['id'], array_map('intval', (array) old('team_ids')), true) ? 'selected' : '' ?>><?= esc($team['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="include_visitors" name="include_visitors" value="1" <?= (int) old('include_visitors') === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="include_visitors"><?= lang('Messages.audienceVisitors') ?></label>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0"><?= lang('Messages.compose') ?></h6></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <span class="form-label d-block"><?= lang('Messages.channel') ?></span>
                    <?php foreach (($channels ?? []) as $channel): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="channel_<?= esc($channel, 'attr') ?>" name="channel" value="<?= esc($channel, 'attr') ?>" <?= old('channel', 'email') === $channel ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="channel_<?= esc($channel, 'attr') ?>"><?= lang($channelLabels[$channel]) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-text"><?= lang('Messages.channelSmsHelp') ?></div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="template_id" class="form-label"><?= lang('Messages.template') ?></label>
                    <select class="form-select" id="template_id">
                        <option value=""><?= lang('Messages.templateNone') ?></option>
                        <?php foreach (($templates ?? []) as $template): ?>
                            <option value="<?= (int) $template['id'] ?>"
                                    data-channel="<?= esc((string) ($template['channel'] ?? 'any'), 'attr') ?>"
                                    data-subject="<?= esc((string) ($template['subject'] ?? ''), 'attr') ?>"
                                    data-body="<?= esc((string) ($template['body'] ?? ''), 'attr') ?>">
                                <?= esc($template['name']) ?><?= ($template['channel'] ?? 'any') !== 'any' ? ' (' . lang($templateChannelLabels[$template['channel']]) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="subject" class="form-label"><?= lang('Messages.subject') ?></label>
                    <input type="text" class="form-control<?= session('errors.subject') ? ' is-invalid' : '' ?>" id="subject" name="subject" value="<?= esc(old('subject', '')) ?>" maxlength="255">
                    <div class="form-text"><?= lang('Messages.subjectOptional') ?></div>
                    <?php if (session('errors.subject')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.subject')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="body" class="form-label"><?= lang('Messages.body') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control<?= session('errors.body') ? ' is-invalid' : '' ?>" id="body" name="body" rows="7" maxlength="8000" required><?= esc(old('body', '')) ?></textarea>
                <div class="form-text"><?= lang('Messages.tokensHelp') ?></div>
                <?php if (session('errors.body')): ?>
                    <div class="invalid-feedback"><?= esc(session('errors.body')) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-outline-primary">
        <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> <?= lang('Messages.preview') ?>
    </button>
</form>

<script>
(function () {
    var picker = document.getElementById('template_id');
    if (!picker) {
        return;
    }
    picker.addEventListener('change', function () {
        var option = picker.options[picker.selectedIndex];
        if (!option || !option.value) {
            return;
        }
        var subject = document.getElementById('subject');
        var body = document.getElementById('body');
        var channel = option.getAttribute('data-channel');
        if (subject && option.getAttribute('data-subject')) {
            subject.value = option.getAttribute('data-subject');
        }
        if (body) {
            body.value = option.getAttribute('data-body') || '';
        }
        if (channel && channel !== 'any') {
            var radio = document.getElementById('channel_' + channel);
            if (radio) {
                radio.checked = true;
            }
        }
    });
})();
</script>

<?= $this->endSection() ?>
