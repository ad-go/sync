<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use AdGo\Sync\Libraries\Assets;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Makes the admin pages self-hosted: downloads the pinned front-end files (jQuery, Bootstrap, Bootstrap Icons) and,
 * if you give it your licensed DataTables Editor folder, copies the Editor files the Users page needs.
 */
class SyncAssets extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:assets';
    protected $description = 'Downloads the admin front-end assets (pinned, SHA-256 checked) into public/, and copies the licensed DataTables Editor files from --dte.';
    protected $usage       = 'sync:assets [--dte FOLDER]';
    protected $arguments   = [];
    protected $options     = [
        '--dte' => 'Folder with your licensed DataTables Editor bundle: datatables.min.js, datatables.min.css and key.bak (or key.js)',
    ];

    public function run(array $params)
    {
        $root = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR;

        CLI::write('Public assets (' . count(Assets::PUBLIC) . ' files):');
        $failed = 0;

        foreach (Assets::downloadPublic($root) as $path => $status) {
            $color = str_starts_with($status, 'failed') ? 'red' : ($status === 'downloaded' ? 'green' : 'white');
            $failed += str_starts_with($status, 'failed') ? 1 : 0;
            CLI::write('  ' . str_pad($status, 11) . ' public/' . $path, $color);
        }

        CLI::write('Fonts (' . count(Assets::FONT_FACES) . ' faces): assets/fonts/fonts.css ' . (Assets::writeFontsCss($root) ? 'written' : 'not written (no font file present)'));

        $dte = CLI::getOption('dte');

        if ($dte !== null) {
            $dest = $root . trim((string) config('Sync')->dteAssetsUrl, '/') . DIRECTORY_SEPARATOR;
            CLI::write('DataTables Editor from ' . $dte . ':');

            foreach (Assets::copyEditor((string) $dte, $dest) as $file => $status) {
                $failed += $status === 'missing' ? 1 : 0;
                CLI::write('  ' . str_pad($status, 11) . ' public/' . trim((string) config('Sync')->dteAssetsUrl, '/') . '/' . $file, $status === 'missing' ? 'red' : 'green');
            }
        } elseif (! is_file($root . trim((string) config('Sync')->dteAssetsUrl, '/') . '/datatables.min.js')) {
            CLI::write('DataTables Editor is not in place: the Users page stays empty until you run `php spark sync:assets --dte <folder>`.', 'yellow');
        }

        return $failed === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
