<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\Response;
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

    /** Extension => Content-Type, for the vendored static files under Assets/syncui served by self::asset(). */
    private const ASSET_MIME = [
        'js'   => 'text/javascript; charset=UTF-8',
        'mjs'  => 'text/javascript; charset=UTF-8',
        'css'  => 'text/css; charset=UTF-8',
        'svg'  => 'image/svg+xml',
        'map'  => 'application/json',
        'wasm' => 'application/wasm',
    ];

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

        return view((string) config('Sync')->syncUiSqlView, $this->chrome('sql', [
            'title'  => Lang::line('syncUi.sqlTitle'),
            'sql'    => $sqlText,
            'result' => $result,
            'error'  => $error,
            'action' => site_url($this->base() . '/sql'),
        ]));
    }

    /**
     * A gallery of widgets with no natural home in a table/row editor (WYSIWYG, signature pad, image editor,
     * PDF preview, camera barcode/QR scan, text-to-speech) — demos only, nothing here is saved.
     */
    public function showcase(): string
    {
        return view((string) config('Sync')->syncUiShowcaseView, $this->chrome('showcase', [
            'title' => Lang::line('syncUi.showcase'),
        ]));
    }

    /**
     * Serves a vendored static file from Assets/syncui (see its NOTICE.md) — the package ships its own copies
     * of jQuery/DataTables/Select2/etc. so the host application never has to publish anything to its own
     * public/, and the page never fetches a third-party CDN at runtime.
     *
     * The route (`assets/(:any)`) passes each slash-separated piece of the match as its own positional
     * argument rather than one combined string, so the path is rejoined from `$segments`.
     */
    public function asset(string ...$segments): ResponseInterface
    {
        $path = ltrim(implode('/', $segments), '/');
        $root = realpath(__DIR__ . '/../Assets/syncui');
        $full = $root === false ? false : realpath($root . '/' . $path);

        if ($path === '' || $root === false || $full === false || ! str_starts_with($full, $root) || ! is_file($full)) {
            return $this->response->setStatusCode(404);
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        /** @var Response $response */
        $response = $this->response;

        return $response
            ->setHeader('Cache-Control', 'private, max-age=31536000, immutable')
            ->setContentType(self::ASSET_MIME[$ext] ?? 'application/octet-stream')
            ->setBody((string) file_get_contents($full));
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
            'asset'         => fn (string $p): string => site_url($this->base() . '/assets/' . $p),
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
