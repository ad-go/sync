<?php
/**
 * Top bar of the conflicts admin page's `.cfshell` (counterpart of the host app's own `_topbar.php`, but
 * self-contained: the package cannot depend on a host-specific helper/view). Brand, language switch, "node ·
 * user", sign out; `$menu`'s own copies of these are hidden in the sidebar via CSS (`.cfshell .sidebar
 * nav.menu .brand,...{display:none}`), not removed from `$menu` itself.
 *
 * @var ?string $node
 */
$node ??= (string) config('Sync')->node;
$user  = auth()->user();

try {
    $logoutUrl = url_to('logout');
} catch (\Throwable) {
    $logoutUrl = site_url('logout');
}

// Emoji flags, graphic but nothing to load/vendor; self-contained (the package cannot depend on a host helper).
$flag = static fn (string $code): string => [
    'en' => '🇬🇧', 'ro' => '🇷🇴', 'de' => '🇩🇪', 'fr' => '🇫🇷', 'es' => '🇪🇸', 'it' => '🇮🇹',
    'pt' => '🇵🇹', 'nl' => '🇳🇱', 'pl' => '🇵🇱', 'ru' => '🇷🇺', 'tr' => '🇹🇷', 'ar' => '🇸🇦', 'zh' => '🇨🇳', 'ja' => '🇯🇵',
][$code] ?? '🌐';
?>
<header class="cftopbar" id="cfTopbar">
  <a class="cftb-brand" href="<?= site_url('/') ?>">Sync</a>
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
