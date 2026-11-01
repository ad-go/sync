<?php
/**
 * Package admin sidebar nav. Active state detected from the current URL, like the ia host app.
 * Settings and Users are only shown to Config\Sync::$superPermission holders.
 */
$cfg   = config('Sync');
$super = auth()->user() !== null && auth()->user()->can($cfg->superPermission);
$seg   = parse_url(current_url(), PHP_URL_PATH);
$on    = static fn (string $path): string =>
    str_contains($seg, '/' . trim($path, '/')) ? 'aria-current="page"' : '';
?>
<nav class="menu">
  <a href="<?= site_url(trim($cfg->dashboardPath, '/')) ?>" <?= $on($cfg->dashboardPath) ?>><i class="bi bi-speedometer2"></i> <?= lang('Sync.nav.dashboard') ?></a>
<?php if ($super): ?>
  <a href="<?= site_url(trim($cfg->settingsPath, '/')) ?>"
     <?= ($on($cfg->settingsPath) ?: $on($cfg->conflictsPath) ?: $on($cfg->usersPath)) ?>><i class="bi bi-gear"></i> <?= lang('Sync.nav.settings') ?></a>
<?php endif ?>
  <a href="<?= site_url('/') ?>" style="margin-top:auto;border-top:1px solid var(--line);padding-top:10px"><i class="bi bi-box-arrow-up-right"></i> <?= lang('Sync.nav.main_site') ?></a>
</nav>
