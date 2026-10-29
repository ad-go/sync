<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * Site branding (name, logo, font, accent colour) for the package's own admin pages (Conflicts, Status,
 * Settings, Users) — stored via the `codeigniter4/settings` package under `Config\Sync`'s own properties
 * (`$siteName`, `$logo`, `$fontFamily`, `$fontSize`, `$accentColor`), editable from the Settings page.
 * `css()` overrides the admin chrome's `--acc`/`--acc-d`/`--font-family`/`--font-size` CSS variables, the same
 * pattern SyncUI and the host application's own chrome used before this was folded into the package.
 */
final class Branding
{
    public static function siteName(): string
    {
        $v = (string) setting('Sync.siteName');

        if ($v !== '') {
            return $v;
        }

        // Fall back to the app's hostname so TOTP issuers and page titles are always meaningful.
        $host = (string) parse_url((string) base_url(), PHP_URL_HOST);

        return $host !== '' ? $host : 'Sync';
    }

    public static function logoUrl(): string
    {
        $logo = (string) setting('Sync.logo');

        return $logo === '' ? '' : base_url($logo);
    }

    /** CSS overriding the chrome's `:root` accent/font variables when the admin changed them from the defaults. */
    public static function css(): string
    {
        $out = '';
        $c   = (string) setting('Sync.accentColor');

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1) {
            $out .= ':root{--acc:' . $c . ';--acc-d:' . self::darkenHex($c, 0.2) . '}'
                . '@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--acc:' . self::lightenHex($c, 0.15) . ";--acc-d:{$c}}}"
                . ':root[data-theme="dark"]{--acc:' . self::lightenHex($c, 0.15) . ";--acc-d:{$c}}";
        }

        $font = \Config\Sync::FONTS[(string) setting('Sync.fontFamily')] ?? null;

        if ($font !== null) {
            $out .= ":root{--font-family:{$font}}";
        }

        $size = (int) setting('Sync.fontSize');

        if ($size >= 12 && $size <= 20) {
            $out .= ":root{--font-size:{$size}px}";
        }

        return $out;
    }

    /** `#rrggbb` moved towards black by `$amount` (0-1) — the hover/dark shade of a custom accent colour. */
    public static function darkenHex(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', (int) ($r * (1 - $amount)), (int) ($g * (1 - $amount)), (int) ($b * (1 - $amount)));
    }

    /** `#rrggbb` moved towards white by `$amount` (0-1) — the dark-mode shade of a custom accent colour. */
    public static function lightenHex(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', (int) ($r + (255 - $r) * $amount), (int) ($g + (255 - $g) * $amount), (int) ($b + (255 - $b) * $amount));
    }

}
