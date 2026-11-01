<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Shared across every admin controller (conflicts, status, settings, users): the host-application hooks
 * (`Config\Sync::$menuView`/`$headView`, rendered into each page's `$menu`/`$head` view data) and the
 * "redirect back to this page's own list with a flash message" pattern every action ends with. Each class
 * using this trait must declare `protected string $configKey = 'xxxPath'` (the `Config\Sync` property that
 * holds its admin path, e.g. `'usersPath'`).
 */
trait AdminChrome
{

    private function base(): string
    {
        return trim((string) config('Sync')->{$this->configKey}, '/');
    }
    /** Settings tab this controller's pages belong to ('users', 'conflicts'); '' for Settings itself. */
    protected function tab(): string
    {
        return '';
    }

    /** URL of the Settings page, optionally on one of its tabs. */
    private function settingsUrl(string $tab = ''): string
    {
        return site_url(trim((string) config('Sync')->settingsPath, '/')) . ($tab !== '' ? '#' . $tab : '');
    }

    private function flash(string $type, string $message): RedirectResponse
    {
        return redirect()->to($this->settingsUrl($this->tab()))->with($type, $message);
    }

    private function err(string $message): RedirectResponse
    {
        return $this->flash('error', $message);
    }

    private function ok(string $message): RedirectResponse
    {
        return $this->flash('message', $message);
    }
}
