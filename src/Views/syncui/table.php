<?php
/** @var string $table */
/** @var list<object{name: string}> $columns */
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $sort */
/** @var string $dir */
/** @var array<string, string> $filters */
/** @var list<string> $pk */
/** @var bool $editing */
/** @var array<string, mixed> $editPk */
/** @var ?array<string, mixed> $editRow */
/** @var string $base */
/** @var string $action */
/** @var string $delAction */
/** @var string $self */
$names = array_map(static fn (object $c): string => $c->name, $columns);

$qs = static function (array $over) use ($page, $sort, $dir, $filters): string {
    $q = array_merge(['page' => $page, 'sort' => $sort, 'dir' => $dir, 'f' => $filters], $over);

    return '?' . http_build_query(array_filter($q, static fn ($v) => $v !== '' && $v !== [] && $v !== null));
};

// http_build_query() (used by $qs below) url-encodes this value itself; no extra encoding here.
$encodeKey = static fn (array $row): string => json_encode(array_intersect_key($row, array_flip($pk)), JSON_UNESCAPED_SLASHES);

$pages = (int) max(1, ceil($total / max(1, $perPage)));
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($table) ?> · <?= lang('Sync.syncUi.title') ?></title>
<?= $head ?? '' ?>
<style>
body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;max-width:1200px;margin-inline:auto;color:#1c1f26;background:#f6f7f9}
table{border-collapse:collapse;width:100%;background:#fff;margin-bottom:16px}th,td{padding:6px 8px;border-bottom:1px solid #dde;text-align:left;vertical-align:top;word-break:break-word}
th a{color:inherit}
input[type=text]{width:100%;box-sizing:border-box;font:inherit}
button{padding:5px 10px;border:0;border-radius:5px;color:#fff;background:#1d5fd1;cursor:pointer}
button.d{background:#b42318}
a.btn{background:#1d5fd1;color:#fff;padding:4px 10px;border-radius:5px;text-decoration:none}
.flash{padding:8px 12px;border-radius:6px;margin-bottom:12px;background:#e7f4ea}.err{background:#fde8e8}
.pager a{margin-right:8px}
@media (prefers-color-scheme:dark){body{background:#14161a;color:#e6e8ec}table{background:#1e2128}th,td{border-color:#343944}.flash{background:#1d3b27}.err{background:#4a2020}}
</style>
</head>
<body>
<?= $menu ?? '' ?>
<p><a href="<?= esc(site_url($base)) ?>">&larr; <?= lang('Sync.syncUi.allTables') ?></a></p>
<h1><?= esc($table) ?> <small>(<?= lang('Sync.syncUi.rowsOf', [(string) $total]) ?>)</small></h1>
<?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<?php if ($editing): ?>
<h2><?= $editPk === [] ? lang('Sync.syncUi.newRow') : lang('Sync.syncUi.editRow') ?></h2>
<form method="post" action="<?= esc($action) ?>">
<?= csrf_field() ?>
<?php foreach ($editPk as $col => $val): ?><input type="hidden" name="pk[<?= esc($col) ?>]" value="<?= esc((string) $val) ?>"><?php endforeach ?>
<table>
<?php foreach ($names as $n): ?>
<tr>
  <th><?= esc($n) ?><?= in_array($n, $pk, true) ? ' <small>(' . lang('Sync.syncUi.key') . ')</small>' : '' ?></th>
  <td>
  <?php if (in_array($n, $pk, true) && $editPk !== []): ?>
    <?= esc((string) ($editRow[$n] ?? '')) ?>
  <?php else: ?>
    <input type="text" name="v[<?= esc($n) ?>]" value="<?= esc((string) ($editRow[$n] ?? '')) ?>">
  <?php endif ?>
  </td>
</tr>
<?php endforeach ?>
</table>
<button type="submit"><?= $editPk === [] ? lang('Sync.syncUi.insert') : lang('Sync.syncUi.save') ?></button>
<a class="btn" href="<?= esc($self) ?>" style="background:#667"><?= lang('Sync.syncUi.cancel') ?></a>
</form>
<?php endif ?>

<form method="get" action="<?= esc($self) ?>">
<table>
<tr>
<?php foreach ($names as $n): $isSort = $sort === $n; $nextDir = $isSort && $dir === 'asc' ? 'desc' : 'asc'; ?>
  <th><a href="<?= esc($self . $qs(['sort' => $n, 'dir' => $nextDir, 'page' => 1])) ?>"><?= esc($n) ?><?= $isSort ? ($dir === 'asc' ? ' ▲' : ' ▼') : '' ?></a></th>
<?php endforeach ?>
  <th></th>
</tr>
<tr>
<?php foreach ($names as $n): ?>
  <td><input type="text" name="f[<?= esc($n) ?>]" value="<?= esc($filters[$n] ?? '') ?>"></td>
<?php endforeach ?>
  <td><button type="submit"><?= lang('Sync.syncUi.filter') ?></button></td>
</tr>
<?php foreach ($rows as $row): ?>
<tr>
<?php foreach ($names as $n): ?>
  <td><?= $row[$n] === null ? '<i>NULL</i>' : esc((string) $row[$n]) ?></td>
<?php endforeach ?>
  <td>
    <a href="<?= esc($self . $qs(['edit' => $encodeKey($row)])) ?>"><?= lang('Sync.syncUi.edit') ?></a>
    <form method="post" action="<?= esc($delAction) ?>" style="display:inline" onsubmit="return confirm('<?= esc(lang('Sync.syncUi.confirmDelete')) ?>')">
    <?= csrf_field() ?>
    <?php foreach (array_intersect_key($row, array_flip($pk)) as $col => $val): ?><input type="hidden" name="pk[<?= esc($col) ?>]" value="<?= esc((string) $val) ?>"><?php endforeach ?>
    <button type="submit" class="d"><?= lang('Sync.syncUi.delete') ?></button>
    </form>
  </td>
</tr>
<?php endforeach ?>
<?php if ($rows === []): ?><tr><td colspan="<?= count($names) + 1 ?>"><?= lang('Sync.syncUi.noRows') ?></td></tr><?php endif ?>
</table>
</form>

<p><a class="btn" href="<?= esc($self . $qs(['edit' => 'new'])) ?>"><?= lang('Sync.syncUi.newRow') ?></a></p>

<p class="pager">
<?php if ($page > 1): ?><a href="<?= esc($self . $qs(['page' => $page - 1])) ?>">&larr; <?= lang('Sync.syncUi.prev') ?></a><?php endif ?>
<?= lang('Sync.syncUi.pageOf', [(string) $page, (string) $pages]) ?>
<?php if ($page < $pages): ?><a href="<?= esc($self . $qs(['page' => $page + 1])) ?>"><?= lang('Sync.syncUi.next') ?> &rarr;</a><?php endif ?>
</p>
</body>
</html>
