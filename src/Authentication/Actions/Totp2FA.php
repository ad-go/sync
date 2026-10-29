<?php

declare(strict_types=1);

namespace AdGo\Sync\Authentication\Actions;

use AdGo\Sync\Libraries\TwoFactor;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Authentication\Actions\ActionInterface;
use CodeIgniter\Shield\Authentication\Actions\ConditionalActionInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Exceptions\RuntimeException;
use CodeIgniter\Shield\Traits\Viewable;

/**
 * TOTP (authenticator app) second factor, required for the `superadmin` group only (appliesTo()). Unlike
 * Shield's own Email2FA, createIdentity() does NOT regenerate the secret on every login — it reuses the
 * existing one (AdGo\Sync\Libraries\TwoFactor::ensureSecret()), or creates one the first time a superadmin
 * without one yet logs in, so pairing with the authenticator app survives across logins. Recovery codes are
 * generated once, either here (a pre-existing superadmin's first login) or by UsersAdmin::create()/save() when
 * a user is (made) superadmin, never both.
 *
 * Register as `Config\Auth::$actions['login']` in the host application's published `app/Config/Auth.php`
 * (`php spark sync:install` tries to patch this automatically) — Shield's own generic auth-action-* routes
 * (`service('auth')->routes()`) then dispatch to show()/handle()/verify() with nothing new to route.
 */
class Totp2FA implements ActionInterface, ConditionalActionInterface
{
    use Viewable;

    private const TYPE = 'totp_2fa';

    public function appliesTo(User $user): bool
    {
        if (env('sync.disable2fa') === true) {
            return false;
        }

        return $user->inGroup('superadmin');
    }

    public function show()
    {
        $user = $this->pendingUser();
        $totp = new TwoFactor();

        if (! $totp->isConfirmed($user)) {
            $secret = $totp->ensureSecret($user);

            if ($totp->recoveryCodes($user) === []) {
                $totp->generateRecoveryCodes($user);
            }

            return $this->view('AdGo\Sync\Views\totp_2fa_setup', [
                'secret' => $secret,
                'qr'     => $totp->qrSvg($totp->provisioningUri($user, $secret)),
                'codes'  => $totp->recoveryCodes($user),
            ]);
        }

        return $this->view('AdGo\Sync\Views\totp_2fa_verify');
    }

    /** Not used by our own views (they post straight to verify); kept only so the interface/routes stay happy. */
    public function handle(IncomingRequest $request)
    {
        return redirect()->route('auth-action-show');
    }

    public function verify(IncomingRequest $request)
    {
        $user = $this->pendingUser();
        $totp = new TwoFactor();
        $code = (string) $request->getPost('token');

        $ok = $totp->verifyCode($user, $code) || $totp->verifyRecoveryCode($user, $code);

        if (! $ok) {
            session()->setFlashdata('error', 'Invalid code. Check your authenticator app, or use one of your recovery codes.');

            return $this->show();
        }

        if (! $totp->isConfirmed($user)) {
            $totp->confirm($user);
        }

        $this->completeLogin($user);

        return redirect()->to(config('Auth')->loginRedirect());
    }

    /** Reuses an existing secret; only ever creates a new one when the user truly has none yet. */
    public function createIdentity(User $user): string
    {
        return (new TwoFactor())->ensureSecret($user);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    private function pendingUser(): User
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();
        $user          = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        return $user;
    }

    /**
     * Clears the session's pending-action flags and completes the login — the same two steps Shield's own
     * `Session::checkAction()` does, minus deleting the identity (ours must survive to the next login).
     * `setSessionUserKey()`/`removeSessionUserKey()` are protected on Session, so this mirrors them through
     * its own public `completeLogin()` plus the public `session()`/`setting()` helpers it uses internally.
     */
    private function completeLogin(User $user): void
    {
        $field = setting('Auth.sessionConfig')['field'];
        $info  = session($field) ?? [];
        unset($info['auth_action'], $info['auth_action_message']);
        session()->set($field, $info);

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();
        $authenticator->completeLogin($user);
    }
}
