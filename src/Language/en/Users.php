<?php

declare(strict_types=1);

/**
 * English texts of the Users admin (Views/users/panel.php). Usage: lang('Users.title').
 * Placeholders {0}, {1}, ... are filled with the arguments of lang().
 */
return [
    'title'      => 'Users',
    'page_title' => 'Users',

    'col' => [
        'id'       => 'ID',
        'username' => 'Username',
        'email'    => 'Email',
        'groups'   => 'Groups',
        'active'   => 'Active',
    ],

    // Editor fields
    'field' => [
        'username'      => 'Username',
        'email'         => 'E-mail',
        'password'      => 'Password',
        'password_hint' => 'Leave blank to keep',
        'groups'        => 'Groups',
        'active'        => 'Active',
        'yes'           => 'Yes',
        'no'            => 'No',
    ],

    'btn' => [
        'new'    => 'New',
        'export' => 'Export Excel',
        'import' => 'Import Excel',
    ],

    'delete' => [
        'title'   => 'Delete user',
        'message' => 'This action cannot be undone.',
        'button'  => 'Delete',
        'tooltip' => 'Delete',
    ],

    'twofa' => [
        'tooltip' => 'View 2FA',
        'heading' => 'Authenticator (2FA)',
        'loading' => 'Loading…',
        'error'   => 'Could not load.',
        'key'     => 'Manual key:',
        'codes'   => 'Recovery codes (struck through = used):',
        'close'   => 'Close',
    ],

    'import' => [
        'title'      => 'Import users (Excel)',
        'format'     => 'One user per row, in this order: <code>username, email, password, groups, active</code>. Groups are separated by <code>|</code> (for example <code>user|beta</code>); active is <code>1</code> or <code>0</code>. A first row with the column names is skipped.',
        'model'      => 'Download the Excel model',
        'drop'       => 'Choose an Excel file (.xlsx), or drop it here',
        'drop_hint'  => '.xlsx · one user per row',
        'cancel'     => 'Cancel',
        'run'        => 'Import',
        'done'       => 'Done.',
        'failed'     => 'Import failed.',
        'close'      => 'Close',
    ],

    // DataTables and DataTables Editor built-in texts
    'dt' => [
        'search'        => 'Search:',
        'info'          => 'Showing _START_ to _END_ of _TOTAL_ users',
        'info_empty'    => 'Showing 0 to 0 of 0 users',
        'previous'      => 'Previous',
        'next'          => 'Next',
        'empty'         => 'No users yet',
        'zero'          => 'No matching users found',
        'loading'       => 'Loading…',
        'system_error'  => 'A system error has occurred. Please try again.',
        'create_title'  => 'New user',
        'create_submit' => 'Create',
        'edit_title'    => 'Edit user',
        'edit_submit'   => 'Save',
        'remove_title'  => 'Delete user',
        'remove_submit' => 'Delete',
        'remove_confirm'     => 'Delete %d users?',
        'remove_confirm_one' => 'Delete this user?',
    ],
];
