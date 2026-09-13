<?php

return [
    // Member Deletion
    'memberNotFound' => 'Member not found',
    'memberDeletedSuccess' => 'Member deleted successfully',

    // Parish Attachment Operations
    'memberNotFoundInScope' => 'Member not found in your scope',
    'targetParishOutsideDiocesan' => 'Target parish is outside your diocesan scope',
    'unableAttachParish' => 'Unable to attach parish',
    'unableSyncProfileCompat' => 'Unable to sync profile compatibility fields',
    'attachTransactionFailed' => 'Attach transaction failed',
    'parishAttachedSuccess' => 'Parish attached successfully',

    // Parish Detachment Operations
    'memberMustRemainAttached' => 'Member must remain attached to at least one parish',
    'unableDetachParish' => 'Unable to detach parish',
    'detachTransactionFailed' => 'Detach transaction failed',
    'parishDetachedSuccess' => 'Parish detached successfully',

    // Home Parish Operations
    'memberNotAffiliatedParish' => 'Member is not affiliated with selected parish',
    'unableSetHomeParish' => 'Unable to set home parish',
    'setHomeTransactionFailed' => 'Set-home transaction failed',
    'homeParishUpdatedSuccess' => 'Home parish updated successfully',

    // Access & Permission Issues
    'accessDenied' => 'Access denied',
    'invalidUserOrChurch' => 'Invalid user or parish',
    'invalidUserOrParish' => 'Invalid user or parish',
    'provinceAdminReassignmentOnly' => 'Province admins may only reassign province-level staff. Diocesan and parish staff are managed by their respective diocese administrators.',

    // Church/Diocese/Province Lookup Errors
    'churchNotFound' => 'Parish not found',
    'parishNotFound' => 'Parish not found',
    'churchOutsideProvinceScope' => 'Selected parish is outside your province scope',
    'parishOutsideProvinceScope' => 'Selected parish is outside your province scope',
    'churchOutsideDioceseScope' => 'Selected parish is outside the currently loaded diocese scope',
    'parishOutsideDioceseScope' => 'Selected parish is outside the currently loaded diocese scope',
    'userProfileNotFound' => 'User profile not found',

    // Transaction Errors
    'unableUpdateAffiliation' => 'Unable to update affiliation records',
    'transactionFailed' => 'Transaction failed',
];
