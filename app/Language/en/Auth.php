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
];
