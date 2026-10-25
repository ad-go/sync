<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Support;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Sync status page: node/peer identity, journal cursor, pending conflicts, lock state, and a manual "sync now"
 * / "take or release the lock" action. Routes sit behind Shield's `session` + `permission` filters.
 */
class StatusAdmin extends Controller
{
    use AdminChrome;

    private Kernel $kernel;

    public function __construct()
    {
        $this->kernel = Kernel::instance();
    }

    public function index(): string
    {
        helper('form');
        $k  = $this->kernel;
        $db = $k->db;

        return view((string) config('Sync')->statusIndexView, [
            'node'      => $k->opt->node,
            'peerNode'  => $k->opt->peerNode,
            'peerUrl'   => $k->opt->peerUrl,
            'passive'   => $k->opt->passive,
            'cursor'    => $k->meta->cursor($k->opt->peerNode),
            'conflicts' => (int) $db->table('sync_conflicts')->where('status', 'pending')->countAllResults(),
            'journal'   => (int) $db->table('sync_changes')->countAllResults(),
            'authLock'  => $k->locks()->describe('auth'),
            'cronLock'  => $k->locks()->describe('cron'),
            'menu'      => $this->menu('status'),
            'head'      => $this->head(),
            'base'      => $this->base(),
        ]);
    }

    public function syncNow(): RedirectResponse
    {
        $result = self::runSync();

        return $this->flash(str_starts_with($result, 'ok') ? 'message' : 'error', $result);
    }

    public function lock(): RedirectResponse
    {
        $action = (string) $this->request->getPost('action');

        if ($action === 'take') {
            $r = $this->kernel->locks()->acquire('auth', true);
        } elseif ($action === 'release') {
            $this->kernel->locks()->release('auth');
            $r = ['ok' => true];
        } else {
            $r = ['ok' => false, 'reason' => 'unknown action'];
        }

        return $this->flash($r['ok'] ? 'message' : 'error', $r['ok'] ? 'Done.' : ('Could not change the lock: ' . ($r['reason'] ?? '?')));
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

    private function base(): string
    {
        return trim((string) config('Sync')->statusPath, '/');
    }
}
