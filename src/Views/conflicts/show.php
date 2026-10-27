<?php
/** @var array<string,mixed> $c */ /** @var ?array<string,mixed> $local */ /** @var ?array<string,mixed> $remote */
/** @var ?array<string,mixed> $base */ /** @var list<string> $fields */ /** @var array<int|string,string> $divergent */
/** @var string $action */ /** @var string $back */
$fmt = static fn ($v) => $v === null ? 'NULL' : (string) $v;
// The status is translated when a text exists, otherwise shown as stored.
$statusKey = 'Sync.conflicts.status.' . $c['status'];
$status    = lang($statusKey) === $statusKey ? (string) $c['status'] : lang($statusKey);
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lang('Sync.conflicts.conflict', [(string) (int) $c['id']]) ?></title>
<?= view('AdGo\Sync\Views\_theme') ?>
<style>
tr.diff td{background:var(--warn-bg)}
.del{color:var(--err-fg);font-style:italic}
input[type=text]{width:100%;box-sizing:border-box}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\_adminNav', ['active' => 'conflicts']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<p><a href="<?= esc($back) ?>">&larr; <?= lang('Sync.conflicts.allConflicts') ?></a></p>
<h1><?= lang('Sync.conflicts.conflict', [(string) (int) $c['id']]) ?> <small style="font-size:14px;font-weight:400;color:var(--mute)">(<?= esc($status) ?>)</small></h1>
<p><?= lang('Sync.conflicts.tableRow', ['<b>' . esc($c['table_name']) . '</b>', '<code>' . esc($c['uuid']) . '</code>']) ?>
<?= $local === null ? '<span class="del">' . lang('Sync.conflicts.deletedOn', [esc($c['local_node'])]) . '</span>' : '' ?>
<?= $remote === null ? '<span class="del">' . lang('Sync.conflicts.deletedOn', [esc($c['remote_node'])]) . '</span>' : '' ?></p>

<form method="post" action="<?= esc($action) ?>">
<?= csrf_field() ?>
<table>
<tr><th><?= lang('Sync.conflicts.th.field') ?></th><th><?= esc($c['local_node']) ?><br><small><?= esc(gmdate('Y-m-d H:i:s', intdiv((int) $c['local_ts'], 1000000))) ?>Z, <?= lang('Sync.conflicts.rev', [(string) (int) $c['local_rev']]) ?></small></th>
<th><?= esc($c['remote_node']) ?><br><small><?= esc(gmdate('Y-m-d H:i:s', intdiv((int) $c['remote_ts'], 1000000))) ?>Z, <?= lang('Sync.conflicts.rev', [(string) (int) $c['remote_rev']]) ?></small></th>
<th><?= lang('Sync.conflicts.th.lastCommon') ?></th><th><?= lang('Sync.conflicts.th.mergeResult') ?></th></tr>
<?php foreach ($fields as $f): $isDiff = in_array($f, $divergent, true); ?>
<tr class="<?= $isDiff ? 'diff' : '' ?>">
  <td><?= esc($f) ?></td>
  <td><?= esc($fmt($local[$f] ?? null)) ?></td>
  <td><?= esc($fmt($remote[$f] ?? null)) ?></td>
  <td><?= $base === null ? '<small>' . lang('Sync.conflicts.unknown') . '</small>' : esc($fmt($base[$f] ?? null)) ?></td>
  <td>
    <label><input type="radio" name="pick[<?= esc($f) ?>]" value="local" checked> <?= esc($c['local_node']) ?></label>
    <label><input type="radio" name="pick[<?= esc($f) ?>]" value="remote"> <?= esc($c['remote_node']) ?></label>
    <label><input type="radio" name="pick[<?= esc($f) ?>]" value="custom"> <?= lang('Sync.conflicts.other') ?></label>
    <input type="text" name="custom[<?= esc($f) ?>]" value="">
  </td>
</tr>
<?php endforeach ?>
</table>
<?php if ($c['status'] === 'pending'): ?>
<button name="action" value="keep_local"><?= esc(lang('Sync.conflicts.keep', [$c['local_node']])) ?></button>
<button name="action" value="keep_remote"><?= esc(lang('Sync.conflicts.keep', [$c['remote_node']])) ?></button>
<?php if ($local !== null && $remote !== null): ?><button name="action" value="merge" class="s"><?= lang('Sync.conflicts.manualMerge') ?></button><?php endif ?>
<button name="action" value="ignore" class="d"><?= lang('Sync.conflicts.ignore') ?></button>
<?php endif ?>
</form>
</main>
</div>
<script>!function(){var s=document.getElementById('appSidebar'),r=document.getElementById('sbResize');if(!s||!r)return;try{var w=localStorage.getItem('appshell-sw');if(w)s.style.width=w;}catch(e){}var d=!1,x,W;r.addEventListener('mousedown',function(e){d=!0;x=e.clientX;W=s.offsetWidth;document.body.style.cssText+='cursor:col-resize;user-select:none'});document.addEventListener('mousemove',function(e){if(!d)return;var n=Math.max(150,Math.min(400,W+e.clientX-x));s.style.width=n+'px'});document.addEventListener('mouseup',function(){if(!d)return;d=!1;document.body.style.cursor='';document.body.style.userSelect='';try{localStorage.setItem('appshell-sw',s.style.width);}catch(e){}});}();</script>
</body>
</html>
