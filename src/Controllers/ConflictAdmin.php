<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Lang;
use AdGo\Sync\Libraries\Support;

/**
 * Admin UI for quarantined conflicts: the source of truth for decisions
 * (Telegram is only a shortcut). Routes sit behind Shield's `session` and
 * `permission` filters (see Config/Routes.php).
 */
class ConflictAdmin extends Controller
{
    private Kernel $kernel;

    public function __construct()
    {
        $this->kernel = Kernel::instance();
    }

    /** The application's menu (Config\Sync::$menuView), or nothing. */
    private function menu(): string
    {
        $view = (string) config('Sync')->menuView;

        return $view === '' ? '' : view($view, ['active' => 'conflicts']);
    }

    /** Extra markup for <head> (Config\Sync::$headView), or nothing. */
    private function head(): string
    {
        $view = (string) config('Sync')->headView;

        return $view === '' ? '' : view($view);
    }

    public function index(): string
    {
        helper('form');
        $db = $this->kernel->db;

        return view((string) config('Sync')->conflictsIndexView, [
            'node'     => $this->kernel->opt->node,
            'pending'  => $db->table('sync_conflicts')->where('status', 'pending')->orderBy('detected_at', 'DESC')->get()->getResultArray(),
            'recent'   => $db->table('sync_conflicts')->where('status !=', 'pending')->orderBy('detected_at', 'DESC')->limit(50)->get()->getResultArray(),
            'lockHeld' => $this->kernel->locks()->holder('auth'),
            'menu'     => $this->menu(),
            'head'     => $this->head(),
        ]);
    }

    public function show(int $id): string|RedirectResponse
    {
        helper('form');
        $c = $this->conflict($id);

        if ($c === null) {
            return redirect()->to(site_url($this->base()))->with('error', Lang::line('flash.unknownConflict'));
        }

        $local  = Support::unpack($c['local_blob']);
        $remote = Support::unpack($c['remote_blob']);
        $base   = $c['base_blob'] === null ? null : Support::unpack($c['base_blob']);
        $fields = array_values(array_unique(array_merge(array_keys($local ?? []), array_keys($remote ?? []))));
        sort($fields);

        return view((string) config('Sync')->conflictsShowView, [
            'c'         => $c,
            'local'     => $local,
            'remote'    => $remote,
            'base'      => $base,
            'fields'    => $fields,
            'divergent' => $c['fields'] === '*' ? $fields : array_filter(explode(',', (string) $c['fields'])),
            'action'    => site_url($this->base() . '/' . $id . '/resolve'),
            'back'      => site_url($this->base()),
            'menu'      => $this->menu(),
            'head'      => $this->head(),
        ]);
    }

    public function resolve(int $id): RedirectResponse
    {
        $c = $this->conflict($id);

        if ($c === null) {
            return redirect()->to(site_url($this->base()))->with('error', Lang::line('flash.unknownConflict'));
        }

        $action = (string) $this->request->getPost('action');
        $merged = null;
        $choice = $action;

        if ($action === 'merge') {
            $local  = Support::unpack($c['local_blob']) ?? [];
            $remote = Support::unpack($c['remote_blob']) ?? [];
            $pick   = (array) $this->request->getPost('pick');
            $custom = (array) $this->request->getPost('custom');
            $merged = [];

            foreach (array_unique(array_merge(array_keys($local), array_keys($remote))) as $f) {
                $merged[$f] = match ($pick[$f] ?? 'local') {
                    'remote' => $remote[$f] ?? null,
                    'custom' => ($custom[$f] ?? '') === '' ? null : $custom[$f],
                    default  => $local[$f] ?? null,
                };
            }
        } elseif ($action === 'keep_local') {
            $choice = $c['local_node'];
        } elseif ($action === 'keep_remote') {
            $choice = $c['remote_node'];
        }

        helper('auth');
        $user = auth()->loggedIn() ? (string) (auth()->user()->username ?? auth()->id()) : 'admin';
        $res  = $this->kernel->resolver()->resolve($id, $choice, $merged, $user);

        if (! $res['ok']) {
            return redirect()->back()->with('error', $res['error'] ?? Lang::line('flash.couldNotResolve'));
        }

        $msg = Lang::line('flash.resolved', [$id]) . (($res['propagated'] ?? true) ? '' : ' ' . Lang::line('flash.notPropagated'));

        return redirect()->to(site_url($this->base()))->with('message', $msg);
    }

    /** @return array<string, mixed>|null */
    private function conflict(int $id): ?array
    {
        return $this->kernel->db->table('sync_conflicts')->where('id', $id)->get()->getRowArray();
    }

    private function base(): string
    {
        return trim((string) config('Sync')->conflictsPath, '/');
    }
}
