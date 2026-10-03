<?php /* Partial: left column — profile card + contact + address. Required: $profile, $person */ ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <?php
                    $profileUserId = (int) ($profile['user_id'] ?? 0);
                    $profilePicturePath = trim((string) ($profile['profile_picture'] ?? ''));
                    $profilePictureUrl = ($profilePicturePath !== '' && $profileUserId > 0)
                        ? base_url('media/profile-picture/' . $profileUserId)
                        : '';
                    ?>
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img src="<?= esc($profilePictureUrl, 'attr') ?>" alt="" class="people-profile-avatar mb-3">
                    <?php else: ?>
                        <div class="people-profile-avatar-fallback mb-3">
                            <i class="bi bi-person people-profile-avatar-fallback-icon" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>

                    <h5 class="card-title mb-1"><?= esc(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h5>
                    <?php if (!empty($profile['nickname'])): ?>
                        <p class="text-muted mb-2">"<?= esc($profile['nickname']) ?>"</p>
                    <?php endif; ?>

                    <?php
                    $statusValue = strtolower(trim((string) ($person['membership_status'] ?? 'visitor')));
                    $statusLabel = \App\Services\MembershipStatusService::label($statusValue);
                    $statusColor = \App\Services\MembershipStatusService::badgeColor($statusValue);
                    ?>
                    <span class="badge bg-<?= esc($statusColor) ?> mb-2">
                        <?= esc($statusLabel) ?>
                    </span>
                    <?php if ((int) ($person['is_member'] ?? 0) === 0): ?>
                        <span class="badge bg-light text-dark border"><?= lang('People.notAMember') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><?= lang('Members.contactInfo') ?></h6>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong><?= lang('Members.email') ?>:</strong><br>
                        <?= esc($person['email'] ?? $profile['email'] ?? lang('Members.notProvided')) ?></p>
                    <p class="mb-1"><strong><?= lang('Members.phone') ?>:</strong><br>
                        <?= esc((!empty($profile['phone']) ? \App\Libraries\PhoneHelper::display($profile['phone']) : lang('Members.notProvided'))) ?></p>
                    <?php if (!empty($profile['birth_date'])): ?>
                        <p class="mb-1"><strong><?= lang('Members.birthDate') ?>:</strong><br>
                            <?= esc($profile['birth_date']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($profile['address'])): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('Members.address') ?></h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $countryOptions = \App\Libraries\AddressHelper::countryOptions();
                        $countryName = $countryOptions[$profile['country'] ?? ''] ?? ($profile['country'] ?? '');
                        ?>
                        <?= esc($profile['address'] ?: '') ?><br>
                        <?php if (!empty($profile['address_line_2'])): ?>
                            <?= esc($profile['address_line_2']) ?><br>
                        <?php endif; ?>
                        <?= esc(($profile['city'] ?? '') . ', ' . ($profile['state'] ?? '') . ' ' . ($profile['zip'] ?? '')) ?><br>
                        <?= esc($countryName) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
