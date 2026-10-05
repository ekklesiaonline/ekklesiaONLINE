<?php

/**
 * Clergy invitation email.
 * Required: $token (string), $user (Shield user entity).
 */
$verifyUrl = url_to('verify-magic-link') . '?token=' . $token;
?>
<p><?= esc(lang('Clergy.invitationGreeting')) ?></p>
<p><?= esc(lang('Clergy.invitationBody')) ?></p>
<p><a href="<?= esc($verifyUrl, 'attr') ?>"><?= esc(lang('Clergy.invitationAction')) ?></a></p>
<p><?= esc(lang('Clergy.invitationExpiry')) ?></p>
