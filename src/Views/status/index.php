<?php
/** @var string $node */ /** @var string $peerNode */ /** @var string $peerUrl */ /** @var bool $passive */
/** @var int $cursor */ /** @var int $conflicts */ /** @var int $journal */
/** @var ?array{holder:string,mode:string,scope:string,by:string,since:int,expires_in:int} $authLock */
/** @var ?array{holder:string,mode:string,scope:string,by:string,since:int,expires_in:int} $cronLock */
/** @var string $base */ /** @var string $conflictsBase */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sync status · <?= esc($node) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'status']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1>Sync status <small style="font-size:14px;font-weight:400;color:var(--mute)">(node <?= esc($node) ?>)</small></h1>
<?= view('AdGo\Sync\Views\ui\_flash') ?>

<table>
<tr><th>This node</th><td><?= esc($node) ?><?= $passive ? ' <span class="tag">passive</span>' : '' ?></td></tr>
<tr><th>Peer</th><td><?= esc($peerNode) ?> &mdash; <?= esc($peerUrl) ?></td></tr>
<tr><th>Journal cursor (peer's last applied change)</th><td><?= (int) $cursor ?></td></tr>
<tr><th>Journal entries (this node)</th><td><?= (int) $journal ?></td></tr>
<tr><th>Pending conflicts</th><td><?= (int) $conflicts ?> <?php if ($conflicts > 0): ?><a href="<?= site_url($conflictsBase) ?>">review</a><?php endif ?></td></tr>
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
</body>
</html>
