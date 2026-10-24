<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Lang;
use AdGo\Sync\Libraries\SyncUiBrowser;

/**
 * SyncUI: an Adminer-style browser/editor for the application's own database (Config\Sync::$dbGroup) — list
 * tables, browse/filter/sort/edit/delete rows, run arbitrary SQL. Routes sit behind Shield's `session` and
 * `permission` filters (see Config/Routes.php), same as ConflictAdmin.
 *
 * SyncUI draws its own chrome (top bar, left sidebar of tables, footer — see Views/syncui/_layout.php) rather
 * than depending on the host application's layout; Config\Sync::$menuView/$headView still inject the host's
 * own navigation/styles alongside it, for an application that wants a way back out of SyncUI.
 */
class SyncUiAdmin extends Controller
{
    private const SESSION_LOCALE_KEY = 'syncUiLocale';

    private SyncUiBrowser $browser;

    public function __construct()
    {
        $this->browser = new SyncUiBrowser(Kernel::instance()->db);
    }

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'auth']);
        $this->applySessionLocale();
    }

    public function index(): string
    {
        return view((string) config('Sync')->syncUiIndexView, $this->chrome('', [
            'title' => Lang::line('syncUi.title'),
        ]));
    }

    public function table(string $table): string|RedirectResponse
    {
        if (! $this->browser->exists($table)) {
            return redirect()->to(site_url($this->base()))->with('error', Lang::line('flash.unknownTable'));
        }

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

        return view((string) config('Sync')->syncUiTableView, $this->chrome($table, [
            'title'     => $table,
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
            'action'    => site_url($this->base() . '/' . $table . '/save'),
            'delAction' => site_url($this->base() . '/' . $table . '/delete'),
            'self'      => site_url($this->base() . '/' . $table),
        ]));
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

        return view((string) config('Sync')->syncUiSqlView, $this->chrome('', [
            'title'  => Lang::line('syncUi.sqlTitle'),
            'sql'    => $sqlText,
            'result' => $result,
            'error'  => $error,
            'action' => site_url($this->base() . '/sql'),
        ]));
    }

    /** Sets the SyncUI chrome's language for this visitor and sends them back where they came from. */
    public function locale(string $locale): RedirectResponse
    {
        $locales = array_keys((array) config('Sync')->syncUiLocales);
        $back    = (string) ($this->request->getGet('back') ?? '');
        $target  = $back !== '' ? $back : site_url($this->base());

        if (in_array($locale, $locales, true)) {
            session()->set(self::SESSION_LOCALE_KEY, $locale);
        }

        return redirect()->to($target);
    }

    /** Applies the visitor's stored SyncUI language (if any and still valid) to the current request. */
    private function applySessionLocale(): void
    {
        $locales = array_keys((array) config('Sync')->syncUiLocales);
        $chosen  = (string) (session()->get(self::SESSION_LOCALE_KEY) ?? '');

        if ($chosen !== '' && in_array($chosen, $locales, true)) {
            service('language')->setLocale($chosen);
            $this->request->setLocale($chosen);
        }
    }

    /**
     * Common chrome data every SyncUI view needs: top bar (language, user menu), sidebar (table list), footer,
     * plus the host application's own menu/head injections. Merged with the view's own data.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function chrome(string $activeTable, array $extra): array
    {
        $cfg      = config('Sync');
        $menuView = (string) $cfg->menuView;
        $headView = (string) $cfg->headView;

        $user     = auth()->user();
        $username = $user !== null ? (string) ($user->username ?? $user->email ?? auth()->id()) : '';

        return $extra + [
            'base'          => $this->base(),
            'activeTable'   => $activeTable,
            'sidebarTables' => $this->browser->tables(),
            'locales'       => (array) $cfg->syncUiLocales,
            'activeLocale'  => service('language')->getLocale(),
            'currentUrl'    => (string) $this->request->getUri(),
            'username'      => $username,
            'profileUrl'    => (string) $cfg->profileUrl,
            'logoutUrl'     => $this->logoutUrl(),
            'menu'          => $menuView === '' ? '' : view($menuView, ['active' => 'syncUi']),
            'head'          => $headView === '' ? '' : view($headView),
        ];
    }

    /** Shield's named `logout` route, falling back to the conventional `/logout` path if it isn't registered. */
    private function logoutUrl(): string
    {
        try {
            return url_to('logout');
        } catch (Throwable) {
            return site_url('logout');
        }
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
