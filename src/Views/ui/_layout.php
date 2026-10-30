<?php
/** @var string $title @var string $content @var string|null $head @var string|null $scripts */
$cfg      = config('Sync');
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
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav') ?><?= $hostMenu ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<?= view('AdGo\Sync\Views\ui\_flash') ?>
<?= $content ?? '' ?>
</main>
</div>
<?= $scripts ?? '' ?>
</body>
</html>
