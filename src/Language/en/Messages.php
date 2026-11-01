<?php

declare(strict_types=1);

/**
 * English texts of the messages the controllers and libraries return to the browser (flash, JSON replies).
 * Placeholders {0}, {1}, ... are filled with the arguments of lang('Messages.<key>', [args]).
 */
return [
    'settings' => [
        'brandingSaved'         => 'Branding saved.',
        'logoRemoved'           => 'Logo removed.',
        'settingsSaved'         => 'Settings saved.',
        'nothingToChange'       => 'Nothing to change.',
        'identitySaved'         => 'Identity saved. Apply the matching change on the peer node too.',
        'identityInvalid'       => 'Invalid {0}: lowercase letters, digits, - and _ only.',
        'secretInvalid'         => 'Invalid secret: 32-128 letters/digits.',
        'siteNameLength'        => 'The site name must be 1-80 characters.',
        'accentColor'           => 'The accent colour must be a #rrggbb value.',
        'logoTooBig'            => 'The logo must be at most {0} MiB.',
        'logoType'              => 'The logo must be png, jpg, svg or webp.',
        'folderOutside'         => 'Not a folder inside the synchronised directory.',
        'saved'                 => 'Saved',
        'mailNoTarget'          => 'Save the alert e-mail and the SMTP host first.',
        'mailNotSent'           => 'The e-mail was not sent: {0}',
        'mailSent'              => 'Test e-mail sent to {0}.',
        'telegramNotConfigured' => 'Telegram not configured — set the bot token and chat id first.',
        'telegramSent'          => 'Test message sent.',
        'telegramFailed'        => 'Send failed — check the token and chat id in the server log.',
    ],
    'users' => [
        'created'        => 'User created.',
        'deleted'        => 'User deleted.',
        'unknown'        => 'Unknown user.',
        'unknownAction'  => 'Unknown action.',
        'noFile'         => 'No file uploaded.',
        'xlsxOnly'       => 'Only Excel (.xlsx) files can be imported.',
        'notSuperadmin'  => 'Not a superadmin.',
        'notSetUp'       => 'Not set up yet — the QR code appears the first time this user logs in.',
        'usernameLength' => 'The username must be 1-30 characters.',
        'invalidEmail'   => 'Invalid e-mail.',
        'passwordNeeded' => 'A password is required.',
        'noGroup'        => 'Select at least one group.',
        'usernameTaken'  => 'That username is already taken.',
        'emailTaken'     => 'That e-mail is already taken.',
        'ownAccount'     => 'You cannot delete your own account.',
        'lastSuperadmin' => 'Cannot delete the last superadmin.',
        'importRow'      => 'Row {0}: {1}',
        'imported'       => 'Imported {0} user(s).',
        'importErrors'   => 'Errors: {0}',
        'totpSubject'    => '{0}: set up your authenticator',
    ],
    'login' => [
        'invalid2fa' => 'Invalid 2FA code.',
    ],
    'lock' => [
        'peerUnreachable' => 'peer unreachable ({0}); the lease stays until it expires',
        'notApplied'      => 'the peer has not applied our changes yet; the lease stays until it expires',
    ],
];
