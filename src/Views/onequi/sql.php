<?php
/** @var string $sql */
/** @var ?array{columns: list<string>, rows: list<array<string, mixed>>, affected: int} $result */
/** @var ?string $error */
/** @var string $action */
/** @var string $back */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lang('Sync.onequi.sqlTitle') ?></title>
<?= $head ?? '' ?>
<style>
body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;max-width:1100px;margin-inline:auto;color:#1c1f26;background:#f6f7f9}
table{border-collapse:collapse;width:100%;background:#fff;margin-top:12px}th,td{padding:6px 8px;border-bottom:1px solid #dde;text-align:left;vertical-align:top;word-break:break-word}
textarea{width:100%;box-sizing:border-box;font-family:ui-monospace,monospace;font-size:13px}
button{padding:6px 12px;border:0;border-radius:5px;color:#fff;background:#1d5fd1;cursor:pointer}
.flash{padding:8px 12px;border-radius:6px;margin-bottom:12px;background:#e7f4ea}.err{background:#fde8e8}
@media (prefers-color-scheme:dark){body{background:#14161a;color:#e6e8ec}table{background:#1e2128}th,td{border-color:#343944}textarea{background:#1e2128;color:#e6e8ec}.flash{background:#1d3b27}.err{background:#4a2020}}
</style>
</head>
<body>
<?= $menu ?? '' ?>
<p><a href="<?= esc($back) ?>">&larr; <?= lang('Sync.onequi.allTables') ?></a></p>
<h1><?= lang('Sync.onequi.sqlTitle') ?></h1>
<?php if ($error !== null): ?><div class="flash err"><?= esc($error) ?></div><?php endif ?>
<form method="post" action="<?= esc($action) ?>">
<?= csrf_field() ?>
<textarea name="sql" rows="6" placeholder="SELECT * FROM ..."><?= esc($sql) ?></textarea>
<p><button type="submit"><?= lang('Sync.onequi.run') ?></button></p>
</form>
<?php if ($result !== null): ?>
<?php if ($result['columns'] === []): ?>
<p><?= lang('Sync.onequi.affected', [(string) $result['affected']]) ?></p>
<?php else: ?>
<table>
<tr><?php foreach ($result['columns'] as $c): ?><th><?= esc($c) ?></th><?php endforeach ?></tr>
<?php foreach ($result['rows'] as $row): ?>
<tr><?php foreach ($result['columns'] as $c): ?><td><?= $row[$c] === null ? '<i>NULL</i>' : esc((string) $row[$c]) ?></td><?php endforeach ?></tr>
<?php endforeach ?>
<?php if ($result['rows'] === []): ?><tr><td colspan="<?= count($result['columns']) ?>"><?= lang('Sync.onequi.noRows') ?></td></tr><?php endif ?>
</table>
<?php endif ?>
<?php endif ?>
</body>
</html>
