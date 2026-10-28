<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Support;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/** Redirects legacy /admin/status to /admin/conflicts; keeps the static runSync() used by ConflictAdmin. */
class StatusAdmin extends Controller
{
    use AdminChrome;

    protected string $configKey = 'statusPath';

    /** Redirect legacy /admin/status → /admin/conflicts (status is now integrated there). */
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url(trim((string) config('Sync')->conflictsPath, '/')));
    }

    /** Runs a pull cycle right now (scan + pull + reconcile), the same work `sync:pull` does on its schedule. */
    public static function runSync(): string
    {
        $kernel = Kernel::instance();
        $result = null;

        $ran = Support::exclusive('pull', static function () use ($kernel, &$result): void {
            try {
                $kernel->scanner()->scan();
                $r      = $kernel->puller()->run();
                $result = 'ok: pulled ' . $r['pulled'] . ' change(s)';
            } catch (Throwable $e) {
                $result = 'error: ' . $e->getMessage();
            }
        });

        return $ran ? $result : 'error: another sync is already running';
    }
}
