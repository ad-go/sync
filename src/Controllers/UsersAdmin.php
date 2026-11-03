<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\Branding;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\TwoFactor;
use AdGo\Sync\Libraries\Xlsx;
use RuntimeException;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Users admin: list, create, edit, delete Shield users and their group membership, CSV import, plus the
 * "View 2FA" modal for superadmins (QR code + recovery codes, re-viewable any time via TwoFactor).
 * Every write path (form, DTE AJAX, CSV) goes through checkUser() and store(), so the rules and the superadmin
 * TOTP provisioning are the same wherever a user comes from. Behind `Config\Sync::$adminPermission` (route
 * filter) — but that tier can never grant or revoke the superadmin group, nor edit, delete or view the 2FA
 * secrets of an existing superadmin account: those need `Config\Sync::$superPermission` too (isSuper() below),
 * checked here rather than by the route, since it depends on the target user, not just the actor.
 */
class UsersAdmin extends Controller
{
    use AdminChrome;

    protected string $configKey = 'usersPath';

    protected function tab(): string
    {
        return 'users';
    }

    private Kernel $kernel;
    private UserModel $users;

    public function __construct()
    {
        $this->kernel = Kernel::instance();
        $this->users  = model(UserModel::class);
    }

    private function isSuper(): bool
    {
        return auth()->user() !== null && auth()->user()->can(config('Sync')->superPermission);
    }

    /** The groups an adminPermission-only actor may offer/assign: every group except superadmin. */
    private function assignableGroups(): array
    {
        $groups = $this->groupNames();

        return $this->isSuper() ? $groups : array_values(array_diff($groups, ['superadmin']));
    }

    /** The Users page lives in Settings now: this URL goes to its tab. */
    public function index(): RedirectResponse
    {
        return redirect()->to($this->settingsUrl('users'));
    }

    /** The Users tab of Settings. Rendered by SettingsAdmin, never served on its own. */
    public function panel(): string
    {
        helper('form');

        $cfg = config('Sync');

        return view((string) $cfg->usersIndexView, [
            'groups' => $this->assignableGroups(),
            'base'   => $this->base(),
            'dteUrl' => site_url(trim($cfg->dteAssetsUrl, '/') . '/'),
        ]);
    }

    public function create(): RedirectResponse
    {
        [$in, $error] = $this->checkUser($this->formInput(), isNew: true);

        if ($error !== null) {
            return $this->err($error);
        }

        $this->store(null, $in);

        return $this->ok(lang('Messages.users.created'));
    }

    public function delete(int $id): RedirectResponse
    {
        $user = $this->users->findById($id);

        if ($user === null) {
            return $this->err(lang('Messages.users.unknown'));
        }

        $err = $this->deleteUser($user);

        return $err !== null ? $this->err($err) : $this->ok(lang('Messages.users.deleted'));
    }

    /**
     * DTE AJAX endpoint — handles list (GET) and create/edit/remove (POST).
     *
     * Row IDs are strings like "row_1" (DT_RowId) so DTE can track them in the DOM.
     * Groups are returned as an array (DTE checkbox field); sent back as an array of checked values.
     * Password is write-only: on edit an empty value means "keep existing".
     */
    public function ajax(): ResponseInterface
    {
        $action = (string) ($this->request->getPost('action') ?? '');

        if ($action === '') {
            return $this->response->setJSON(['data' => $this->dteRows()]);
        }

        if ($action === 'remove') {
            foreach (array_keys((array) $this->request->getPost('data')) as $rowId) {
                $user = $this->users->findById((int) ltrim((string) $rowId, 'row_'));

                if ($user === null) {
                    continue;
                }

                $err = $this->deleteUser($user);

                if ($err !== null) {
                    return $this->response->setJSON(['error' => $err]);
                }
            }

            return $this->response->setJSON(['data' => []]);
        }

        if (! in_array($action, ['create', 'edit'], true)) {
            return $this->response->setJSON(['error' => lang('Messages.users.unknownAction')]);
        }

        $updated = [];

        foreach ((array) $this->request->getPost('data') as $rowId => $posted) {
            $user = null;

            if ($action === 'edit') {
                $user = $this->users->findById((int) ltrim((string) $rowId, 'row_'));

                if ($user === null) {
                    return $this->response->setJSON(['error' => lang('Messages.users.unknown')]);
                }

                if (! $this->isSuper() && $user->inGroup('superadmin')) {
                    return $this->response->setJSON(['error' => lang('Messages.users.superadminProtected')]);
                }
            }

            [$in, $err] = $this->checkUser(
                $this->dteInput((array) $posted),
                isNew: $user === null,
                currentEmail: (string) ($user->email ?? ''),
                currentUsername: (string) ($user->username ?? ''),
                defaultGroups: ['user'],
            );

            if ($err !== null) {
                return $this->response->setJSON(['error' => $err]);
            }

            $user      = $this->store($user, $in);
            $updated[] = $this->dteRow($this->users->withGroups()->findById((int) $user->id));
        }

        return $this->response->setJSON(['data' => $updated]);
    }

    /** The Excel model linked from the import dialog: a header row and two example users. */
    public function importTemplate(): ResponseInterface
    {
        return $this->excel('users-import-model.xlsx', [
            ['username', 'email', 'password', 'groups', 'active'],
            ['jdoe', 'jdoe@example.com', 'ChangeMe-2026', 'user', 1],
            ['asmith', 'asmith@example.com', 'ChangeMe-2026', 'user|beta', 1],
        ]);
    }

    /**
     * Excel export of the users, in the import layout. Passwords are never exported (they are stored hashed), so the
     * password column is left empty: fill it in before importing a new user.
     */
    public function export(): ResponseInterface
    {
        $rows = [['username', 'email', 'password', 'groups', 'active']];

        foreach ($this->users->withGroups()->orderBy('id')->findAll() as $user) {
            $row    = $this->dteRow($user);
            $rows[] = [$row['username'], $row['email'], '', implode('|', $row['groups']), $row['active'] === '1' ? 1 : 0];
        }

        return $this->excel('users.xlsx', $rows);
    }

    /** @param list<list<string|int>> $rows */
    private function excel(string $filename, array $rows): ResponseInterface
    {
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody(Xlsx::write($rows));
    }

    /**
     * Excel import: username, email, password, groups, active — one user per row (the first sheet). A header row
     * is skipped. Only .xlsx files are accepted.
     */
    public function import(): ResponseInterface
    {
        $file = $this->request->getFile('xlsx');

        if ($file === null || ! $file->isValid()) {
            return $this->response->setJSON(['ok' => false, 'message' => lang('Messages.users.noFile')]);
        }

        if (strtolower($file->getClientExtension()) !== 'xlsx') {
            return $this->response->setJSON(['ok' => false, 'message' => lang('Messages.users.xlsxOnly')]);
        }

        try {
            $rows = Xlsx::read($file->getTempName());
        } catch (RuntimeException $e) {
            return $this->response->setJSON(['ok' => false, 'message' => $e->getMessage()]);
        }

        $created = 0;
        $errors  = [];

        foreach ($rows as $i => $cols) {
            if ($cols === []) {
                continue;
            }

            if ($i === 0 && strtolower(trim($cols[0])) === 'username') {
                continue;
            }

            [$in, $err] = $this->checkUser([
                'username' => $cols[0] ?? '',
                'email'    => $cols[1] ?? '',
                'password' => $cols[2] ?? '',
                'groups'   => explode('|', $cols[3] ?? 'user'),
                'active'   => strtolower(trim($cols[4] ?? '1')) !== '0',
            ], isNew: true, defaultGroups: ['user']);

            if ($err !== null) {
                $errors[] = lang('Messages.users.importRow', [$i + 1, $err]);
                continue;
            }

            $this->store(null, $in);
            $created++;
        }

        $message = lang('Messages.users.imported', [$created]);

        if ($errors !== []) {
            $message .= ' ' . lang('Messages.users.importErrors', [implode('; ', $errors)]);
        }

        return $this->response->setJSON(['ok' => $errors === [], 'message' => $message]);
    }

    /** JSON endpoint for the "View 2FA" modal. */
    public function twofa(int $id): ResponseInterface
    {
        if (! $this->isSuper()) {
            return $this->response->setStatusCode(404)->setJSON(['error' => lang('Messages.users.superadminProtected')]);
        }

        $user = $this->users->findById($id);

        if ($user === null || ! $user->inGroup('superadmin')) {
            return $this->response->setStatusCode(404)->setJSON(['error' => lang('Messages.users.notSuperadmin')]);
        }

        $totp = new TwoFactor();

        if (! $totp->hasSecret($user)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => lang('Messages.users.notSetUp')]);
        }

        $secret = $totp->ensureSecret($user);

        return $this->response->setJSON([
            'secret' => $secret,
            'qr'     => $totp->qrSvg($totp->provisioningUri($user, $secret)),
            'codes'  => $totp->recoveryCodes($user),
        ]);
    }

    /**
     * Writes one validated user: creates it ($user null) or updates it, then sets its groups. A user who becomes
     * a superadmin here gets a TOTP secret and recovery codes (e-mailed); an existing one keeps its own.
     *
     * @param array{username: string, email: string, password: string, active: bool, groups: list<string>} $in
     */
    private function store(?User $user, array $in): User
    {
        $wasSuper = $user?->inGroup('superadmin') ?? false;

        if ($user === null) {
            $user = new User([
                'username' => $in['username'],
                'email'    => $in['email'],
                'password' => $in['password'],
                'active'   => $in['active'] ? 1 : 0,
            ]);
            $this->users->save($user);
            $user = $this->users->findById($this->users->getInsertID());
        } else {
            $user->username = $in['username'];
            $user->email    = $in['email'];
            $user->active   = $in['active'] ? 1 : 0;

            if ($in['password'] !== '') {
                $user->password = $in['password'];
            }

            $this->users->save($user);
        }

        $user->syncGroups(...$in['groups']);

        if (! $wasSuper && in_array('superadmin', $in['groups'], true)) {
            $this->provisionTotp($user);
        }

        return $user;
    }

    /** Generates (or reuses) a TOTP secret + recovery codes for a new/promoted superadmin and e-mails them. */
    private function provisionTotp(User $user): bool
    {
        $totp     = new TwoFactor();
        $secret   = $totp->ensureSecret($user);
        $existing = $totp->recoveryCodes($user);
        $codes    = $existing === [] ? $totp->generateRecoveryCodes($user) : array_column($existing, 'code');

        $email = (string) $user->email;

        if ($email === '') {
            return false;
        }

        $emailer = service('email');
        $emailer->setTo($email);
        $emailer->setSubject(lang('Messages.users.totpSubject', [Branding::siteName()]));
        $emailer->setMessage(view('AdGo\Sync\Views\Email\totp_2fa_setup_email', [
            'user'   => $user,
            'secret' => $secret,
            'qr'     => $totp->qrSvg($totp->provisioningUri($user, $secret)),
            'codes'  => $codes,
        ]));

        return $emailer->send();
    }

    /** @return array{username: string, email: string, password: string, active: bool, groups: list<string>} */
    private function formInput(): array
    {
        return [
            'username' => (string) $this->request->getPost('username'),
            'email'    => (string) $this->request->getPost('email'),
            'password' => (string) $this->request->getPost('password'),
            'active'   => $this->request->getPost('active') !== null,
            'groups'   => (array) $this->request->getPost('groups'),
        ];
    }

    /**
     * @param array<string, mixed> $posted
     * @return array{username: string, email: string, password: string, active: bool, groups: list<string>}
     */
    private function dteInput(array $posted): array
    {
        return [
            'username' => (string) ($posted['username'] ?? ''),
            'email'    => (string) ($posted['email'] ?? ''),
            'password' => (string) ($posted['password'] ?? ''),
            'active'   => (string) ($posted['active'] ?? '1') !== '0',
            'groups'   => (array) ($posted['groups'] ?? []),
        ];
    }

    /**
     * Checks one user's input, whatever its source. Returns the cleaned values, or the first error message.
     *
     * @param array{username?: mixed, email?: mixed, password?: mixed, active?: mixed, groups?: mixed} $raw
     * @param list<string> $defaultGroups used when no group was given (DTE, CSV); the form passes none and must select one
     * @return array{0: array{username: string, email: string, password: string, active: bool, groups: list<string>}, 1: ?string}
     */
    private function checkUser(array $raw, bool $isNew, string $currentEmail = '', string $currentUsername = '', array $defaultGroups = []): array
    {
        $username = trim((string) ($raw['username'] ?? ''));
        $email    = trim((string) ($raw['email'] ?? ''));
        $password = (string) ($raw['password'] ?? '');
        $active   = (bool) ($raw['active'] ?? true);
        $groups   = array_values(array_intersect(array_map('trim', (array) ($raw['groups'] ?? [])), $this->assignableGroups()));

        if ($groups === []) {
            $groups = $defaultGroups;
        }

        $empty = ['username' => '', 'email' => '', 'password' => '', 'active' => false, 'groups' => []];
        $ok    = static fn (array $values): array => [$values, null];
        $fail  = static fn (string $message): array => [$empty, $message];

        if ($username === '' || mb_strlen($username) > 30) {
            return $fail(lang('Messages.users.usernameLength'));
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            return $fail(lang('Messages.users.invalidEmail'));
        }

        if ($isNew && $password === '') {
            return $fail(lang('Messages.users.passwordNeeded'));
        }

        if ($password !== '') {
            // NothingPersonalValidator (a stock Shield password validator) compares the password with
            // username/email, so it needs a User; a throwaway unsaved one is enough — it is never persisted.
            $result = service('passwords')->check($password, new User(['username' => $username, 'email' => $email]));

            if (! $result->isOK()) {
                return $fail($result->reason());
            }
        }

        if ($groups === []) {
            return $fail(lang('Messages.users.noGroup'));
        }

        if ($username !== $currentUsername && $this->users->where('username', $username)->first() !== null) {
            return $fail(lang('Messages.users.usernameTaken'));
        }

        if ($email !== $currentEmail
            && $this->kernel->db->table('auth_identities')->where(['type' => 'email_password', 'secret' => $email])->countAllResults() > 0) {
            return $fail(lang('Messages.users.emailTaken'));
        }

        return $ok(['username' => $username, 'email' => $email, 'password' => $password, 'active' => $active, 'groups' => $groups]);
    }

    /** @return list<array<string, mixed>> */
    private function dteRows(): array
    {
        return array_values(array_map(
            fn (User $u) => $this->dteRow($u),
            $this->users->withGroups()->orderBy('id')->findAll(),
        ));
    }

    /** @return array<string, mixed> */
    private function dteRow(User $u): array
    {
        $groups = array_map(
            static fn ($g) => is_object($g) ? (string) ($g->name ?? $g->group ?? '') : (string) $g,
            (array) ($u->groups ?? []),
        );

        return [
            'DT_RowId'    => 'row_' . $u->id,
            'DT_RowClass' => '',
            'id'          => (int) $u->id,
            'username'    => (string) $u->username,
            'email'       => (string) $u->email,
            'groups'      => array_values($groups),
            'active'      => $u->active ? '1' : '0',
        ];
    }

    /** @return list<string> */
    private function groupNames(): array
    {
        return array_keys((array) setting('AuthGroups.groups'));
    }

    private function deleteUser(User $user): ?string
    {
        if ((int) $user->id === (int) auth()->id()) {
            return lang('Messages.users.ownAccount');
        }

        if (! $this->isSuper() && $user->inGroup('superadmin')) {
            return lang('Messages.users.superadminProtected');
        }

        if ($user->inGroup('superadmin') && $this->countSuperadmins() <= 1) {
            return lang('Messages.users.lastSuperadmin');
        }

        $db = $this->kernel->db;
        $db->transStart();
        $db->table('auth_identities')->where('user_id', $user->id)->delete();
        $db->table('auth_groups_users')->where('user_id', $user->id)->delete();
        $db->table('auth_permissions_users')->where('user_id', $user->id)->delete();
        $this->users->delete($user->id);
        $db->transComplete();

        return null;
    }

    private function countSuperadmins(): int
    {
        return (int) $this->kernel->db->table('auth_groups_users')->where('group', 'superadmin')->countAllResults();
    }
}
