<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * Inline SVG icons for SyncUI, vendored from Lucide (ISC license) under `src/Assets/syncui/icons`. Markup is
 * cached per request; size/colour are controlled by the `.icon` CSS class (`currentColor` stroke, `1em` box),
 * not by the file's own width/height attributes.
 */
final class Icon
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function svg(string $name): string
    {
        if (! isset(self::$cache[$name])) {
            $file = __DIR__ . '/../Assets/syncui/icons/' . basename($name) . '.svg';
            $raw  = is_file($file) ? (string) file_get_contents($file) : '';
            // Drop the licence comment and let our own `.icon` class (not the file's own attributes) size it.
            $raw = (string) preg_replace('/<!--.*?-->\s*/s', '', $raw);

            self::$cache[$name] = str_replace('class="lucide', 'class="icon lucide', $raw);
        }

        return self::$cache[$name];
    }
}
