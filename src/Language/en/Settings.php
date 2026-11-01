<?php

declare(strict_types=1);

/**
 * English texts of the settings page (Views/settings/index.php, _fields.php and its scripts).
 * Placeholders {0}, {1}, ... are filled with the arguments of lang().
 */
return [
    'title' => 'Settings',
    'card' => [
        'email'    => 'E-mail',
        'telegram' => 'Telegram',
    ],
    'brand' => [
        'languages'      => 'Languages',
        'languages_hint' => 'Visitors can switch to any ticked language. At least one stays ticked.',
        'logo_title'   => 'Click or drop an image here',
        'logo_empty'   => 'Logo: click or drop an image',
        'logo_replace' => 'Logo: click or drop to replace',
        'logo_formats' => 'PNG · JPG · SVG · WebP, max {0} MiB',
        'site_name'    => 'Site name',
        'accent'       => 'Accent colour',
        'pick_colour'  => 'Pick a colour',
        'font'         => 'Font',
        'font_default' => '(default)',
        'size'         => 'Size (px)',
        'size_aria'    => 'Font size (px)',
        'login_field'  => 'Login field',
        'login_email'  => 'E-mail',
        'login_username' => 'Username',
        'login_both'   => 'E-mail or username',
        'autosave'     => 'Changes are saved automatically',
        'remove_logo'  => 'Remove logo',
    ],
    'node' => [
        'fingerprint'        => 'Secret fingerprint',
        'fp_hint'            => 'Renaming a node or rotating the secret only changes {0} — apply the matching value on the peer by hand.',
        'fp_preview'         => 'Preview: the fingerprint the node gets after Save (now: {0})',
        'gen_fp_title'       => 'Generate a new secret into the field below (save to apply)',
        'generate'           => 'Generate a new secret',
        'copy_fp_title'      => 'Copy the fingerprint',
        'copy'               => 'Copy',
        'this_node'          => 'This node',
        'peer_node'          => 'Peer node',
        'new_secret'         => 'New shared secret',
        'secret_placeholder' => 'leave blank to keep the current one',
        'gen_secret_title'   => 'Generate a random secret (save to apply)',
        'gen_secret_aria'    => 'Generate a random secret',
        'copy_secret_title'  => 'Copy the new secret',
        'secret_hint'        => 'Min 32 alphanumeric characters. Leave blank to keep the current secret.',
        'secret_generated'   => 'New secret generated: its fingerprint is shown above; press Save to apply it.',
        'nothing_to_copy'    => 'Nothing to copy yet: generate a secret or type one first.',
    ],
    'folder' => [
        'pick_title' => 'Pick the synchronised folder',
        'pick_hint'  => 'Pick a folder inside the synchronised directory',
        'pick_aria'  => 'Pick a folder',
        'select'     => 'Select a folder in the tree.',
        'use'        => 'Use this folder',
        'cancel'     => 'Cancel',
        'close'      => 'Close',
    ],
    'btn' => [
        'save'       => 'Save',
        'test_email' => 'Test e-mail',
        'test_telegram' => 'Test Telegram',
    ],
    'js' => [
        'saving'    => 'Saving…',
        'saved'     => 'Saved',
        'net_error' => 'Not saved: network error',
        'bad_response' => 'Not saved: unexpected response ({0})',
    ],
    'field' => [
        'none'       => '(none)',
        'not_set'    => 'not set',
        'secret_set' => 'set — leave blank to keep',
    ],
];
