<?php

/**
 * Authentication strings — French (Français)
 */
return [
    'login'               => 'Connexion',
    'accountInactive'     => 'Votre compte est inactif. Veuillez contacter un administrateur.',
    'accountBanned'       => 'Votre compte n\'est pas autorisé à accéder à ce site. Veuillez contacter un administrateur.',
    'loginHeading'        => 'Content de vous revoir',
    'loginSubheading'     => 'Connectez-vous à votre compte',
    'loginEmail'          => 'Adresse courriel',
    'loginPassword'       => 'Mot de passe',
    'loginRemember'       => 'Se souvenir de moi',
    'loginButton'         => 'Se connecter',
    'loginForgot'         => 'Mot de passe oublié ?',
    'showPassword'        => 'Afficher le mot de passe',
    'hidePassword'        => 'Masquer le mot de passe',
    'haveAccount'         => 'Vous avez déjà un compte ?',
    'noAccount'           => "Vous n'avez pas de compte ?",

    'register'            => "S'inscrire",
    'registerHeading'     => 'Créer un compte',
    'registerEmail'       => 'Adresse courriel',
    'registerPassword'    => 'Mot de passe',
    'registerPassConfirm' => 'Confirmer le mot de passe',
    'registerButton'      => 'Créer un compte',

    'forgotPassword'      => 'Mot de passe oublié',
    'forgotHeading'       => 'Réinitialiser votre mot de passe',
    'forgotSubheading'    => 'Saisissez votre courriel et nous vous enverrons un lien de réinitialisation.',
    'forgotEmail'         => 'Adresse courriel',
    'forgotButton'        => 'Envoyer le lien de réinitialisation',
    'forgotSuccess'       => 'Si ce courriel est enregistré, un lien de réinitialisation a été envoyé.',

    'resetPassword'       => 'Réinitialiser le mot de passe',
    'resetHeading'        => 'Définir un nouveau mot de passe',
    'resetNewPassword'    => 'Nouveau mot de passe',
    'resetConfirmPass'    => 'Confirmer le nouveau mot de passe',
    'resetButton'         => 'Mettre à jour le mot de passe',
    'resetSuccess'        => 'Votre mot de passe a été mis à jour. Veuillez vous connecter.',
    'setPasswordTitle'    => 'Définissez votre mot de passe',
    'setPasswordDescription' => 'Votre compte requiert un mot de passe avant de pouvoir continuer.',
    'setPasswordButton'   => 'Définir le mot de passe et continuer',

    'verifyEmail'         => 'Vérifier le courriel',
    'verifyHeading'       => 'Vérifiez votre boîte de réception',
    'verifySubheading'    => 'Nous avons envoyé un lien de vérification à votre adresse courriel.',
    'verifyResend'        => 'Renvoyer le courriel de vérification',

    'pendingTitle'             => "En attente d'approbation",
    'pendingHeading'          => 'Compte en attente de validation',
    'pendingBody'             => "Votre inscription a été reçue et est en attente d'approbation par un administrateur.",
    'pendingDiocesanHeading'  => "Compte en attente d'approbation diocésaine",
    'pendingDiocesanBody'     => "Votre courriel est confirmé et votre profil est complet. Un administrateur diocésain doit maintenant examiner et vous attribuer un rôle.",
    'pendingLogout'           => 'Se déconnecter',

    'badAttempt'          => 'Impossible de se connecter. Vérifiez vos identifiants.',
    'tooManyRequests'     => 'Trop de tentatives de connexion. Veuillez patienter avant de réessayer.',
    'invalidToken'        => 'Jeton invalide ou expiré.',

    // -------------------------------------------------------------------------
    // Keys mirroring Shield's vendor Auth.php (used by login.php / register.php)
    // -------------------------------------------------------------------------
    'email'               => 'Adresse courriel',
    'password'            => 'Mot de passe',
    'passwordConfirm'     => 'Confirmer le mot de passe',
    'needAccount'         => "Vous n'avez pas de compte ?",
    'rememberMe'          => 'Se souvenir de moi ?',
    'useMagicLink'        => 'Utiliser un lien de connexion',
    'successLogout'       => 'Vous avez été déconnecté avec succès.',
    'backToLogin'         => 'Retour à la connexion',
    'registerSuccess'     => 'Bienvenue !',
    'registerDisabled'    => "L'inscription n'est pas disponible pour l'instant.",
    'registrationEmailUnavailable' => "L'inscription n'a pas pu être effectuée. Si cette adresse est déjà enregistrée, utilisez la page de connexion.",

    // -------------------------------------------------------------------------
    // Register page — additional keys
    // -------------------------------------------------------------------------
    'registerSubheading'  => "Inscrivez-vous avec votre paroisse. Vous confirmerez votre courriel, compléterez votre profil, puis attendrez la validation paroissiale.",
    'chooseProvince'      => 'Choisir une province',
    'province'            => 'Province',
    'chooseParish'        => 'Choisir une paroisse',
    'parish'              => 'Paroisse',
    'parishSelectionRequired' => 'Veuillez choisir une paroisse valide dans la liste.',
    'interfaceLanguage'   => 'Langue de l interface',
    'preferredLanguage'   => 'Langue preferee',
    'chooseLanguage'      => 'Choisir une langue',
    'languageEnglish'     => 'Anglais',
    'languageSpanish'     => 'Espagnol',
    'languageFrench'      => 'Francais',
    'verifyDeviceTitle'   => 'Vérifier l\'appareil',
    'verifyNewDeviceHeading' => 'Vérifier le nouvel appareil',
    'verifyCodeIntro'     => 'Nous avons envoyé un code de vérification à 6 chiffres à votre courriel. Saisissez-le ci-dessous pour poursuivre cette session.',
    'verifyCodeExpired'   => 'Votre code précédent a expiré. Demandez un nouveau code ci-dessous.',
    'verifyCodeExpiresIn' => 'Le code expire dans environ {0} minute(s).',
    'verificationCode'    => 'Code de vérification',
    'verifyDeviceButton'  => 'Vérifier l\'appareil',
    'resendCode'          => 'Renvoyer le code',
    'logoutLink'          => 'Se déconnecter',

    // Device verification flow
    'verifyCodeMalformed'    => 'Saisissez le code de vérification à 6 chiffres.',
    'verifyCodeExpiredRetry' => 'Le code de vérification a expiré. Demandez un nouveau code.',
    'verifyCodeLockedOut'    => 'Trop de tentatives échouées. Le code de vérification a été invalidé — veuillez en demander un nouveau.',
    'verifyCodeInvalid'      => 'Code de vérification invalide. Veuillez réessayer.',
    'deviceVerifiedSuccess'  => 'Appareil vérifié avec succès.',
    'verifyResendCooldown'   => 'Veuillez attendre {0} secondes avant de demander un autre code.',
    'verifyResendSent'       => 'Un nouveau code de vérification a été envoyé à votre courriel.',
    'verifyEmailFailed'      => 'Nous n\'avons pas pu envoyer le courriel avec le code de vérification. Utilisez « Renvoyer le code » pour réessayer.',

    // Device verification email
    'deviceVerifyEmailSubject'    => 'Vérifier la connexion depuis un nouvel appareil - EkklesiaRD',
    'deviceVerifyEmailIntro'      => 'Une connexion a été détectée depuis un nouvel appareil sur votre compte EkklesiaRD. Pour poursuivre cette session, saisissez ce code de vérification :',
    'deviceVerifyEmailCodeExpiry' => 'Ce code expire dans {0} minutes.',
    'deviceVerifyEmailTime'       => 'Heure :',
    'deviceVerifyEmailIp'         => 'Adresse IP :',
    'deviceVerifyEmailDevice'     => 'Navigateur / Appareil :',
    'deviceVerifyEmailConfirm'    => 'Si c\'était vous, terminez la vérification pour continuer.',
    'deviceVerifyEmailNotYou'     => 'Si vous ne vous êtes pas connecté depuis cet appareil, veuillez <strong>changer votre mot de passe immédiatement</strong> et contacter l\'administrateur de votre paroisse.',

    // Trusted devices (Security page)
    'trustedDevicesTitle'        => 'Appareils de confiance',
    'trustedDevicesIntro'        => 'Les appareils que vous avez vérifiés peuvent se connecter sans code courriel jusqu\'à leur expiration ou révocation.',
    'noTrustedDevices'           => 'Aucun appareil de confiance pour le moment. Après la vérification d\'un nouvel appareil, il apparaîtra ici.',
    'deviceColumn'               => 'Appareil',
    'lastIpColumn'               => 'Dernière IP',
    'firstSeenColumn'            => 'Première utilisation',
    'lastSeenColumn'             => 'Dernière utilisation',
    'expiresColumn'              => 'Expiration',
    'thisDevice'                 => 'Cet appareil',
    'unknownDevice'              => 'Appareil inconnu',
    'revokeDevice'               => 'Révoquer',
    'revokeOtherDevices'         => 'Révoquer tous les autres appareils',
    'forgetThisDevice'           => 'Oublier cet appareil',
    'confirmRevokeDevice'        => 'Révoquer cet appareil de confiance ? Une vérification sera demandée à la prochaine connexion.',
    'confirmRevokeOtherDevices'  => 'Révoquer tous les autres appareils de confiance ? Une vérification sera demandée à la prochaine connexion.',
    'confirmForgetDevice'        => 'Oublier cet appareil ? Votre prochaine connexion depuis ce navigateur nécessitera une vérification.',
    'trustedDeviceRevoked'       => 'Appareil de confiance révoqué.',
    'trustedDevicesRevokedCount' => '{0} appareil(s) de confiance révoqué(s).',
    'trustedDeviceNotFound'      => 'Appareil de confiance introuvable ou déjà révoqué.',
    'trustedDeviceForgotten'     => 'Une vérification sera demandée pour cet appareil à votre prochaine connexion.',

    // Pending-registration admin notification email
    'registrationPendingSubject' => 'Nouvelle inscription en attente de validation paroissiale',
    'registrationPendingBody'    => "Une nouvelle inscription utilisateur est en attente de validation.\n\nParoisse : {0}\nDemandeur : {1}\nCourriel : {2}\n\nAction requise : autorisez ou rejetez cette inscription dans le module Membres.\nDemande n° {3}\nRéviser : {4}",
    'registrationPendingUnknown' => 'Inconnue',
    'registrationPendingNoName'  => 'Nom non fourni',

    // Portal access invitation email
    'portalInviteSubject'      => 'Invitation d’accès au portail - EkklesiaRD',
    'portalInviteIntro'        => 'Votre invitation d’accès au portail a été créée.',
    'portalInvitePasswordNote' => 'Pour des raisons de sécurité, vous devez définir votre mot de passe avant que l’accès complet ne soit activé.',
    'portalInviteGoTo'         => 'Accédez à :',

    // Password-changed notification email
    'passwordChangedSubject' => 'Votre mot de passe a été modifié - EkklesiaRD',
    'passwordChangedIntro'   => 'Le mot de passe de votre compte EkklesiaRD a été modifié.',
    'passwordChangedAt'      => 'Heure :',
    'passwordChangedNotYou'  => 'Si vous n’êtes pas à l’origine de ce changement, veuillez <strong>changer immédiatement votre mot de passe</strong> et contacter l’administrateur de votre paroisse.',
];
