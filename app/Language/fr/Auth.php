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
];
