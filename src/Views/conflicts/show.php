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
<?= $head ?? '' ?>
<style>
body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;max-width:1100px;margin-inline:auto;color:#1c1f26;background:#f6f7f9}
table{border-collapse:collapse;width:100%;background:#fff;margin-bottom:16px}th,td{padding:6px 8px;border-bottom:1px solid #dde;text-align:left;vertical-align:top;word-break:break-word}
tr.diff td{background:#fff1c7}.del{color:#a00;font-style:italic}
button{padding:6px 12px;border:0;border-radius:5px;color:#fff;background:#1d5fd1;cursor:pointer;margin-right:6px}button.s{background:#667}button.d{background:#b42318}
input[type=text]{width:100%;box-sizing:border-box}
@media (prefers-color-scheme:dark){body{background:#14161a;color:#e6e8ec}table{background:#1e2128}th,td{border-color:#343944}tr.diff td{background:#4a3b12}input{background:#14161a;color:#e6e8ec}}
<?php /* Sidebar wrapper for `$menu` — identical block to src/Views/conflicts/index.php, see its comment. */ ?>
html body{max-width:none;margin:0;padding:0;display:flex;flex-direction:column;min-height:100vh}
.cftopbar{flex:0 0 auto;position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:10px;padding:8px 14px;background:#fff;border-bottom:1px solid transparent;min-height:20px;box-sizing:border-box}
.cftopbar .cftb-brand{font-weight:800;font-size:16px;color:#1d5fd1;text-decoration:none}
.cftopbar .cftb-sp{flex:1}
.cftopbar .cftb-who{color:#6b7380;font-size:12px}
.cftopbar .cftb-dd{position:relative}
.cftopbar .cftb-dd>summary{list-style:none;cursor:pointer;padding:4px 9px;border:1px solid #dde;border-radius:6px;font-size:12px;font-weight:700;color:#1c1f26}
.cftopbar .cftb-dd>summary::-webkit-details-marker{display:none}
.cftopbar .cftb-dd .cftb-ddmenu{position:absolute;right:0;top:100%;margin-top:6px;background:#fff;border:1px solid #dde;border-radius:8px;box-shadow:0 8px 24px rgba(20,24,30,.12);padding:4px;min-width:90px;z-index:60}
.cftopbar .cftb-dd .cftb-ddmenu a{display:block;padding:6px 10px;border-radius:6px;color:#1c1f26;text-decoration:none;font-size:13px}
.cftopbar .cftb-dd .cftb-ddmenu a:hover{background:#eef2fb}
.cftopbar .cftb-signout{color:#1c1f26;font-weight:600;font-size:13px;text-decoration:none;padding:4px 9px;border:1px solid #dde;border-radius:6px}
.cftopbar .cftb-signout:hover{background:#eef2fb}
.tb-resize{flex:0 0 6px;cursor:row-resize;background:transparent}
.cfshell{flex:1;display:flex;min-height:0}
.cfshell>.sidebar{width:220px;min-width:150px;max-width:400px;overflow:auto;background:#fff;flex:0 0 auto}
.cfshell>.sb-resize{flex:0 0 6px;cursor:col-resize;background:transparent}
.cfshell>.appmain{flex:1;min-width:0;padding:16px;overflow:auto}
.cfshell .sidebar nav.menu{display:flex;flex-direction:column;flex-wrap:nowrap;align-items:stretch;gap:2px;padding:12px 10px;border:0;box-shadow:none;background:transparent}
.cfshell .sidebar nav.menu a{padding:6px 8px;border-radius:6px;border-bottom:0}
.cfshell .sidebar nav.menu a:hover,.cfshell .sidebar nav.menu a[aria-current=page]{background:#eef2fb;border-bottom-color:transparent}
.cfshell .sidebar nav.menu .brand,.cfshell .sidebar nav.menu .sp,.cfshell .sidebar nav.menu .who,.cfshell .sidebar nav.menu a.lang,.cfshell .sidebar nav.menu>a:last-child{display:none}
@media (prefers-color-scheme:dark){
  .cftopbar{background:#1e2128}
  .cftopbar .cftb-who{color:#9aa2b1}
  .cftopbar .cftb-dd>summary,.cftopbar .cftb-signout{border-color:#343944;color:#e6e8ec}
  .cftopbar .cftb-dd .cftb-ddmenu{background:#1e2128;border-color:#343944}
  .cftopbar .cftb-dd .cftb-ddmenu a{color:#e6e8ec}
  .cftopbar .cftb-dd .cftb-ddmenu a:hover,.cftopbar .cftb-signout:hover{background:#28303e}
  .cfshell>.sidebar{background:#1e2128}
  .cfshell .sidebar nav.menu a:hover,.cfshell .sidebar nav.menu a[aria-current=page]{background:#28303e}
}
@media (max-width:760px){
  .cfshell{flex-direction:column}
  .cfshell>.sidebar{width:100%;max-width:none}
  .cfshell .sidebar nav.menu{flex-direction:row;flex-wrap:wrap}
  .cfshell>.sb-resize,.tb-resize{display:none}
}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\conflicts\_topbar') ?>
<div class="tb-resize" id="cfTbResize"></div>
<div class="cfshell">
<aside class="sidebar" id="cfSidebar"><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="cfSbResize"></div>
<main class="appmain">
<p><a href="<?= esc($back) ?>">&larr; <?= lang('Sync.conflicts.allConflicts') ?></a></p>
<h1><?= lang('Sync.conflicts.conflict', [(string) (int) $c['id']]) ?> <small>(<?= esc($status) ?>)</small></h1>
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
<?= view('AdGo\Sync\Views\conflicts\_shell_js') ?>
</body>
</html>
