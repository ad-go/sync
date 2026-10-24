<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Lang;
use AdGo\Sync\Libraries\SyncUiBrowser;

/**
 * SyncUI: an Adminer-style browser/editor for the application's own database (Config\Sync::$dbGroup) — list
 * tables, browse/filter/sort/edit/delete rows, run arbitrary SQL. Routes sit behind Shield's `session` and
 * `permission` filters (see Config/Routes.php), same as ConflictAdmin.
 */
class SyncUiAdmin extends Controller
{
    private SyncUiBrowser $browser;

    public function __construct()
    {
        $this->browser = new SyncUiBrowser(Kernel::instance()->db);
    }

    /** The application's menu (Config\Sync::$menuView), or nothing. */
    private function menu(): string
    {
        $view = (string) config('Sync')->menuView;

        return $view === '' ? '' : view($view, ['active' => 'syncUi']);
    }

    /** Extra markup for <head> (Config\Sync::$headView), or nothing. */
    private function head(): string
    {
        $view = (string) config('Sync')->headView;

        return $view === '' ? '' : view($view);
    }

    public function index(): string
    {
        return view((string) config('Sync')->syncUiIndexView, [
            'tables' => $this->browser->tables(),
            'base'   => $this->base(),
            'sql'    => site_url($this->base() . '/sql'),
            'menu'   => $this->menu(),
            'head'   => $this->head(),
        ]);
    }

    public function table(string $table): string|RedirectResponse
    {
        if (! $this->browser->exists($table)) {
            return redirect()->to(site_url($this->base()))->with('error', Lang::line('flash.unknownTable'));
        }

        helper('form');
        $req     = $this->request;
        $page    = max(1, (int) $req->getGet('page'));
        $perPage = 25;
        $sort    = (string) ($req->getGet('sort') ?? '');
        $dir     = (string) ($req->getGet('dir') ?? 'asc');
        /** @var array<string, string> $filters */
        $filters = array_filter((array) ($req->getGet('f') ?? []), static fn ($v): bool => $v !== '');

        $data = $this->browser->rows($table, $page, $perPage, $sort === '' ? null : $sort, $dir, $filters);

        $editKey = (string) ($req->getGet('edit') ?? '');
        $editPk  = $editKey === '' || $editKey === 'new' ? [] : $this->decodeKey($editKey);
        $editRow = $editKey === 'new' ? [] : ($editPk === [] ? null : ($this->browser->find($table, $editPk) ?? []));

        return view((string) config('Sync')->syncUiTableView, [
            'table'     => $table,
            'columns'   => $data['columns'],
            'rows'      => $data['rows'],
            'total'     => $data['total'],
            'page'      => $page,
            'perPage'   => $perPage,
            'sort'      => $sort,
            'dir'       => $dir,
            'filters'   => $filters,
            'pk'        => $this->browser->primaryKey($table),
            'editing'   => $editKey !== '',
            'editPk'    => $editPk,
            'editRow'   => $editRow,
            'base'      => $this->base(),
            'action'    => site_url($this->base() . '/' . $table . '/save'),
            'delAction' => site_url($this->base() . '/' . $table . '/delete'),
            'self'      => site_url($this->base() . '/' . $table),
            'menu'      => $this->menu(),
            'head'      => $this->head(),
        ]);
    }

    public function save(string $table): RedirectResponse
    {
        if (! $this->browser->exists($table)) {
            return redirect()->to(site_url($this->base()))->with('error', Lang::line('flash.unknownTable'));
        }

        $values = (array) $this->request->getPost('v');
        $pk     = (array) $this->request->getPost('pk');

        try {
            if ($pk === []) {
                $this->browser->insert($table, $values);
                $message = Lang::line('flash.inserted');
            } else {
                $this->browser->update($table, $pk, $values);
                $message = Lang::line('flash.updated');
            }
        } catch (Throwable $e) {
            return redirect()->to(site_url($this->base() . '/' . $table))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url($this->base() . '/' . $table))->with('message', $message);
    }

    public function delete(string $table): RedirectResponse
    {
        if (! $this->browser->exists($table)) {
            return redirect()->to(site_url($this->base()))->with('error', Lang::line('flash.unknownTable'));
        }

        $pk = (array) $this->request->getPost('pk');

        try {
            $this->browser->delete($table, $pk);
        } catch (Throwable $e) {
            return redirect()->to(site_url($this->base() . '/' . $table))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url($this->base() . '/' . $table))->with('message', Lang::line('flash.deleted'));
    }

    public function sql(): string
    {
        helper('form');
        $sqlText = (string) ($this->request->getPost('sql') ?? '');
        $result  = null;
        $error   = null;

        if ($sqlText !== '') {
            try {
                $result = $this->browser->run($sqlText);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view((string) config('Sync')->syncUiSqlView, [
            'sql'    => $sqlText,
            'result' => $result,
            'error'  => $error,
            'action' => site_url($this->base() . '/sql'),
            'back'   => site_url($this->base()),
            'menu'   => $this->menu(),
            'head'   => $this->head(),
        ]);
    }

    private function base(): string
    {
        return trim((string) config('Sync')->syncUiPath, '/');
    }

    /** @return array<string, mixed> */
    private function decodeKey(string $key): array
    {
        $decoded = json_decode($key, true);

        return is_array($decoded) ? $decoded : [];
    }
}
