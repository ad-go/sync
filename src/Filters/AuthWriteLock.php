<?php

declare(strict_types=1);

namespace AdGo\Sync\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Lang;
use Throwable;

/**
 * Guards the routes that create / edit / delete users and groups with an automatic EDIT LEASE.
 *
 *     // form pages and saves: starting to edit takes the lease and publishes the position to the peer
 *     $routes->group('admin/users', ['filter' => 'authWriteLock:edit,users'], static function ($routes) { ... });
 *     // other writes (POST/PUT/PATCH/DELETE) of a group of routes: same, but plain GET pages stay free
 *     $routes->post('admin/users/save', 'Users::save', ['filter' => 'authWriteLock:write,users']);
 *
 * Arguments: `edit|write[,scope]`. `edit` also acts on GET (opening a form is the start of an edit), `write`
 * (default) lets reads pass. The scope label (default `auth`) is extended with the first numeric URI segment, so
 * the peer sees e.g. "users:12". The editor is the logged-in Shield user.
 *
 * - This node holds the lock (manual, or a lease): the request passes; a lease is refreshed.
 * - Nobody holds it: the lease is taken, the peer is told what is being edited and it catches up with the
 *   peer's journal first.
 * - The peer holds it: the request is refused (and an alert is sent) with who edits what.
 * After a successful non-GET request the lease is handed over: the peer confirms it has applied our changes,
 * then the lock is released. A lease that is abandoned frees itself after `leaseTtl`.
 *
 * In views, show forms read-only and a banner using `readOnly()` / `banner()`. All texts come from
 * Language/<locale>/Sync.php (`lock.*`) in the current locale.
 */
class AuthWriteLock implements FilterInterface
{
    /** English wording of the refusal (kept for callers); `message()` gives it in the current locale. */
    public const MESSAGE = 'User editing is currently active on the other node.';

    /** The refusal message ("User editing is currently active on the other node.") in the current locale. */
    public static function message(): string
    {
        return Lang::line('lock.message');
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $mode  = $arguments[0] ?? 'write';
        $scope = $arguments[1] ?? 'auth';

        if ($mode !== 'edit' && $this->isRead($request)) {
            return null;
        }

        $kernel = Kernel::instance();
        $r      = $kernel->locks()->begin('auth', $this->scope($request, $scope), $this->editor());

        if ($r['ok']) {
            return null;
        }

        $this->alert($kernel);
        $msg = self::message() . ' ' . ($r['reason'] ?? '');

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(423)->setBody($msg);
        }

        return redirect()->back()->with('error', trim($msg));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($this->isRead($request) || $response->getStatusCode() >= 400) {
            return null;
        }

        try {
            $locks = Kernel::instance()->locks();

            if (($locks->describe('auth')['mode'] ?? '') === 'lease' && $locks->isHeld('auth')) {
                $r = $locks->handover('auth');

                if (! $r['ok']) {
                    log_message('error', 'authWriteLock handover: ' . ($r['reason'] ?? '?'));
                }
            }
        } catch (Throwable $e) {
            log_message('error', 'authWriteLock handover: ' . $e->getMessage());
        }

        return null;
    }

    /** For views: true while this node must show user-editing forms read-only (the peer is editing). */
    public static function readOnly(): bool
    {
        $k = Kernel::instance();
        $d = $k->locks()->describe('auth');

        return $d !== null && $d['holder'] !== $k->opt->node;
    }

    /** For views: "site1 is editing users:12 (admin) since 10:12" while the peer edits, otherwise an empty string. */
    public static function banner(): string
    {
        $k = Kernel::instance();
        $d = $k->locks()->describe('auth');

        if ($d === null || $d['holder'] === $k->opt->node) {
            return '';
        }

        return Lang::line('lock.banner', [
            $d['holder'],
            $d['scope'] !== '' ? $d['scope'] : Lang::line('lock.usersGroups'),
            $d['by'] !== '' ? ' (' . $d['by'] . ')' : '',
            gmdate('H:i', $d['since']),
        ]);
    }

    private function isRead(RequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function scope(RequestInterface $request, string $label): string
    {
        foreach ($request->getUri()->getSegments() as $seg) {
            if (ctype_digit($seg)) {
                return $label . ':' . $seg;
            }
        }

        return $label;
    }

    private function editor(): string
    {
        try {
            helper('auth');

            return (string) (auth()->user()?->username ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    /** Throttled to one alert per 10 minutes. */
    private function alert(Kernel $kernel): void
    {
        try {
            if (cache('sync_auth_alert') !== null) {
                return;
            }

            cache()->save('sync_auth_alert', 1, 600);
            $kernel->telegram()->send(Lang::line('telegram.blocked', [$kernel->opt->node, self::message()]));
        } catch (Throwable) {
        }
    }
}
