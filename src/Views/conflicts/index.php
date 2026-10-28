<?php
/** @var string $node */ /** @var string $peerNode */ /** @var string $peerUrl */ /** @var bool $passive */
/** @var int $cursor */ /** @var int $journal */
/** @var ?array{holder:string,mode:string,scope:string,by:string,since:int,expires_in:int} $authLock */
/** @var ?array{holder:string,mode:string,scope:string,by:string,since:int,expires_in:int} $cronLock */
/** @var list<array<string,mixed>> $pending */ /** @var list<array<string,mixed>> $recent */
/** @var ?string $lockHeld */ /** @var string $base */
// Column values (status / severity / resolved_as) are translated when a text exists, otherwise shown as stored.
$label = static function (string $group, string $value): string {
    $key = 'Sync.conflicts.' . $group . '.' . lcfirst(str_replace('-', '', ucwords($value, '-')));
    $t   = lang($key);

    return $t === $key ? $value : $t;
};
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lang('Sync.conflicts.title') ?> · <?= esc($node) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<?= $head ?? '' ?>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'conflicts']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1><?= lang('Sync.conflicts.title') ?> <small style="font-size:14px;font-weight:400;color:var(--mute)">(<?= lang('Sync.conflicts.node', [esc($node)]) ?>)</small></h1>
<?= view('AdGo\Sync\Views\ui\_flash') ?>
<?php if ($lockHeld !== null && $lockHeld !== $node): ?><div class="banner"><?= lang('Sync.conflicts.editingActiveOn', ['<b>' . esc($lockHeld) . '</b>']) ?></div><?php endif ?>

<details class="status-panel" style="margin-bottom:18px">
<summary style="cursor:pointer;font-weight:600;padding:10px 14px;background:var(--card);border:1px solid var(--line);border-radius:var(--r);list-style:none;display:flex;align-items:center;gap:8px">
  <span>Node status</span>
  <span class="tag" style="font-weight:400"><?= esc($node) ?><?= $passive ? ' · passive' : '' ?></span>
  <?php if ($authLock !== null): ?><span class="tag danger" style="font-weight:400">auth lock held</span><?php endif ?>
</summary>
<div style="background:var(--card);border:1px solid var(--line);border-top:0;border-radius:0 0 var(--r) var(--r);padding:14px">
  <table style="margin-bottom:12px">
  <tr><th>Peer</th><td><?= esc($peerNode) ?> — <code><?= esc($peerUrl) ?></code></td></tr>
  <tr><th>Journal cursor</th><td><?= (int) $cursor ?></td></tr>
  <tr><th>Journal entries</th><td><?= (int) $journal ?></td></tr>
  <tr><th>Auth lock</th><td><?php if ($authLock === null): ?>free<?php else: ?>held by <b><?= esc($authLock['holder']) ?></b> (<?= esc($authLock['mode']) ?><?= $authLock['by'] !== '' ? ', ' . esc($authLock['by']) : '' ?>), expires in <?= (int) $authLock['expires_in'] ?>s<?php endif ?></td></tr>
  <tr><th>Cron lock</th><td><?= $cronLock === null ? 'free' : 'held by ' . esc($cronLock['holder']) . ', expires in ' . (int) $cronLock['expires_in'] . 's' ?></td></tr>
  </table>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="post" action="<?= site_url($base . '/sync-now') ?>"><?= csrf_field() ?><button class="btn">Sync now</button></form>
    <form method="post" action="<?= site_url($base . '/lock') ?>"><?= csrf_field() ?>
      <button name="action" value="take" class="btn">Take lock</button>
      <button name="action" value="release" class="btn s">Release lock</button>
    </form>
  </div>
</div>
</details>

<h2><?= lang('Sync.conflicts.pending', [(string) count($pending)]) ?></h2>
<table>
<tr><th><?= lang('Sync.conflicts.th.id') ?></th><th><?= lang('Sync.conflicts.th.table') ?></th><th><?= lang('Sync.conflicts.th.row') ?></th><th><?= lang('Sync.conflicts.th.fields') ?></th><th><?= lang('Sync.conflicts.th.detected') ?></th><th></th></tr>
<?php foreach ($pending as $c): ?>
<tr>
  <td><?= (int) $c['id'] ?></td>
  <td><?= esc($c['table_name']) ?></td>
  <td><code><?= esc($c['uuid']) ?></code></td>
  <td><?= esc($c['fields'] === '*' ? lang('Sync.conflicts.deleteVsEdit') : $c['fields']) ?></td>
  <td><?= esc(gmdate('Y-m-d H:i:s', intdiv((int) $c['detected_at'], 1000000))) ?>Z</td>
  <td><a class="btn" href="<?= site_url($base . '/' . (int) $c['id']) ?>"><?= lang('Sync.conflicts.review') ?></a></td>
</tr>
<?php endforeach ?>
<?php if ($pending === []): ?><tr><td colspan="6"><?= lang('Sync.conflicts.nothingPending') ?></td></tr><?php endif ?>
</table>

<h2><?= lang('Sync.conflicts.recentDecisions') ?></h2>
<table>
<tr><th><?= lang('Sync.conflicts.th.id') ?></th><th><?= lang('Sync.conflicts.th.table') ?></th><th><?= lang('Sync.conflicts.th.row') ?></th><th><?= lang('Sync.conflicts.th.severity') ?></th><th><?= lang('Sync.conflicts.th.resolvedAs') ?></th><th><?= lang('Sync.conflicts.th.by') ?></th></tr>
<?php foreach ($recent as $c): ?>
<tr>
  <td><?= (int) $c['id'] ?></td><td><?= esc($c['table_name']) ?></td><td><code><?= esc($c['uuid']) ?></code></td>
  <td><span class="tag <?= $c['severity'] === 'dangerous' ? 'danger' : '' ?>"><?= esc($label('severity', (string) $c['severity'])) ?></span></td>
  <td><?= esc($label('resolution', (string) $c['resolved_as'])) ?></td><td><?= esc((string) $c['resolved_by']) ?></td>
</tr>
<?php endforeach ?>
</table>
</main>
</div>
</body>
</html>
