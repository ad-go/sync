<?php
/** @var string $title @var string $content @var string|null $head @var string|null $scripts */
$cfg      = config('Sync');
/* $embed = true: the page is a content-only tab of Settings (no topbar or sidebar). Set by the Users views. */
$embed    = (bool) ($embed ?? false);
$hostHead = ($cfg->headView !== '') ? view($cfg->headView) : '';
$hostMenu = ($cfg->menuView !== '') ? view($cfg->menuView) : '';
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title ?? '') ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<?= $hostHead ?>
<?= $head ?? '' ?>
</head>
<body<?= $embed ? ' class="embed"' : '' ?>>
<?php if ($embed): ?>
<main class="appmain" style="padding:12px 16px">
<?= view('AdGo\Sync\Views\ui\_flash') ?>
<?= $content ?? '' ?>
</main>
<script>
/* The CI4 debug toolbar (development only) is appended to every HTML response. The page that embeds this one already
   shows its own, so drop this copy. Nothing matches outside development, so this is a no-op there. */
window.addEventListener('DOMContentLoaded', function () {
  ['debug-icon', 'debug-bar', 'toolbar_js', 'debugbar_loader', 'debugbar_dynamic_script', 'debugbar_dynamic_style']
    .forEach(function (id) { var el = document.getElementById(id); if (el) { el.remove(); } });
});
</script>
<?php else: ?>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav') ?><?= $hostMenu ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<?= view('AdGo\Sync\Views\ui\_flash') ?>
<?= $content ?? '' ?>
</main>
</div>
<?php endif ?>
<?= $scripts ?? '' ?>
<script>
if (window.self !== window.top) {
  document.documentElement.classList.add('iframe-mode');
}
</script>
</body>
</html>
