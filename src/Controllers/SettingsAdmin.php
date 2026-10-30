<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\Branding;
use AdGo\Sync\Libraries\DbCheck;
use AdGo\Sync\Libraries\EnvFile;
use AdGo\Sync\Libraries\EnvSettings;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\NodeIdentity;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Sync as SyncConfig;
use RuntimeException;

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
            $fields[$key] = $f + ['value' => in_array($f['type'], ['secret'], true) ? '' : ($env[$key] ?? (isset($f['default']) ? (string) $f['default'] : '')), 'set' => isset($env[$key]) && $env[$key] !== ''];
        }

        return view((string) $cfg->settingsIndexView, [
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

    /** Toggle licensed DTE mode (Sync.pay setting). */
    public function dteToggle(): RedirectResponse
    {
        $current = (bool) (setting('Sync.pay') ?? config('Sync')->pay);
        setting('Sync.pay', ! $current);

        $ref = $this->request->getServer('HTTP_REFERER');
        return redirect()->to($ref ?: site_url($this->base()));
    }

    /** Site branding: name, logo upload, accent colour, font. */
    public function saveBranding(): RedirectResponse
    {
        $name = trim((string) $this->request->getPost('siteName'));

        if ($name === '' || mb_strlen($name) > 80) {
            return $this->err('The site name must be 1-80 characters.');
        }

        setting('Sync.siteName', $name);

        $color = trim((string) $this->request->getPost('accentColor'));

        if ($color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            return $this->err('The accent colour must be a #rrggbb value.');
        }

        setting('Sync.accentColor', $color);

        $font = (string) $this->request->getPost('fontFamily');
        setting('Sync.fontFamily', array_key_exists($font, SyncConfig::FONTS) ? $font : '');

        $size = (int) $this->request->getPost('fontSize');
        setting('Sync.fontSize', $size >= 12 && $size <= 20 ? $size : 0);

        $lf = (string) $this->request->getPost('loginField');
        setting('Sync.loginField', in_array($lf, ['email', 'username', 'both'], true) ? $lf : 'email');

        $logo = $this->request->getFile('logo');

        if ($logo !== null && $logo->isValid() && ! $logo->hasMoved()) {
            $maxMb = config('Sync')->maxImageMb;

            if ($logo->getSize() > $maxMb * 1024 * 1024) {
                return $this->err("The logo must be at most {$maxMb} MiB.");
            }

            $ext = strtolower($logo->getClientExtension());

            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                return $this->err('The logo must be png, jpg, svg or webp.');
            }

            $old = (string) setting('Sync.logo');

            if ($old !== '' && is_file(FCPATH . $old)) {
                @unlink(FCPATH . $old);
            }

            $name = 'sync-logo-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $logo->move(FCPATH . 'uploads', $name);
            setting('Sync.logo', 'uploads/' . $name);
        }

        return $this->ok('Branding saved.');
    }

    public function removeLogo(): RedirectResponse
    {
        $old = (string) setting('Sync.logo');

        if ($old !== '' && is_file(FCPATH . $old)) {
            @unlink(FCPATH . $old);
        }

        setting('Sync.logo', '');

        return $this->ok('Logo removed.');
    }

    /** Node identity: rename this node / the peer, or rotate the shared secret — written to `.env` only. */
    public function identity(): RedirectResponse
    {
        $node     = trim((string) $this->request->getPost('node'));
        $peerNode = trim((string) $this->request->getPost('peerNode'));
        $secret   = trim((string) $this->request->getPost('secret'));

        foreach (['node' => $node, 'peerNode' => $peerNode] as $label => $v) {
            if ($v !== '' && preg_match(NodeIdentity::NODE_PATTERN, $v) !== 1) {
                return $this->err("Invalid {$label}: lowercase letters, digits, - and _ only.");
            }
        }

        if ($secret !== '' && preg_match(NodeIdentity::SECRET_PATTERN, $secret) !== 1) {
            return $this->err('Invalid secret: 32-128 letters/digits.');
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
            return $this->ok('Nothing to change.');
        }

        try {
            EnvFile::default()->apply($changes);

            if ($rename !== []) {
                NodeIdentity::migrateNames(Kernel::instance()->db, $rename);
            }
        } catch (RuntimeException $e) {
            return $this->err($e->getMessage());
        }

        return $this->ok('Identity saved. Apply the matching change on the peer node too.');
    }

    /** Whitelisted `.env` variables (EnvSettings::FIELDS); a database change is tried first, before it is written. */
    public function saveEnv(): RedirectResponse
    {
        $changes = [];

        foreach (EnvSettings::FIELDS as $key => $f) {
            $raw = $this->request->getPost(str_replace('.', '__', $key));

            if ($raw === null) {
                continue;
            }

            [$value, $error] = EnvSettings::check($key, (string) $raw, $f);

            if ($error !== null) {
                return $this->err($error);
            }

            if ($value !== null || ! empty($f['required'])) {
                $changes[$key] = $value;
            }
        }

        if ($changes === []) {
            return $this->ok('Nothing to change.');
        }

        $dbChanged = (bool) array_filter(array_keys($changes), static fn ($k) => str_starts_with($k, 'database.default.'));

        if ($dbChanged) {
            $current = EnvFile::default()->all();
            $error   = DbCheck::test(array_merge($current, $changes), (string) (auth()->user()->email ?? ''));

            if ($error !== null) {
                return $this->err($error);
            }
        }

        EnvFile::default()->apply($changes);

        return $this->ok('Settings saved.');
    }

    public function telegramTest(): RedirectResponse
    {
        $n = Kernel::instance()->telegram();

        if (! $n->enabled()) {
            return $this->err('Telegram not configured — set the bot token and chat id first.');
        }

        $node = config('Sync')->node;
        $ok   = $n->send("*ad-go/sync* test alert from `{$node}` ✓");

        return $ok
            ? $this->ok('Test message sent.')
            : $this->err('Send failed — check the token and chat id in the server log.');
    }

}
