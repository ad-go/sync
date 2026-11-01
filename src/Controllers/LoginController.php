<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\TwoFactor;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Controllers\LoginController as ShieldLoginController;
use CodeIgniter\Shield\Validation\ValidationRules;

/**
 * Extends Shield's LoginController with two features:
 *
 * 1. Dynamic credential field: uses setting('Sync.loginField') to accept 'email', 'username', or 'both',
 *    ignoring Config\Auth::$validFields (which is kept as-is for other Shield subsystems).
 *
 * 2. Inline TOTP: if the user submits a `token` field on the login form and a TOTP action is pending
 *    after successful password auth, the code is verified immediately — skipping the separate action page.
 *    If the token is wrong, the user is redirected back with an error. If blank, the normal action-page
 *    redirect happens as usual.
 *
 * Register via the host app's Routes.php before service('auth')->routes():
 *   $routes->get('login', '\AdGo\Sync\Controllers\LoginController::loginView', ['as' => 'login']);
 *   $routes->post('login', '\AdGo\Sync\Controllers\LoginController::loginAction');
 */
class LoginController extends ShieldLoginController
{
    public function loginAction(): RedirectResponse
    {
        $rules = $this->getValidationRules();

        if (! $this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $lf     = (string) (setting('Sync.loginField') ?: 'email');
        $fields = match($lf) {
            'username' => ['username'],
            'both'     => ['email', 'username'],
            default    => ['email'],
        };

        $credentials             = $this->request->getPost($fields) ?? [];
        $credentials             = array_filter($credentials);
        $credentials['password'] = (string) $this->request->getPost('password');
        $remember                = (bool) $this->request->getPost('remember');

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $result = $authenticator->remember($remember)->attempt($credentials);

        if (! $result->isOK()) {
            return redirect()->route('login')->withInput()->with('error', $result->reason());
        }

        if ($authenticator->hasAction()) {
            $token = trim((string) $this->request->getPost('token'));

            if ($token !== '') {
                $user = $authenticator->getPendingUser();

                if ($user !== null) {
                    $totp = new TwoFactor();

                    try {
                        $ok = $totp->verifyCode($user, $token) || $totp->verifyRecoveryCode($user, $token);
                    } catch (\Throwable) {
                        $ok = false;
                    }

                    if (! $ok) {
                        // TOTP not confirmed yet → guide to setup page, not error on login form.
                        if (! $totp->isConfirmed($user)) {
                            return redirect()->route('auth-action-show')->withCookies();
                        }

                        return redirect()->route('login')->withInput()->with('error', 'Invalid 2FA code.');
                    }

                    if (! $totp->isConfirmed($user)) {
                        $totp->confirm($user);
                    }

                    // Clear the pending action and complete login (mirrors Totp2FA::completeLogin()).
                    $field = setting('Auth.sessionConfig')['field'];
                    $info  = session($field) ?? [];
                    unset($info['auth_action'], $info['auth_action_message']);
                    session()->set($field, $info);
                    $authenticator->completeLogin($user);

                    return redirect()->to(config('Auth')->loginRedirect())->withCookies();
                }
            }

            return redirect()->route('auth-action-show')->withCookies();
        }

        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
    }

    protected function getValidationRules(): array
    {
        $lf  = (string) (setting('Sync.loginField') ?: 'email');
        $cfg = config('Auth');
        $pw  = (new ValidationRules())->getPasswordRules();

        return match($lf) {
            'username' => [
                'username' => $cfg->usernameValidationRules,
                'password' => $pw,
            ],
            'both' => [
                'email'    => ['label' => 'Auth.email',    'rules' => ['permit_empty', 'valid_email']],
                'username' => ['label' => 'Auth.username', 'rules' => ['permit_empty', 'min_length[3]', 'max_length[30]']],
                'password' => $pw,
            ],
            default => [
                'email'    => $cfg->emailValidationRules,
                'password' => $pw,
            ],
        };
    }
}
