<?php

return [
    // Member Deletion
    'memberNotFound' => 'Miembro no encontrado',
    'memberDeletedSuccess' => 'Miembro eliminado exitosamente',

    // Parish Attachment Operations
    'memberNotFoundInScope' => 'Miembro no encontrado en su alcance',
    'targetParishOutsideDiocesan' => 'La parroquia objetivo está fuera de su alcance diocesano',
    'unableAttachParish' => 'No se pudo adjuntar la parroquia',
    'unableSyncProfileCompat' => 'No se pudo sincronizar los campos de compatibilidad del perfil',
    'attachTransactionFailed' => 'Error de transacción de adjuntar',
    'parishAttachedSuccess' => 'Parroquia adjuntada exitosamente',

    // Parish Detachment Operations
    'memberMustRemainAttached' => 'El miembro debe permanecer adjunto a al menos una parroquia',
    'unableDetachParish' => 'No se pudo separar la parroquia',
    'detachTransactionFailed' => 'Error de transacción de separar',
    'parishDetachedSuccess' => 'Parroquia separada exitosamente',

    // Home Parish Operations
    'memberNotAffiliatedParish' => 'El miembro no está afiliado a la parroquia seleccionada',
    'unableSetHomeParish' => 'No se pudo establecer la parroquia principal',
    'setHomeTransactionFailed' => 'Error de transacción al establecer la principal',
    'homeParishUpdatedSuccess' => 'Parroquia principal actualizada exitosamente',

    // Access & Permission Issues
    'accessDenied' => 'Acceso denegado',
    'invalidUserOrParish' => 'Usuario o parroquia inválido',
    'provinceAdminReassignmentOnly' => 'Los administradores provinciales solo pueden reasignar personal de nivel provincial. El personal diocesano e parroquia es administrado por sus respectivos administradores diocesanos.',

    // Parish/Diocese/Province Lookup Errors
    'parishNotFound' => 'Parroquia no encontrada',
    'parishOutsideProvinceScope' => 'La parroquia seleccionada está fuera de su alcance provincial',
    'parishOutsideDioceseScope' => 'La parroquia seleccionada está fuera del alcance diocesano cargado actualmente',
    'userProfileNotFound' => 'Perfil de usuario no encontrado',

    // Transaction Errors
    'unableUpdateAffiliation' => 'No se pudo actualizar los registros de afiliación',
    'transactionFailed' => 'Error de transacción',
];
