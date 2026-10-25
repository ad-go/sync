<?php /** @var string $node */ /** @var list<array<string,mixed>> $pending */ /** @var list<array<string,mixed>> $recent */ /** @var ?string $lockHeld */
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
<?= $head ?? '' ?>
<style>
body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;max-width:1000px;margin-inline:auto;color:#1c1f26;background:#f6f7f9}
table{border-collapse:collapse;width:100%;background:#fff}th,td{padding:6px 8px;border-bottom:1px solid #dde;text-align:left;vertical-align:top}
.flash{padding:8px 12px;border-radius:6px;margin-bottom:12px;background:#e7f4ea}.err{background:#fde8e8}
.banner{padding:8px 12px;border-radius:6px;background:#fff4d6;margin-bottom:12px}
a.btn{background:#1d5fd1;color:#fff;padding:4px 10px;border-radius:5px;text-decoration:none}
.tag{font-size:12px;padding:1px 6px;border-radius:4px;background:#eee}.danger{background:#fbd0d0}
@media (prefers-color-scheme:dark){body{background:#14161a;color:#e6e8ec}table{background:#1e2128}th,td{border-color:#343944}.tag{background:#343944}.danger{background:#6b2b2b}.banner{background:#4a3b12}.flash{background:#1d3b27}.err{background:#4a2020}}
<?php /* Sidebar wrapper for `$menu` (see src/Views/conflicts/show.php for the identical block) — scoped under
   `.cfshell` (never a bare selector) so it wins over `_theme.php`'s rules, which `_menu.php` injects inline,
   after this <style>, at equal specificity otherwise. The dividing line is an invisible 6px drag handle. */ ?>
<?= view('AdGo\Sync\Views\_adminChrome') ?>
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\_adminTopbar', ['node' => $node]) ?>
<div class="tb-resize" id="cfTbResize"></div>
<div class="cfshell">
<aside class="sidebar" id="cfSidebar"><?= view('AdGo\Sync\Views\_adminNav', ['active' => 'conflicts']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="cfSbResize"></div>
<main class="appmain">
<h1><?= lang('Sync.conflicts.title') ?> <small>(<?= lang('Sync.conflicts.node', [esc($node)]) ?>)</small></h1>
<?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<?php if ($lockHeld !== null && $lockHeld !== $node): ?><div class="banner"><?= lang('Sync.conflicts.editingActiveOn', ['<b>' . esc($lockHeld) . '</b>']) ?></div><?php endif ?>

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
  <td><a class="btn" href="<?= site_url(trim(config('Sync')->conflictsPath, '/') . '/' . (int) $c['id']) ?>"><?= lang('Sync.conflicts.review') ?></a></td>
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
<?= view('AdGo\Sync\Views\_adminShellJs') ?>
</body>
</html>
