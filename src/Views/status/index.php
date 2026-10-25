<?php
/** @var string $node */ /** @var string $peerNode */ /** @var string $peerUrl */ /** @var bool $passive */
/** @var int $cursor */ /** @var int $conflicts */ /** @var int $journal */
/** @var ?array{holder:string,mode:string,scope:string,by:string,since:int,expires_in:int} $authLock */
/** @var ?array{holder:string,mode:string,scope:string,by:string,since:int,expires_in:int} $cronLock */
/** @var string $base */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sync status · <?= esc($node) ?></title>
<?= $head ?? '' ?>
<style>
body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;max-width:900px;margin-inline:auto;color:#1c1f26;background:#f6f7f9}
table{border-collapse:collapse;width:100%;background:#fff;margin-bottom:16px}th,td{padding:6px 8px;border-bottom:1px solid #dde;text-align:left;vertical-align:top}
.flash{padding:8px 12px;border-radius:6px;margin-bottom:12px;background:#e7f4ea}.err{background:#fde8e8}
button{padding:6px 12px;border:0;border-radius:5px;color:#fff;background:#1d5fd1;cursor:pointer;margin-right:6px}button.s{background:#667}
.tag{font-size:12px;padding:1px 6px;border-radius:4px;background:#eee}
@media (prefers-color-scheme:dark){body{background:#14161a;color:#e6e8ec}table{background:#1e2128}th,td{border-color:#343944}.tag{background:#343944}.flash{background:#1d3b27}.err{background:#4a2020}}
<?= view('AdGo\Sync\Views\_adminChrome') ?>
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\_adminTopbar', ['node' => $node]) ?>
<div class="tb-resize" id="cfTbResize"></div>
<div class="cfshell">
<aside class="sidebar" id="cfSidebar"><?= view('AdGo\Sync\Views\_adminNav', ['active' => 'status']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="cfSbResize"></div>
<main class="appmain">
<h1>Sync status <small>(node <?= esc($node) ?>)</small></h1>
<?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<table>
<tr><th>This node</th><td><?= esc($node) ?><?= $passive ? ' <span class="tag">passive</span>' : '' ?></td></tr>
<tr><th>Peer</th><td><?= esc($peerNode) ?> &mdash; <?= esc($peerUrl) ?></td></tr>
<tr><th>Journal cursor (peer's last applied change)</th><td><?= (int) $cursor ?></td></tr>
<tr><th>Journal entries (this node)</th><td><?= (int) $journal ?></td></tr>
<tr><th>Pending conflicts</th><td><?= (int) $conflicts ?> <?php if ($conflicts > 0): ?><a href="<?= site_url(trim(config('Sync')->conflictsPath, '/')) ?>">review</a><?php endif ?></td></tr>
<tr><th>Auth/users lock</th><td>
<?php if ($authLock === null): ?>free<?php else: ?>
held by <b><?= esc($authLock['holder']) ?></b> (<?= esc($authLock['mode']) ?><?= $authLock['by'] !== '' ? ', ' . esc($authLock['by']) : '' ?>), expires in <?= (int) $authLock['expires_in'] ?>s
<?php endif ?>
</td></tr>
<tr><th>Cron lock</th><td><?= $cronLock === null ? 'free' : 'held by ' . esc($cronLock['holder']) . ', expires in ' . (int) $cronLock['expires_in'] . 's' ?></td></tr>
</table>

<form method="post" action="<?= site_url($base . '/sync-now') ?>">
  <?= csrf_field() ?>
  <button>Sync now</button>
</form>

<h3>Auth lock</h3>
<form method="post" action="<?= site_url($base . '/lock') ?>">
  <?= csrf_field() ?>
  <button name="action" value="take">Take lock</button>
  <button name="action" value="release" class="s">Release lock</button>
</form>
</main>
</div>
<?= view('AdGo\Sync\Views\_adminShellJs') ?>
</body>
</html>
