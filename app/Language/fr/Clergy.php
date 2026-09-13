<?php

return [
    // Member Deletion
    'memberNotFound' => 'Membre non trouvé',
    'memberDeletedSuccess' => 'Membre supprimé avec succès',

    // Parish Attachment Operations
    'memberNotFoundInScope' => 'Membre non trouvé dans votre portée',
    'targetParishOutsideDiocesan' => 'La paroisse cible est en dehors de votre portée diocésaine',
    'unableAttachParish' => 'Impossible d\'attacher la paroisse',
    'unableSyncProfileCompat' => 'Impossible de synchroniser les champs de compatibilité du profil',
    'attachTransactionFailed' => 'Échec de la transaction d\'attachement',
    'parishAttachedSuccess' => 'Paroisse attachée avec succès',

    // Parish Detachment Operations
    'memberMustRemainAttached' => 'Le membre doit rester attaché à au moins une paroisse',
    'unableDetachParish' => 'Impossible de détacher la paroisse',
    'detachTransactionFailed' => 'Échec de la transaction de détachement',
    'parishDetachedSuccess' => 'Paroisse détachée avec succès',

    // Home Parish Operations
    'memberNotAffiliatedParish' => 'Le membre n\'est pas affilié à la paroisse sélectionnée',
    'unableSetHomeParish' => 'Impossible de définir la paroisse d\'accueil',
    'setHomeTransactionFailed' => 'Échec de la transaction de définition de la maison',
    'homeParishUpdatedSuccess' => 'Paroisse d\'accueil mise à jour avec succès',

    // Access & Permission Issues
    'accessDenied' => 'Accès refusé',
    'invalidUserOrChurch' => 'Utilisateur ou paroisse invalide',
    'provinceAdminReassignmentOnly' => 'Les administrateurs provinciaux ne peuvent réassigner que le personnel de niveau provincial. Le personnel diocésain et de la paroisse est géré par leurs administrateurs diocésains respectifs.',

    // Church/Diocese/Province Lookup Errors
    'churchNotFound' => 'Paroisse non trouvée',
    'churchOutsideProvinceScope' => 'La paroisse sélectionnée est en dehors de votre portée provinciale',
    'churchOutsideDioceseScope' => 'La paroisse sélectionnée est en dehors de la portée diocésaine actuellement chargée',
    'userProfileNotFound' => 'Profil utilisateur non trouvé',

    // Transaction Errors
    'unableUpdateAffiliation' => 'Impossible de mettre à jour les dossiers d\'affiliation',
    'transactionFailed' => 'Échec de la transaction',
];
