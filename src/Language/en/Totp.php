<?php

declare(strict_types=1);

/**
 * English texts of the two-factor (TOTP) pages shown at login for superadmins.
 * Usage: lang('Totp.setup.title').
 */
return [
    'setup' => [
        'title'        => 'Set up your authenticator',
        'heading'      => 'Set up your authenticator app',
        'intro'        => 'Your account is in the <b>superadmin</b> group, which requires a second factor. Scan this code with Google Authenticator, Authy, or any other TOTP app, then enter the 6-digit code it shows.',
        'cant_scan'    => 'Can\'t scan it? Enter this key manually:',
        'placeholder'  => '000000 or recovery code',
        'confirm'      => 'Confirm',
        'codes_title'  => 'Recovery codes',
        'codes_text'   => 'Save these somewhere safe — each works once, if you ever lose access to your authenticator app. You can see them again later from the Users page ("{0}").',
    ],

    'verify' => [
        'title'       => 'Enter your authenticator code',
        'intro'       => 'Open your authenticator app and enter the 6-digit code, or one of your recovery codes.',
        'placeholder' => '000000 or recovery code',
        'confirm'     => 'Confirm',
    ],
];
