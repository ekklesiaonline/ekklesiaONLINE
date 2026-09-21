<?php

/**
 * Authentication strings — Spanish (Español)
 */
return [
    'login'               => 'Iniciar sesión',
    'accountInactive'     => 'Tu cuenta está inactiva. Contacta a un administrador.',
    'accountBanned'       => 'Tu cuenta no tiene permiso para acceder a este sitio. Contacta a un administrador.',
    'loginHeading'        => 'Bienvenido de nuevo',
    'loginSubheading'     => 'Inicia sesión en tu cuenta',
    'loginEmail'          => 'Correo electrónico',
    'loginPassword'       => 'Contraseña',
    'loginRemember'       => 'Recordarme',
    'loginButton'         => 'Entrar',
    'loginForgot'         => '¿Olvidaste tu contraseña?',
    'showPassword'        => 'Mostrar contraseña',
    'hidePassword'        => 'Ocultar contraseña',
    'haveAccount'         => '¿Ya tienes cuenta?',
    'noAccount'           => '¿No tienes cuenta?',

    'register'            => 'Registrarse',
    'registerHeading'     => 'Crear una cuenta',
    'registerEmail'       => 'Correo electrónico',
    'registerPassword'    => 'Contraseña',
    'registerPassConfirm' => 'Confirmar contraseña',
    'registerButton'      => 'Crear cuenta',

    'forgotPassword'      => 'Olvidé mi contraseña',
    'forgotHeading'       => 'Restablecer contraseña',
    'forgotSubheading'    => 'Ingresa tu correo y te enviaremos un enlace de restablecimiento.',
    'forgotEmail'         => 'Correo electrónico',
    'forgotButton'        => 'Enviar enlace de restablecimiento',
    'forgotSuccess'       => 'Si ese correo está registrado, se ha enviado un enlace de restablecimiento.',

    'resetPassword'       => 'Restablecer contraseña',
    'resetHeading'        => 'Establecer nueva contraseña',
    'resetNewPassword'    => 'Nueva contraseña',
    'resetConfirmPass'    => 'Confirmar nueva contraseña',
    'resetButton'         => 'Actualizar contraseña',
    'resetSuccess'        => 'Tu contraseña ha sido actualizada. Por favor, inicia sesión.',
    'setPasswordTitle'    => 'Establece tu contraseña',
    'setPasswordDescription' => 'Tu cuenta requiere una contraseña antes de que puedas continuar.',
    'setPasswordButton'   => 'Establecer contraseña y continuar',

    'verifyEmail'         => 'Verificar correo',
    'verifyHeading'       => 'Revisa tu bandeja de entrada',
    'verifySubheading'    => 'Enviamos un enlace de verificación a tu correo.',
    'verifyResend'        => 'Reenviar correo de verificación',

    'pendingTitle'             => 'En espera de aprobación',
    'pendingHeading'          => 'Cuenta pendiente de revisión',
    'pendingBody'             => 'Tu registro ha sido recibido y está siendo revisado por un administrador.',
    'pendingDiocesanHeading'  => 'Cuenta pendiente de aprobación diocesana',
    'pendingDiocesanBody'     => 'Tu correo está confirmado y tu perfil está completo. Un administrador diocesano debe revisar y asignarte un rol.',
    'pendingLogout'           => 'Cerrar sesión',

    'badAttempt'          => 'No se pudo iniciar sesión. Verifica tus credenciales.',
    'tooManyRequests'     => 'Demasiados intentos de acceso. Por favor, espera antes de intentar de nuevo.',
    'invalidToken'        => 'Token inválido o expirado.',

    // -------------------------------------------------------------------------
    // Keys mirroring Shield's vendor Auth.php (used by login.php / register.php)
    // -------------------------------------------------------------------------
    'email'               => 'Correo electrónico',
    'password'            => 'Contraseña',
    'passwordConfirm'     => 'Confirmar contraseña',
    'needAccount'         => '¿No tienes cuenta?',
    'rememberMe'          => '¿Recordarme?',
    'useMagicLink'        => 'Usar enlace de acceso',
    'successLogout'       => 'Has cerrado sesión exitosamente.',
    'backToLogin'         => 'Volver al inicio de sesión',
    'registerSuccess'     => '¡Bienvenido!',
    'registerDisabled'    => 'El registro no está disponible en este momento.',
    'registrationEmailUnavailable' => 'No se pudo completar el registro. Si este correo ya está registrado, utiliza la página de inicio de sesión.',

    // -------------------------------------------------------------------------
    // Register page — additional keys
    // -------------------------------------------------------------------------
    'registerSubheading'  => 'Regístrate con tu parroquia. Confirmarás tu correo, completarás tu perfil y luego aguardarás la revisión parroquial.',
    'chooseProvince'      => 'Elige una provincia',
    'province'            => 'Provincia',
    'chooseParish'        => 'Elige una parroquia',
    'parish'              => 'Parroquia',
    'parishSelectionRequired' => 'Selecciona una parroquia valida de la lista.',
    'interfaceLanguage'   => 'Idioma de la interfaz',
    'preferredLanguage'   => 'Idioma preferido',
    'chooseLanguage'      => 'Elige un idioma',
    'languageEnglish'     => 'Ingles',
    'languageSpanish'     => 'Espanol',
    'languageFrench'      => 'Frances',
    'verifyDeviceTitle'   => 'Verificar dispositivo',
    'verifyNewDeviceHeading' => 'Verificar nuevo dispositivo',
    'verifyCodeIntro'     => 'Enviamos un código de verificación de 6 dígitos a tu correo. Introdúcelo abajo para continuar esta sesión.',
    'verifyCodeExpired'   => 'Tu código anterior expiró. Solicita uno nuevo abajo.',
    'verifyCodeExpiresIn' => 'El código expira en aproximadamente {0} minuto(s).',
    'verificationCode'    => 'Código de verificación',
    'verifyDeviceButton'  => 'Verificar dispositivo',
    'resendCode'          => 'Reenviar código',
    'logoutLink'          => 'Cerrar sesión',

    // Device verification flow
    'verifyCodeMalformed'    => 'Introduce el código de verificación de 6 dígitos.',
    'verifyCodeExpiredRetry' => 'El código de verificación expiró. Solicita uno nuevo.',
    'verifyCodeLockedOut'    => 'Demasiados intentos fallidos. El código de verificación fue invalidado — solicita uno nuevo.',
    'verifyCodeInvalid'      => 'Código de verificación inválido. Inténtalo de nuevo.',
    'deviceVerifiedSuccess'  => 'Dispositivo verificado correctamente.',
    'verifyResendCooldown'   => 'Espera {0} segundos antes de solicitar otro código.',
    'verifyResendSent'       => 'Se envió un nuevo código de verificación a tu correo.',
    'verifyEmailFailed'      => 'No pudimos enviar el correo con el código de verificación. Usa "Reenviar código" para intentarlo de nuevo.',

    // Device verification email
    'deviceVerifyEmailSubject'    => 'Verificar inicio de sesión en nuevo dispositivo - EkklesiaRD',
    'deviceVerifyEmailIntro'      => 'Se detectó un inicio de sesión desde un nuevo dispositivo en tu cuenta de EkklesiaRD. Para continuar esta sesión, introduce este código de verificación:',
    'deviceVerifyEmailCodeExpiry' => 'Este código expira en {0} minutos.',
    'deviceVerifyEmailTime'       => 'Hora:',
    'deviceVerifyEmailIp'         => 'Dirección IP:',
    'deviceVerifyEmailDevice'     => 'Navegador / Dispositivo:',
    'deviceVerifyEmailConfirm'    => 'Si fuiste tú, completa la verificación para continuar.',
    'deviceVerifyEmailNotYou'     => 'Si no iniciaste sesión desde este dispositivo, <strong>cambia tu contraseña de inmediato</strong> y contacta al administrador de tu parroquia.',

    // Trusted devices (Security page)
    'trustedDevicesTitle'        => 'Dispositivos de confianza',
    'trustedDevicesIntro'        => 'Los dispositivos que has verificado pueden iniciar sesión sin código de correo hasta que expiren o sean revocados.',
    'noTrustedDevices'           => 'Aún no hay dispositivos de confianza. Después de verificar un nuevo dispositivo aparecerá aquí.',
    'deviceColumn'               => 'Dispositivo',
    'lastIpColumn'               => 'Última IP',
    'firstSeenColumn'            => 'Primera vez',
    'lastSeenColumn'             => 'Última vez',
    'expiresColumn'              => 'Expira',
    'thisDevice'                 => 'Este dispositivo',
    'unknownDevice'              => 'Dispositivo desconocido',
    'revokeDevice'               => 'Revocar',
    'revokeOtherDevices'         => 'Revocar todos los demás dispositivos',
    'forgetThisDevice'           => 'Olvidar este dispositivo',
    'confirmRevokeDevice'        => '¿Revocar este dispositivo de confianza? Se le pedirá verificar en el próximo inicio de sesión.',
    'confirmRevokeOtherDevices'  => '¿Revocar todos los demás dispositivos de confianza? Se les pedirá verificar en el próximo inicio de sesión.',
    'confirmForgetDevice'        => '¿Olvidar este dispositivo? Tu próximo inicio de sesión desde este navegador requerirá verificación.',
    'trustedDeviceRevoked'       => 'Dispositivo de confianza revocado.',
    'trustedDevicesRevokedCount' => '{0} dispositivo(s) de confianza revocado(s).',
    'trustedDeviceNotFound'      => 'Dispositivo de confianza no encontrado o ya revocado.',
    'trustedDeviceForgotten'     => 'Se pedirá verificar este dispositivo en tu próximo inicio de sesión.',

    // Pending-registration admin notification email
    'registrationPendingSubject' => 'Nuevo registro pendiente de revisión parroquial',
    'registrationPendingBody'    => "Un nuevo registro de usuario está pendiente de revisión.\n\nParroquia: {0}\nSolicitante: {1}\nCorreo: {2}\n\nAcción requerida: autoriza o descarta este registro en el módulo de Miembros.\nSolicitud N.º {3}\nRevisar: {4}",
    'registrationPendingUnknown' => 'Desconocida',
    'registrationPendingNoName'  => 'Nombre no proporcionado',

    // Portal access invitation email
    'portalInviteSubject'      => 'Invitación de acceso al portal - EkklesiaRD',
    'portalInviteIntro'        => 'Se creó tu invitación de acceso al portal.',
    'portalInvitePasswordNote' => 'Por seguridad, debes establecer tu contraseña antes de que se habilite el acceso completo.',
    'portalInviteGoTo'         => 'Ve a:',

    // Password-changed notification email
    'passwordChangedSubject' => 'Tu contraseña fue cambiada - EkklesiaRD',
    'passwordChangedIntro'   => 'La contraseña de tu cuenta de EkklesiaRD fue cambiada.',
    'passwordChangedAt'      => 'Hora:',
    'passwordChangedNotYou'  => 'Si no realizaste este cambio, <strong>cambia tu contraseña de inmediato</strong> y contacta al administrador de tu parroquia.',
];
