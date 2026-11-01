<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Filters\Locale;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * The language switch: GET lang/{code} remembers the choice (session and a one-year cookie) and goes back to the
 * page it came from. Unsupported codes are ignored.
 */
class LangController extends Controller
{
    public function switch(string $code): RedirectResponse
    {
        if (in_array($code, config('App')->supportedLocales, true)) {
            session()->set(Locale::SESSION, $code);
            $this->response->setCookie(Locale::COOKIE, $code, 86400 * 365, '', '/', '', false, true, 'Lax');
        }

        $back = (string) $this->request->getServer('HTTP_REFERER');
        $home = rtrim(base_url(), '/');

        return redirect()->to(str_starts_with($back, $home . '/') || $back === $home ? $back : '/');
    }
}
