<?php

declare(strict_types=1);

/**
 * English texts of the e-mail sent when an account is added to the superadmin group.
 * Usage: lang('Mail.greeting', [$username]). Placeholders {0}, {1}, ... are filled by lang().
 */
return [
    'greeting'    => 'Hello {0},',
    'added'       => 'Your account ({0}) was added to the <b>superadmin</b> group on {1}, which requires a second factor at login.',
    'scan'        => 'Scan this code with Google Authenticator, Authy, or any other TOTP app:',
    'cant_scan'   => 'Can\'t scan it? Enter this key manually:',
    'codes'       => '<b>Recovery codes</b> — save these somewhere safe. Each works once, if you ever lose access to your authenticator app:',
    'admin_note'  => 'An admin can see this QR code and these recovery codes again later from the Users page ("{0}").',
];
