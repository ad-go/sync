<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use Throwable;

/**
 * Translated texts of the package (src/Language/<locale>/Sync.php), with a safe fallback.
 *
 * Inside a CodeIgniter application `line()` is `lang('Sync.<key>', $args)` in the CURRENT locale: the
 * request's locale on the web, the application's default locale (`Config\App::$defaultLocale`) in CLI
 * commands, queue workers and scheduled tasks. The package's namespace is discovered through Composer, so
 * no registration is needed. Outside the framework (the unit tests boot no application) the English file
 * is read directly, so every text stays available.
 */
final class Lang
{
    /** @var array<string, mixed>|null */
    private static ?array $english = null;

    /**
     * @param array<int, scalar|null> $args fills {0}, {1}, ... Always passed as strings, so ids are never locale-formatted.
     */
    public static function line(string $key, array $args = []): string
    {
        $args = array_map(static fn ($a): string => (string) $a, array_values($args));

        try {
            if (function_exists('lang') && class_exists(\Config\Services::class)) {
                $line = lang('Sync.' . $key, $args);

                if (is_string($line) && $line !== 'Sync.' . $key) {
                    return $line;
                }
            }
        } catch (Throwable) {
            // no application booted (or a broken language setup): English below
        }

        return self::english($key, $args);
    }

    /**
     * @param list<string> $args
     */
    private static function english(string $key, array $args): string
    {
        self::$english ??= require __DIR__ . '/../Language/en/Sync.php';
        $line = self::$english;

        foreach (explode('.', $key) as $part) {
            if (! is_array($line) || ! array_key_exists($part, $line)) {
                return 'Sync.' . $key;
            }

            $line = $line[$part];
        }

        if (! is_string($line)) {
            return 'Sync.' . $key;
        }

        foreach ($args as $i => $a) {
            $line = str_replace('{' . $i . '}', $a, $line);
        }

        return $line;
    }

    /** @var array<string, array<string, mixed>> */
    private static array $files = [];

    /**
     * Any package text by its full key, "File.some.key" (File = Sync, Messages, Env, Settings, ...).
     * Inside a CodeIgniter application it is lang() in the current locale; outside it the English file is read.
     *
     * @param array<int, scalar|null> $args
     */
    public static function text(string $full, array $args = []): string
    {
        $args = array_map(static fn ($a): string => (string) $a, array_values($args));

        try {
            if (function_exists('lang') && class_exists(\Config\Services::class)) {
                $line = lang($full, $args);

                if (is_string($line) && $line !== $full) {
                    return $line;
                }
            }
        } catch (Throwable) {
            // no application booted (or a broken language setup): English below
        }

        [$file, $key] = array_pad(explode('.', $full, 2), 2, '');
        if (! isset(self::$files[$file])) {
            $path = __DIR__ . '/../Language/en/' . $file . '.php';
            self::$files[$file] = is_file($path) ? require $path : [];
        }

        $line = self::$files[$file];
        foreach (explode('.', $key) as $part) {
            if (! is_array($line) || ! array_key_exists($part, $line)) {
                return $full;
            }
            $line = $line[$part];
        }

        if (! is_string($line)) {
            return $full;
        }

        $map = [];
        foreach ($args as $i => $value) {
            $map['{' . $i . '}'] = $value;
        }

        return strtr($line, $map);
    }
}
