<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\Branding;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\TwoFactor;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Users admin: list, create, edit, delete Shield users and their group membership, plus the "View 2FA" modal
 * for superadmins (QR code + recovery codes, re-viewable any time via TwoFactor::ensureSecret()/recoveryCodes()).
 * Behind `Config\Sync::$superPermission` (route filter).
 */
class UsersAdmin extends Controller
{
    use AdminChrome;

    private Kernel $kernel;
    private UserModel $users;

    public function __construct()
    {
        $this->kernel = Kernel::instance();
        $this->users  = model(UserModel::class);
    }

    public function index(): string
    {
        helper('form');

        return view((string) config('Sync')->usersIndexView, [
            'users'  => $this->users->withGroups()->orderBy('id')->findAll(),
            'groups' => $this->groupNames(),
            'menu'   => $this->menu('users'),
            'head'   => $this->head(),
            'base'   => $this->base(),
        ]);
    }

    public function edit(int $id): string|RedirectResponse
    {
        helper('form');
        $user = $this->users->findById($id);

        if ($user === null) {
            return $this->err('Unknown user.');
        }

        $by = (string) (auth()->user()->username ?? auth()->id());
        $this->kernel->locks()->begin('auth', 'users:' . $id, $by);

        return view((string) config('Sync')->usersEditView, [
            'user'   => $user,
            'groups' => $this->groupNames(),
            'base'   => $this->base(),
            'node'   => config('Sync')->node,
            'menu'   => $this->menu('users'),
            'head'   => $this->head(),
        ]);
    }

    public function create(): RedirectResponse
    {
        [$input, $error] = $this->input(isNew: true);

        if ($error !== null) {
            return $this->err($error);
        }

        $user = new User([
            'username' => $input['username'],
            'email'    => $input['email'],
            'password' => $input['password'],
            'active'   => $input['active'] ? 1 : 0,
        ]);

        $this->users->save($user);
        $user = $this->users->findById($this->users->getInsertID());
        $user->syncGroups(...$input['groups']);

        if (in_array('superadmin', $input['groups'], true)) {
            $this->provisionTotp($user);
        }

        return $this->ok('User created.');
    }

    public function save(int $id): RedirectResponse
    {
        $user = $this->users->findById($id);

        if ($user === null) {
            return $this->err('Unknown user.');
        }

        [$input, $error] = $this->input(isNew: false, currentEmail: (string) $user->email, currentUsername: $user->username);

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        $wasSuper = $user->inGroup('superadmin');

        $user->username = $input['username'];
        $user->email    = $input['email'];
        $user->active   = $input['active'] ? 1 : 0;

        if ($input['password'] !== '') {
            $user->password = $input['password'];
        }

        $this->users->save($user);
        $user->syncGroups(...$input['groups']);

        if (! $wasSuper && in_array('superadmin', $input['groups'], true)) {
            $this->provisionTotp($user);
        }

        // edit() took the edit lease on open; release it now, as the "read-only until you save" banner promises.
        $this->kernel->locks()->handover('auth');

        return $this->ok('User saved.');
    }

    public function delete(int $id): RedirectResponse
    {
        $user = $this->users->findById($id);

        if ($user === null) {
            return $this->err('Unknown user.');
        }

        if ((int) $user->id === (int) auth()->id()) {
            return $this->err('You cannot delete your own account.');
        }

        if ($user->inGroup('superadmin') && $this->countSuperadmins() <= 1) {
            return $this->err('Cannot delete the last superadmin.');
        }

        $db = $this->kernel->db;
        $db->transStart();
        $db->table('auth_identities')->where('user_id', $user->id)->delete();
        $db->table('auth_groups_users')->where('user_id', $user->id)->delete();
        $db->table('auth_permissions_users')->where('user_id', $user->id)->delete();
        $this->users->delete($user->id);
        $db->transComplete();

        return $this->ok('User deleted.');
    }

    /** JSON endpoint for the "View 2FA" modal. */
    public function twofa(int $id): ResponseInterface
    {
        $user = $this->users->findById($id);

        if ($user === null || ! $user->inGroup('superadmin')) {
            return $this->response->setJSON(['error' => 'Not a superadmin.']);
        }

        $totp = new TwoFactor();

        if (! $totp->hasSecret($user)) {
            return $this->response->setJSON(['error' => 'Not set up yet — the QR code appears the first time this user logs in.']);
        }

        $secret = $totp->ensureSecret($user);

        return $this->response->setJSON([
            'secret' => $secret,
            'qr'     => $totp->qrSvg($totp->provisioningUri($user, $secret)),
            'codes'  => $totp->recoveryCodes($user),
        ]);
    }

    /** Generates (or reuses) a TOTP secret + recovery codes for a new/promoted superadmin and e-mails them. */
    private function provisionTotp(User $user): bool
    {
        $totp     = new TwoFactor();
        $secret   = $totp->ensureSecret($user);
        $existing = $totp->recoveryCodes($user);
        $codes    = $existing === [] ? $totp->generateRecoveryCodes($user) : array_column($existing, 'code');

        $email    = (string) $user->email;

        if ($email === '') {
            return false;
        }

        $emailer = service('email');
        $emailer->setTo($email);
        $emailer->setSubject(Branding::siteName() . ': set up your authenticator');
        $emailer->setMessage(view('AdGo\Sync\Views\Email\totp_2fa_setup_email', [
            'user'   => $user,
            'secret' => $secret,
            'qr'     => $totp->qrSvg($totp->provisioningUri($user, $secret)),
            'codes'  => $codes,
        ]));

        return $emailer->send();
    }

    /**
     * @return array{0: array{username: string, email: string, password: string, active: bool, groups: list<string>}, 1: ?string}
     */
    private function input(bool $isNew, string $currentEmail = '', string $currentUsername = ''): array
    {
        $username = trim((string) $this->request->getPost('username'));
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        $active   = $this->request->getPost('active') !== null;
        $groups   = array_values(array_intersect((array) $this->request->getPost('groups'), $this->groupNames()));

        $empty = ['username' => '', 'email' => '', 'password' => '', 'active' => false, 'groups' => []];

        if ($username === '' || mb_strlen($username) > 30) {
            return [$empty, 'The username must be 1-30 characters.'];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            return [$empty, 'Invalid e-mail.'];
        }

        if ($isNew && $password === '') {
            return [$empty, 'A password is required.'];
        }

        if ($password !== '') {
            // NothingPersonalValidator (a stock Shield password validator) needs a User to compare the
            // password against username/email; a throwaway unsaved one is enough — it is never persisted.
            $result = service('passwords')->check($password, new User(['username' => $username, 'email' => $email]));

            if (! $result->isOK()) {
                return [$empty, $result->reason()];
            }
        }

        if ($groups === []) {
            return [$empty, 'Select at least one group.'];
        }

        if ($username !== $currentUsername && $this->users->where('username', $username)->first() !== null) {
            return [$empty, 'That username is already taken.'];
        }

        if ($email !== $currentEmail
            && $this->kernel->db->table('auth_identities')->where(['type' => 'email_password', 'secret' => $email])->countAllResults() > 0) {
            return [$empty, 'That e-mail is already taken.'];
        }

        return [['username' => $username, 'email' => $email, 'password' => $password, 'active' => $active, 'groups' => $groups], null];
    }

    /** @return list<string> */
    private function groupNames(): array
    {
        return array_keys((array) setting('AuthGroups.groups'));
    }

    private function countSuperadmins(): int
    {
        return (int) $this->kernel->db->table('auth_groups_users')->where('group', 'superadmin')->countAllResults();
    }

    private function base(): string
    {
        return trim((string) config('Sync')->usersPath, '/');
    }
}
