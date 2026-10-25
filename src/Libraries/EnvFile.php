<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use RuntimeException;

/**
 * Reads and edits the `.env` file line by line: comments, order and every variable we do not touch stay exactly
 * as they were. Every write makes a backup first (`.env.bak-<UTC time>`, the newest KEEP are kept, mode 600) and
 * replaces the file atomically, so a half-written `.env` can never be read by a request.
 */
class EnvFile
{
    public const KEEP = 8;

    public function __construct(private readonly string $path)
    {
    }

    public static function default(): self
    {
        return new self(ROOTPATH . '.env');
    }

    /**
     * Raw value of every `KEY = value` line (quotes removed), last occurrence wins like in DotEnv.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $out = [];

        foreach ($this->lines() as $l) {
            if (preg_match('/^\s*([A-Za-z0-9_.]+)\s*=\s*(.*?)\s*$/', $l, $m)) {
                $out[$m[1]] = self::unquote($m[2]);
            }
        }

        return $out;
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Applies several changes at once: `null` removes the variable, a string sets it. Nothing is written when
     * nothing changes.
     *
     * @param array<string, string|null> $changes
     *
     * @return list<string> the keys that actually changed
     */
    public function apply(array $changes): array
    {
        $lines   = $this->lines();
        $current = $this->all();
        $changed = [];

        foreach ($changes as $key => $value) {
            if (! preg_match('/^[A-Za-z0-9_.]+$/', $key)) {
                throw new RuntimeException('Invalid variable name.');
            }

            if ($value !== null && ! self::safe($value)) {
                throw new RuntimeException("The value of {$key} contains characters that cannot be stored in .env.");
            }

            if (($current[$key] ?? null) === $value) {
                continue;
            }

            $changed[] = $key;
            $at        = null;

            foreach ($lines as $i => $l) {
                if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $l)) {
                    $at = $i; // the last one wins, as for DotEnv
                }
            }

            if ($value === null) {
                foreach ($lines as $i => $l) {
                    if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $l)) {
                        unset($lines[$i]);
                    }
                }

                continue;
            }

            $line = $key . ' = ' . self::quote($value);

            if ($at === null) {
                $lines[] = $line;
            } else {
                $lines[$at] = $line;
            }
        }

        if ($changed === []) {
            return [];
        }

        $this->write(implode("\n", array_values($lines)) . "\n");

        return $changed;
    }

    /** @return list<string> backup file names, newest first */
    public function backups(): array
    {
        $names = array_map('basename', glob($this->path . '.bak-*') ?: []);
        rsort($names);

        return $names;
    }

    /** Puts a backup back (the current file is backed up first). */
    public function restore(string $name): void
    {
        if (! in_array($name, $this->backups(), true)) {
            throw new RuntimeException('Unknown backup.');
        }

        $this->write((string) file_get_contents(dirname($this->path) . '/' . $name));
    }

    // ------------------------------------------------------------------ internals

    /** @return list<string> */
    private function lines(): array
    {
        $raw = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        return $raw === '' ? [] : explode("\n", rtrim(str_replace("\r\n", "\n", $raw), "\n"));
    }

    private function write(string $content): void
    {
        $dir  = dirname($this->path);
        $mode = is_file($this->path) ? (fileperms($this->path) & 0777) : 0600;

        if (is_file($this->path)) {
            $bak = $this->path . '.bak-' . gmdate('Ymd-His');
            copy($this->path, $bak);
            @chmod($bak, 0600);

            foreach (array_slice($this->backups(), self::KEEP) as $old) {
                @unlink($dir . '/' . $old);
            }
        }

        $tmp = $this->path . '.tmp-' . bin2hex(random_bytes(4));

        if (file_put_contents($tmp, $content) === false) {
            throw new RuntimeException('The .env file cannot be written (permissions?).');
        }

        @chmod($tmp, $mode);

        if (! @rename($tmp, $this->path)) {
            @unlink($tmp);

            throw new RuntimeException('The .env file cannot be replaced (permissions?).');
        }
    }

    private static function unquote(string $v): string
    {
        if (strlen($v) >= 2 && ($v[0] === "'" || $v[0] === '"') && $v[strlen($v) - 1] === $v[0]) {
            return substr($v, 1, -1);
        }

        return $v;
    }

    /** DotEnv expands `${VAR}`; quotes, line breaks and NUL cannot be represented safely. */
    private static function safe(string $v): bool
    {
        return ! preg_match('/[\r\n\0\'"\\\\]/', $v) && ! str_contains($v, '${') && ! str_contains($v, '#');
    }

    private static function quote(string $v): string
    {
        return preg_match('~^[A-Za-z0-9_.:/@+-]*$~', $v) === 1 && $v !== '' ? $v : "'" . $v . "'";
    }
}
