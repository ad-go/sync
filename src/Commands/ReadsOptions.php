<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\CLI;

/**
 * CodeIgniter's CLI parser only understands `--name value`. Commands that document `--name=value` read both.
 */
trait ReadsOptions
{
    private function option(string $name): ?string
    {
        $value = CLI::getOption($name);

        if (is_string($value)) {
            return $value;
        }

        foreach (array_keys(CLI::getOptions()) as $key) {
            if (str_starts_with((string) $key, $name . '=')) {
                return substr((string) $key, strlen($name) + 1);
            }
        }

        return null;
    }

    private function hasFlag(string $name): bool
    {
        return CLI::getOption($name) !== null;
    }
}
