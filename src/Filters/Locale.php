<?php

declare(strict_types=1);

namespace AdGo\Sync\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Picks the language of the page: the one chosen with the language switch (session, then the cookie for the next
 * visit), otherwise the first supported language of the browser, otherwise the default. Registered globally by the
 * package (Config\Registrar), so lang() in views, controllers and e-mails agree. Harmless when the application has
 * only one supported locale.
 */
class Locale implements FilterInterface
{
    public const COOKIE  = 'lang';
    public const SESSION = 'lang';

    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request instanceof IncomingRequest) {
            $request->setLocale(self::pick($request));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    public static function pick(IncomingRequest $request): string
    {
        $supported = \AdGo\Sync\Libraries\Branding::locales();
        $wanted    = (string) (session()->get(self::SESSION) ?? $request->getCookie(self::COOKIE) ?? '');

        if (in_array($wanted, $supported, true)) {
            return $wanted;
        }

        foreach (explode(',', (string) $request->getHeaderLine('Accept-Language')) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));

            if (in_array($code, $supported, true)) {
                return $code;
            }
        }

        return in_array(config('App')->defaultLocale, $supported, true) ? config('App')->defaultLocale : $supported[0];
    }
}
