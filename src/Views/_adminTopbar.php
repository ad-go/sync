<?php
/**
 * Top bar of the package's own admin pages' `.cfshell` (conflicts, status, settings, users; counterpart of a
 * host app's own `_topbar.php`, but self-contained: the package cannot depend on a host-specific helper/view).
 * Brand (name + logo, from AdGo\Sync\Libraries\Branding), language switch, "node · user", sign out; `$menu`'s
 * own copies of these are hidden in the sidebar via CSS (`.cfshell .sidebar nav.menu .brand,...{display:none}`),
 * not removed from `$menu` itself.
 *
 * @var ?string $node
 */
use AdGo\Sync\Libraries\Branding;

$node ??= (string) config('Sync')->node;
$logo  = Branding::logoUrl();
$user  = auth()->user();

try {
    $logoutUrl = url_to('logout');
} catch (\Throwable) {
    $logoutUrl = site_url('logout');
}

/*
 * Small flag <img> for a locale, from public/flags/<cc>.svg — the same vendored files the host app's own
 * site_locale_flag() serves (see its NOTICE.md); emoji flags were tried first but several Windows/Chrome
 * combinations show the two-letter code instead of a flag glyph. This DOES couple the package to a path
 * convention the host happens to follow here; a host without public/flags/ just shows no flag (not a broken
 * image — is_file() is checked first), same graceful fallback as the host's own helper.
 */
$flag = static function (string $code): string {
    $cc = [
        'en' => 'gb', 'ro' => 'ro', 'de' => 'de', 'fr' => 'fr', 'es' => 'es', 'it' => 'it',
        'pt' => 'pt', 'nl' => 'nl', 'pl' => 'pl', 'ru' => 'ru', 'tr' => 'tr', 'ar' => 'sa', 'zh' => 'cn', 'ja' => 'jp',
    ][$code] ?? null;

    if ($cc === null || ! is_file(FCPATH . "flags/{$cc}.svg")) {
        return '';
    }

    return '<img class="cftb-flag" src="' . esc(base_url("flags/{$cc}.svg")) . '" alt="" width="16" height="12">';
};
?>
<header class="cftopbar" id="cfTopbar">
  <a class="cftb-brand" href="<?= site_url('/') ?>"><?php if ($logo !== ''): ?><img src="<?= esc($logo) ?>" alt="" class="cftb-logo"><?php endif ?><?= esc(Branding::siteName()) ?></a>
  <span class="cftb-sp"></span>
  <?php $langs = array_diff(config('App')->supportedLocales, [service('request')->getLocale()]); ?>
  <?php if ($langs !== []): ?>
  <details class="cftb-dd">
    <summary><?= $flag(service('request')->getLocale()) ?> <?= esc(strtoupper(service('request')->getLocale())) ?></summary>
    <div class="cftb-ddmenu">
      <?php foreach ($langs as $code): ?><a href="<?= site_url('lang/' . $code) ?>"><?= $flag($code) ?> <?= esc(strtoupper($code)) ?></a><?php endforeach ?>
    </div>
  </details>
  <?php endif ?>
  <span class="cftb-who">node <?= esc($node) ?><?= $user !== null ? ' &middot; ' . esc((string) ($user->username ?? $user->email ?? '')) : '' ?></span>
  <a class="cftb-signout" href="<?= esc($logoutUrl) ?>">Sign out</a>
</header>
