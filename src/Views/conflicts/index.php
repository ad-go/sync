<?php /** @var string $node */ /** @var list<array<string,mixed>> $pending */ /** @var list<array<string,mixed>> $recent */ /** @var ?string $lockHeld */ /** @var string $base */
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
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'conflicts']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1><?= lang('Sync.conflicts.title') ?> <small style="font-size:14px;font-weight:400;color:var(--mute)">(<?= lang('Sync.conflicts.node', [esc($node)]) ?>)</small></h1>
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
