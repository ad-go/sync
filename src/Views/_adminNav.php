<?php
/**
 * Switches between the package's own admin pages (conflicts/status/settings/users), shown at the top of the
 * sidebar, above the host application's own `$menu` (Config\Sync::$menuView) if any. Settings and Users are
 * only shown to `Config\Sync::$superPermission` holders.
 *
 * @var string $active one of 'conflicts'|'status'|'settings'|'users'
 */
$cfg   = config('Sync');
$super = auth()->user() !== null && auth()->user()->can($cfg->superPermission);

$items = [
    'users'     => ['label' => lang('Sync.nav.users'), 'path' => $cfg->usersPath, 'show' => $super],
    'conflicts' => ['label' => lang('Sync.nav.conflicts'), 'path' => $cfg->conflictsPath, 'show' => true],
    'status'    => ['label' => lang('Sync.nav.status'), 'path' => $cfg->statusPath, 'show' => true],
    'settings'  => ['label' => lang('Sync.nav.settings'), 'path' => $cfg->settingsPath, 'show' => $super],
];
?>
<nav class="menu">
<?php foreach ($items as $key => $it): ?>
<?php if (! $it['show']) { continue; } ?>
<a href="<?= site_url(trim($it['path'], '/')) ?>" <?= $key === $active ? 'aria-current="page"' : '' ?>><?= esc($it['label']) ?></a>
<?php endforeach ?>
</nav>

