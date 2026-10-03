<?php /* Partial: status history + onboarding cards. Required: $status_history, $onboarding */ ?>
            <?php if (!empty($status_history)): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('Members.statusHistory') ?></h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($status_history as $entry): ?>
                                <?php
                                $actorName = trim((string) ($entry['actor'] ?? ''));
                                if ($actorName === '') {
                                    $actorId = (int) ($entry['changed_by'] ?? 0);
                                    $actorName = $actorId > 0 ? '#' . $actorId : lang('Members.statusHistorySystem');
                                }
                                $reasonText = trim((string) ($entry['reason'] ?? ''));
                                ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                        <div>
                                            <?php if (!empty($entry['from_status'])): ?>
                                                <span class="badge border border-secondary text-secondary fw-normal me-1"><?= esc($entry['from_status']) ?></span>
                                                <i class="bi bi-arrow-right small text-muted" aria-hidden="true"></i>
                                            <?php endif; ?>
                                            <strong><?= esc($entry['to_status'] ?? '') ?></strong>
                                            <?php
                                            $classification = (string) ($entry['classification'] ?? '');
                                            $classificationColors = [
                                                'forward'      => 'success',
                                                'backward'     => 'secondary',
                                                'reactivation' => 'info',
                                                'deactivation' => 'warning',
                                                'terminal'     => 'dark',
                                                'correction'   => 'danger',
                                            ];
                                            $classificationColor = $classificationColors[$classification] ?? '';
                                            if ($classification !== '' && $classification !== 'none' && $classificationColor !== ''):
                                            ?>
                                                <span class="badge border border-<?= $classificationColor ?> text-<?= $classificationColor ?> fw-normal ms-1"><?= esc(lang('Members.transition' . ucfirst($classification))) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($entry['created_at'])): ?>
                                            <small class="text-muted"><?= esc($entry['created_at']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1 small">
                                        <span class="text-muted"><?= esc(lang('Members.statusHistoryBy')) ?>:</span>
                                        <span><?= esc($actorName) ?></span>
                                    </div>
                                    <?php if ($reasonText !== ''): ?>
                                        <div class="mt-1 small">
                                            <span class="text-muted"><?= esc(lang('Members.statusHistoryReason')) ?>:</span>
                                            <span class="text-muted"><?= esc($reasonText) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($onboarding)): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('Members.onboarding') ?></h6>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-2 rounded small" style="white-space: pre-wrap;"><?= esc(json_encode($onboarding, JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                </div>
            <?php endif; ?>
