<?php

/**
 * Authentication strings — English
 *
 * Overrides / extends Shield's vendor/codeigniter4/shield/src/Language/en/Auth.php.
 * CI4 merges app-level files on top of vendor files — only define keys you need to
 * add or override.
 */
return [
    // -------------------------------------------------------------------------
    // Login page
    // -------------------------------------------------------------------------
    'login'               => 'Login',
    'accountInactive'     => 'Your account is inactive. Please contact an administrator.',
    'accountBanned'       => 'Your account is not permitted to access this site. Please contact an administrator.',
    'loginHeading'        => 'Welcome back',
    'loginSubheading'     => 'Sign in to your account',
    'loginEmail'          => 'Email address',
    'loginPassword'       => 'Password',
    'loginRemember'       => 'Remember me',
    'loginButton'         => 'Sign in',
    'loginForgot'         => 'Forgot your password?',
    'showPassword'        => 'Show password',
    'hidePassword'        => 'Hide password',
    'haveAccount'         => 'Already have an account?',
    'noAccount'           => "Don't have an account?",

    // -------------------------------------------------------------------------
    // Register page
    // -------------------------------------------------------------------------
    'register'            => 'Register',
    'registerHeading'     => 'Create an account',
    'registerEmail'       => 'Email address',
    'registerPassword'    => 'Password',
    'registerPassConfirm' => 'Confirm password',
    'registerButton'      => 'Create account',

    // -------------------------------------------------------------------------
    // Password reset
    // -------------------------------------------------------------------------
    'forgotPassword'      => 'Forgot Password',
    'forgotHeading'       => 'Reset your password',
    'forgotSubheading'    => "Enter your email and we'll send you a reset link.",
    'forgotEmail'         => 'Email address',
    'forgotButton'        => 'Send reset link',
    'forgotSuccess'       => 'If that email is registered, a reset link has been sent.',

    'resetPassword'       => 'Reset Password',
    'resetHeading'        => 'Set a new password',
    'resetNewPassword'    => 'New password',
    'resetConfirmPass'    => 'Confirm new password',
    'resetButton'         => 'Update password',
    'resetSuccess'        => 'Your password has been updated. Please log in.',
    'setPasswordTitle'    => 'Set Your Password',
    'setPasswordDescription' => 'Your account requires a password before you can continue.',
    'setPasswordButton'   => 'Set Password & Continue',

    // -------------------------------------------------------------------------
    // Email verification
    // -------------------------------------------------------------------------
    'verifyEmail'         => 'Verify Email',
    'verifyHeading'       => 'Check your inbox',
    'verifySubheading'    => 'We sent a verification link to your email address.',
    'verifyResend'        => 'Resend verification email',

    // -------------------------------------------------------------------------
    // Pending approval
    // -------------------------------------------------------------------------
    'pendingTitle'             => 'Awaiting Approval',
    'pendingHeading'          => 'Account pending review',
    'pendingBody'             => 'Your registration has been received and is awaiting approval by an administrator.',
    'pendingDiocesanHeading'  => 'Account Pending Diocesan Approval',
    'pendingDiocesanBody'     => 'Your email is confirmed and your profile is complete. A diocesan administrator must now review and assign your role.',
    'pendingLogout'           => 'Sign out',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'badAttempt'          => 'Unable to log in. Please check your credentials.',
    'tooManyRequests'     => 'Too many login attempts. Please wait before trying again.',
    'invalidToken'        => 'Invalid or expired token.',

    // -------------------------------------------------------------------------
    // Keys mirroring Shield's vendor Auth.php (used by login.php / register.php)
    // -------------------------------------------------------------------------
    'email'               => 'Email Address',
    'password'            => 'Password',
    'passwordConfirm'     => 'Password (again)',
    'needAccount'         => 'Need an account?',
    'rememberMe'          => 'Remember me?',
    'useMagicLink'        => 'Use a Login Link',
    'successLogout'       => 'You have successfully logged out.',
    'backToLogin'         => 'Back to Login',
    'registerSuccess'     => 'Welcome aboard!',
    'registerDisabled'    => 'Registration is not currently allowed.',
    'registrationEmailUnavailable' => 'Registration could not be completed. If this email is already registered, please use the login page.',

    // -------------------------------------------------------------------------
    // Register page — additional keys
    // -------------------------------------------------------------------------
    'registerSubheading'  => 'Register with your parish. You will confirm your email, complete your profile, and then await parish review.',
    'chooseProvince'      => 'Choose province',
    'province'            => 'Province',
    'chooseParish'        => 'Choose parish',
    'parish'              => 'Parish',
    'parishSelectionRequired' => 'Please choose a valid parish from the list.',
    'interfaceLanguage'   => 'Interface language',
    'preferredLanguage'   => 'Preferred language',
    'chooseLanguage'      => 'Choose language',
    'languageEnglish'     => 'English',
    'languageSpanish'     => 'Spanish',
    'languageFrench'      => 'French',
    'verifyDeviceTitle'   => 'Verify Device',
    'verifyNewDeviceHeading' => 'Verify New Device',
    'verifyCodeIntro'     => 'We sent a 6-digit verification code to your email. Enter it below to continue this session.',
    'verifyCodeExpired'   => 'Your previous code has expired. Request a new code below.',
    'verifyCodeExpiresIn' => 'Code expires in about {0} minute(s).',
    'verificationCode'    => 'Verification code',
    'verifyDeviceButton'  => 'Verify Device',
    'resendCode'          => 'Resend Code',
    'logoutLink'          => 'Log out',

    // Device verification flow
    'verifyCodeMalformed'    => 'Enter the 6-digit verification code.',
    'verifyCodeExpiredRetry' => 'Verification code expired. Request a new code.',
    'verifyCodeLockedOut'    => 'Too many failed attempts. The verification code was invalidated — please request a new one.',
    'verifyCodeInvalid'      => 'Invalid verification code. Please try again.',
    'deviceVerifiedSuccess'  => 'Device verified successfully.',
    'verifyResendCooldown'   => 'Please wait {0} seconds before requesting another code.',
    'verifyResendSent'       => 'A new verification code was sent to your email.',
    'verifyEmailFailed'      => 'We could not send the verification code email. Use "Resend Code" to try again.',

    // Device verification email
    'deviceVerifyEmailSubject'    => 'Verify New Device Login - EkklesiaRD',
    'deviceVerifyEmailIntro'      => 'A login was detected from a new device on your EkklesiaRD account. To continue this session, enter this verification code:',
    'deviceVerifyEmailCodeExpiry' => 'This code expires in {0} minutes.',
    'deviceVerifyEmailTime'       => 'Time:',
    'deviceVerifyEmailIp'         => 'IP Address:',
    'deviceVerifyEmailDevice'     => 'Browser / Device:',
    'deviceVerifyEmailConfirm'    => 'If this was you, complete verification to continue.',
    'deviceVerifyEmailNotYou'     => 'If you did not log in from this device, please <strong>change your password immediately</strong> and contact your parish administrator.',

    // Trusted devices (Security page)
    'trustedDevicesTitle'        => 'Trusted Devices',
    'trustedDevicesIntro'        => 'Devices you have verified can sign in without an email code until they expire or are revoked.',
    'noTrustedDevices'           => 'No trusted devices yet. After verifying a new device it will appear here.',
    'deviceColumn'               => 'Device',
    'lastIpColumn'               => 'Last IP',
    'firstSeenColumn'            => 'First seen',
    'lastSeenColumn'             => 'Last seen',
    'expiresColumn'              => 'Expires',
    'thisDevice'                 => 'This device',
    'unknownDevice'              => 'Unknown device',
    'revokeDevice'               => 'Revoke',
    'revokeOtherDevices'         => 'Revoke all other devices',
    'forgetThisDevice'           => 'Forget this device',
    'confirmRevokeDevice'        => 'Revoke this trusted device? It will be asked to verify on next sign-in.',
    'confirmRevokeOtherDevices'  => 'Revoke all other trusted devices? They will be asked to verify on next sign-in.',
    'confirmForgetDevice'        => 'Forget this device? Your next sign-in from this browser will require verification.',
    'trustedDeviceRevoked'       => 'Trusted device revoked.',
    'trustedDevicesRevokedCount' => '{0} trusted device(s) revoked.',
    'trustedDeviceNotFound'      => 'Trusted device not found or already revoked.',
    'trustedDeviceForgotten'     => 'This device will be asked to verify on your next sign-in.',
];
