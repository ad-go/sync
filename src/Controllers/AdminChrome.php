<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Shared across every admin controller (conflicts, status, settings, users): the host-application hooks
 * (`Config\Sync::$menuView`/`$headView`, rendered into each page's `$menu`/`$head` view data) and the
 * "redirect back to this page's own list with a flash message" pattern every action ends with. Every class
 * using this trait must define a `base(): string` method (each controller's own admin path, e.g.
 * `config('Sync')->usersPath`).
 */
trait AdminChrome
{
    private function menu(string $active): string
    {
        $view = (string) config('Sync')->menuView;

        return $view === '' ? '' : view($view, ['active' => $active]);
    }

    private function head(): string
    {
        $view = (string) config('Sync')->headView;

        return $view === '' ? '' : view($view);
    }

    private function flash(string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url($this->base()))->with($type, $message);
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
