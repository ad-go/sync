<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\Branding;
use AdGo\Sync\Libraries\DbCheck;
use AdGo\Sync\Libraries\EnvFile;
use AdGo\Sync\Libraries\EnvSettings;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\NodeIdentity;
use CodeIgniter\Config\Services;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Sync as SyncConfig;
use RuntimeException;
use Throwable;

/**
 * Settings page: site branding (name/logo/accent colour/font, via `codeigniter4/settings`) and the node identity
 * (`.env`: node/peer names, shared secret, database access and the other whitelisted `.env` variables, via
 * EnvFile/EnvSettings/DbCheck). Behind `Config\Sync::$superPermission` (route filter).
 *
 * Renaming a node or rotating the shared secret only takes effect locally: the matching change must be made on
 * the peer by hand (same secret, matching peerNode name) — the package does not try to coordinate this over the
 * network, so a bad value here can never also break the other node.
 */
class SettingsAdmin extends Controller
{
    use AdminChrome;

    protected string $configKey = 'settingsPath';

    public function index(): string
    {
        helper('form');
        $cfg = config('Sync');
        $env = EnvFile::default()->all();

        $fields = [];

        foreach (EnvSettings::FIELDS as $key => $f) {
            // An empty sync.files.dir means the package default, WRITEPATH/share: show that path, so the field is never blank.
            $default = $key === 'sync.files.dir' ? rtrim(WRITEPATH, "/\\") . DIRECTORY_SEPARATOR . 'share' : (isset($f['default']) ? (string) $f['default'] : '');
            $fields[$key] = $f + ['value' => in_array($f['type'], ['secret'], true) ? '' : (($env[$key] ?? '') !== '' ? $env[$key] : $default), 'set' => isset($env[$key]) && $env[$key] !== ''];
        }

        // Users and Conflicts are tabs of this page: their markup is rendered here, not served on their own.
        $openConflict = (int) $this->request->getGet('conflict');

        return view((string) $cfg->settingsIndexView, [
            'usersPanel'     => (new UsersAdmin())->panel(),
            'filesRoot'      => rtrim(ROOTPATH, "/\\"),
            'dirsUrl'        => site_url($this->base() . '/dirs'),
            'conflictsPanel' => (new ConflictAdmin())->panel($openConflict > 0 ? $openConflict : null),
            'node'         => $cfg->node,
            'fields'       => $fields,
            'siteName'     => Branding::siteName(),
            'logo'         => Branding::logoUrl(),
            'accentColor'  => (string) setting('Sync.accentColor'),
            'fontFamily'   => (string) setting('Sync.fontFamily'),
            'fontSize'     => (int) setting('Sync.fontSize'),
            'fonts'        => SyncConfig::FONTS,
            'loginField'   => (string) (setting('Sync.loginField') ?: 'email'),
            'maxImageMb'   => $cfg->maxImageMb,
            'secretHash'   => NodeIdentity::fingerprint($cfg->secret),
            'peerNode'     => $cfg->peerNode,
            'base'         => $this->base(),
        ]);
    }

    /**
     * Folder list for the directory picker (jsTree, lazy): the sub-folders of `path`, relative to the application root
     * (the modal starts there). Anything outside the application root, or not a folder, is refused.
     */
    public function dirs(): ResponseInterface
    {
        $base = realpath(rtrim(ROOTPATH, "/\\"));
        $rel  = trim((string) $this->request->getGet('path'), '/\\');
        $dir  = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . $rel);

        if ($base === false || $dir === false || ! is_dir($dir)
            || ($dir !== $base && ! str_starts_with($dir, $base . DIRECTORY_SEPARATOR))) {
            return $this->response->setStatusCode(404)->setJSON(['error' => lang('Messages.settings.folderOutside')]);
        }

        $items = [];

        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || ! is_dir($dir . DIRECTORY_SEPARATOR . $name)) {
                continue;
            }

            $items[] = ['id' => ($rel === '' ? '' : $rel . '/') . $name, 'text' => $name, 'children' => true];
        }

        usort($items, static fn (array $a, array $b): int => strcasecmp($a['text'], $b['text']));

        return $this->response->setJSON($items);
    }

    /** The synchronised directory: sync.files.dir from .env, or the package default WRITEPATH/share. */
    private function filesRoot(): string
    {
        $dir = (string) (EnvFile::default()->all()['sync.files.dir'] ?? '');

        return rtrim($dir !== '' ? $dir : WRITEPATH . 'share', '/\\');
    }

    /**
     * Sends a test e-mail with the saved sync.mail.* settings (the same SMTP setup the alerts use) to the alert address.
     */
    public function emailTest(): RedirectResponse
    {
        $env = EnvFile::default()->all();
        $to  = trim((string) ($env['sync.mail.to'] ?? ''));
        $host = trim((string) ($env['sync.mail.host'] ?? ''));

        if ($to === '' || $host === '') {
            return $this->err(lang('Messages.settings.mailNoTarget'));
        }

        $user = (string) ($env['sync.mail.user'] ?? '');
        $from = (string) (($env['sync.mail.from'] ?? '') !== '' ? $env['sync.mail.from'] : ($user !== '' ? $user : ''));

        try {
            $defaults = get_object_vars(config('Email'));
        } catch (Throwable) {
            $defaults = [];
        }

        try {
            $mail = Services::email([
                'protocol'    => 'smtp',
                'SMTPHost'    => $host,
                'SMTPPort'    => (int) ($env['sync.mail.port'] ?? 587),
                'SMTPUser'    => $user,
                'SMTPPass'    => (string) ($env['sync.mail.pass'] ?? ''),
                'SMTPCrypto'  => (string) ($env['sync.mail.crypto'] ?? 'tls'),
                'SMTPTimeout' => 15,
                'mailType'    => 'text',
                'charset'     => 'UTF-8',
                'newline'     => "\r\n",
                'CRLF'        => "\r\n",
            ] + $defaults, false);

            $mail->setFrom($from !== '' ? $from : (string) ($defaults['fromEmail'] ?? $to), 'Sync ' . config('Sync')->node)
                ->setTo($to)
                ->setSubject('Sync: test e-mail')
                ->setMessage('This is a test e-mail from the Sync settings page of node ' . config('Sync')->node . '.');

            if (! $mail->send(false)) {
                return $this->err(lang('Messages.settings.mailNotSent', [trim(strip_tags($mail->printDebugger(['headers'])))]));
            }
        } catch (Throwable $e) {
            return $this->err(lang('Messages.settings.mailNotSent', [$e->getMessage()]));
        }

        return $this->ok(lang('Messages.settings.mailSent', [$to]));
    }

    /** Toggle licensed DTE mode (Sync.pay setting). */
    public function dteToggle(): RedirectResponse
    {
        $current = (bool) (setting('Sync.pay') ?? config('Sync')->pay);
        setting('Sync.pay', ! $current);

        $ref = $this->request->getServer('HTTP_REFERER');
        return redirect()->to($ref ?: site_url($this->base()));
    }

    /** Site branding: name, logo upload, accent colour, font. */
    /**
     * Branding is saved as the admin changes it: an AJAX request gets JSON (and the fresh CSRF token, since the form
     * token is regenerated), a plain form post gets the usual redirect with a flash message.
     */
    public function saveBranding(): RedirectResponse|ResponseInterface
    {
        $error = $this->applyBranding();

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'ok'        => $error === null,
                'message'   => $error ?? lang('Messages.settings.saved'),
                'token'     => csrf_hash(),
                'tokenName' => csrf_token(),
                'logo'      => Branding::logoUrl(),
            ]);
        }

        return $error !== null ? $this->err($error) : $this->ok(lang('Messages.settings.brandingSaved'));
    }

    /** Writes the branding settings and the logo. Returns the first error message, or null when all was saved. */
    private function applyBranding(): ?string
    {
        $name = trim((string) $this->request->getPost('siteName'));

        if ($name === '' || mb_strlen($name) > 80) {
            return lang('Messages.settings.siteNameLength');
        }

        setting('Sync.siteName', $name);

        $color = trim((string) $this->request->getPost('accentColor'));

        if ($color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            return lang('Messages.settings.accentColor');
        }

        setting('Sync.accentColor', $color);

        $font = (string) $this->request->getPost('fontFamily');
        setting('Sync.fontFamily', array_key_exists($font, SyncConfig::FONTS) ? $font : '');

        $size = (int) $this->request->getPost('fontSize');
        setting('Sync.fontSize', $size >= 12 && $size <= 20 ? $size : 0);

        $lf = (string) $this->request->getPost('loginField');
        setting('Sync.loginField', in_array($lf, ['email', 'username', 'both'], true) ? $lf : 'email');

        /* the languages offered on the site: the ticked ones, at least one of the languages the package ships */
        $ticked = array_values(array_intersect(Branding::availableLocales(), (array) $this->request->getPost('locales')));

        if ($ticked === []) {
            return lang('Messages.settings.localesNone');
        }

        setting('Sync.locales', implode(',', $ticked));

        $logo = $this->request->getFile('logo');

        if ($logo !== null && $logo->isValid() && ! $logo->hasMoved()) {
            $maxMb = config('Sync')->maxImageMb;

            if ($logo->getSize() > $maxMb * 1024 * 1024) {
                return lang('Messages.settings.logoTooBig', [$maxMb]);
            }

            $ext = strtolower($logo->getClientExtension());

            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                return lang('Messages.settings.logoType');
            }

            $old = (string) setting('Sync.logo');

            if ($old !== '' && is_file(FCPATH . $old)) {
                @unlink(FCPATH . $old);
            }

            $name = 'sync-logo-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $logo->move(FCPATH . 'uploads', $name);
            setting('Sync.logo', 'uploads/' . $name);
        }

        return null;
    }

    public function removeLogo(): RedirectResponse
    {
        $old = (string) setting('Sync.logo');

        if ($old !== '' && is_file(FCPATH . $old)) {
            @unlink(FCPATH . $old);
        }

        setting('Sync.logo', '');

        return $this->ok(lang('Messages.settings.logoRemoved'));
    }

    /** Node identity: rename this node / the peer, or rotate the shared secret — written to `.env` only. */
    public function identity(): RedirectResponse
    {
        $node     = trim((string) $this->request->getPost('node'));
        $peerNode = trim((string) $this->request->getPost('peerNode'));
        $secret   = trim((string) $this->request->getPost('secret'));

        foreach (['node' => $node, 'peerNode' => $peerNode] as $label => $v) {
            if ($v !== '' && preg_match(NodeIdentity::NODE_PATTERN, $v) !== 1) {
                return $this->err(lang('Messages.settings.identityInvalid', [$label]));
            }
        }

        if ($secret !== '' && preg_match(NodeIdentity::SECRET_PATTERN, $secret) !== 1) {
            return $this->err(lang('Messages.settings.secretInvalid'));
        }

        $changes = [];
        $rename  = [];
        $cfg     = config('Sync');

        if ($node !== '' && $node !== $cfg->node) {
            $changes['sync.node'] = $node;
            $rename[$cfg->node]   = $node;
        }

        if ($peerNode !== '' && $peerNode !== $cfg->peerNode) {
            $changes['sync.peerNode'] = $peerNode;
            $rename[$cfg->peerNode]   = $peerNode;
        }

        if ($secret !== '') {
            $changes['sync.secret'] = $secret;
        }

        if ($changes === []) {
            return $this->ok(lang('Messages.settings.nothingToChange'));
        }

        try {
            EnvFile::default()->apply($changes);

            if ($rename !== []) {
                NodeIdentity::migrateNames(Kernel::instance()->db, $rename);
            }
        } catch (RuntimeException $e) {
            return $this->err($e->getMessage());
        }

        return $this->ok(lang('Messages.settings.identitySaved'));
    }

    /**
     * Whitelisted `.env` variables (EnvSettings::FIELDS). An AJAX request (the Synchronisation tab saves as it changes)
     * gets JSON with the result and the regenerated CSRF token; a plain post keeps the redirect and flash message.
     */
    public function saveEnv(): RedirectResponse|ResponseInterface
    {
        [$ok, $message] = $this->applyEnv();

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'ok'        => $ok,
                'message'   => $message,
                'token'     => csrf_hash(),
                'tokenName' => csrf_token(),
            ]);
        }

        return $ok ? $this->ok($message) : $this->err($message);
    }

    /** Validates and writes the posted variables. Returns [success, message]. */
    private function applyEnv(): array
    {
        $changes = [];

        foreach (EnvSettings::FIELDS as $key => $f) {
            $raw = $this->request->getPost(str_replace('.', '__', $key));

            if ($raw === null) {
                continue;
            }

            [$value, $error] = EnvSettings::check($key, (string) $raw, $f);

            if ($error !== null) {
                return [false, $error];
            }

            if ($value !== null || ! empty($f['required'])) {
                $changes[$key] = $value;
            }
        }

        if ($changes === []) {
            return [true, lang('Messages.settings.nothingToChange')];
        }

        $dbChanged = (bool) array_filter(array_keys($changes), static fn ($k) => str_starts_with($k, 'database.default.'));

        if ($dbChanged) {
            $current = EnvFile::default()->all();
            $error   = DbCheck::test(array_merge($current, $changes), (string) (auth()->user()->email ?? ''));

            if ($error !== null) {
                return [false, $error];
            }
        }

        EnvFile::default()->apply($changes);

        return [true, lang('Messages.settings.settingsSaved')];
    }

    public function telegramTest(): RedirectResponse
    {
        $n = Kernel::instance()->telegram();

        if (! $n->enabled()) {
            return $this->err(lang('Messages.settings.telegramNotConfigured'));
        }

        $node = config('Sync')->node;
        $ok   = $n->send("*ad-go/sync* test alert from `{$node}` ✓");

        return $ok
            ? $this->ok(lang('Messages.settings.telegramSent'))
            : $this->err(lang('Messages.settings.telegramFailed'));
    }

}
