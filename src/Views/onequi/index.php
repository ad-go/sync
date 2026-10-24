<?php
/** @var list<array{name: string, rows: int}> $tables */
/** @var string $base */
/** @var string $sql */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lang('Sync.onequi.title') ?></title>
<?= $head ?? '' ?>
<style>
body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;max-width:900px;margin-inline:auto;color:#1c1f26;background:#f6f7f9}
table{border-collapse:collapse;width:100%;background:#fff}th,td{padding:6px 8px;border-bottom:1px solid #dde;text-align:left}
td.n{text-align:right}
a.btn{background:#1d5fd1;color:#fff;padding:4px 10px;border-radius:5px;text-decoration:none}
.flash{padding:8px 12px;border-radius:6px;margin-bottom:12px;background:#e7f4ea}.err{background:#fde8e8}
@media (prefers-color-scheme:dark){body{background:#14161a;color:#e6e8ec}table{background:#1e2128}th,td{border-color:#343944}.flash{background:#1d3b27}.err{background:#4a2020}}
</style>
</head>
<body>
<?= $menu ?? '' ?>
<h1><?= lang('Sync.onequi.title') ?></h1>
<?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<p><a class="btn" href="<?= esc($sql) ?>"><?= lang('Sync.onequi.runSql') ?></a></p>
<table>
<tr><th><?= lang('Sync.onequi.th.table') ?></th><th><?= lang('Sync.onequi.th.rows') ?></th><th></th></tr>
<?php foreach ($tables as $t): ?>
<tr>
  <td><?= esc($t['name']) ?></td>
  <td class="n"><?= (int) $t['rows'] ?></td>
  <td><a href="<?= site_url($base . '/' . $t['name']) ?>"><?= lang('Sync.onequi.browse') ?></a></td>
</tr>
<?php endforeach ?>
<?php if ($tables === []): ?><tr><td colspan="3"><?= lang('Sync.onequi.noTables') ?></td></tr><?php endif ?>
</table>
</body>
</html>
